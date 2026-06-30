// Targeted price crawler: one curated target per catalog product. Goes to the
// product page (directly or via its category landing), OPENS the quantity selector
// so every tier renders, then captures with Gemini vision. Tags each result with
// our product slug for direct mapping into the seeder.
//
// Run headed:  node src/crawl-targets.mjs   (key from ../backend/.env; reuses .userdata clearance)
import { chromium } from 'playwright';
import { mkdir, writeFile, readFile } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(HERE, '..', '..');
const DATA = path.join(ROOT, 'research', 'data');
const SHOTS = path.join(DATA, 'shots-targets');
const USERDATA = path.resolve(HERE, '..', '.userdata');
const BASE = 'https://www.vistaprint.com';
const MODEL = 'gemini-3.5-flash';
const HEADLESS = process.env.HEADLESS === 'true';
const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';

// our-product-slug, category landing or direct product url, keyword to find the product link
const TARGETS = [
    ['flyers', 'marketing-materials', `${BASE}/flyers`, /flyer/i],
    ['posters', 'marketing-materials', `${BASE}/signs-posters/posters`, /poster/i],
    ['brochures', 'marketing-materials', `${BASE}/marketing-materials/brochures`, /brochure/i],
    ['postcards', 'marketing-materials', `${BASE}/marketing-materials/standard-postcards`, /postcard/i],
    ['roll-up-banner', 'signs-banners', `${BASE}/signs-banners/retractable-banners`, /retractable|roll.?up/i],
    ['vinyl-banner', 'signs-banners', `${BASE}/signs-banners/vinyl-banners`, /vinyl|banner/i],
    ['yard-signs', 'signs-banners', `${BASE}/signs-banners/yard-signs`, /yard.?sign/i],
    ['window-decals', 'signs-banners', `${BASE}/signs-banners/window-decals`, /window|decal|cling/i],
    ['letterhead', 'stationery', `${BASE}/stationery/letterhead`, /letterhead/i],
    ['envelopes', 'stationery', `${BASE}/stationery/stationery/envelopes-mailing`, /envelope/i],
    ['notepads', 'stationery', `${BASE}/stationery/stationery/notebooks-pads-journals`, /notepad|notebook|pad/i],
    ['custom-stickers', 'stickers-labels', `${BASE}/labels-stickers/custom-stickers/single-stickers`, /sticker/i],
    ['roll-labels', 'stickers-labels', `${BASE}/labels-stickers/roll-labels`, /roll.?label|label/i],
    ['sheet-labels', 'stickers-labels', `${BASE}/labels-stickers/custom-stickers/sheet-stickers`, /sheet/i],
    ['custom-t-shirts', 'apparel-bags', `${BASE}/clothing-bags/t-shirts/short-sleeve-t-shirts`, /t.?shirt/i],
    ['tote-bags', 'apparel-bags', `${BASE}/clothing-bags/bags/totes`, /tote/i],
];

const PROMPT = `Screenshot of a print-shop PRODUCT page with the QUANTITY selector likely open.
Return ONLY JSON:
{"isProduct":true,"title":string,"category":string,"price":number,
 "options":[{"name":string,"values":[{"label":string,"priceDelta":number}]}],
 "quantities":[{"quantity":number,"totalPrice":number,"perUnit":number}]}
Read EVERY quantity tier visible (the open dropdown lists e.g. "100  $21.99  $0.22/ea"); include ALL of them.
Capture each option group (Size, Paper, Finish, Sides, Material, Corners, Shape) with price deltas.
If it is NOT a single configurable product page, return {"isProduct":false}.`;

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const rnd = (a, b) => Math.floor(a + Math.random() * (b - a));
const jitter = () => sleep(rnd(1200, 2800));
const log = (...a) => console.log(...a);
const sameHost = (u) => { try { return new URL(u).hostname.endsWith('vistaprint.com'); } catch { return false; } };
const looksBlocked = (h) => ['just a moment', 'verify you are human', 'captcha', 'attention required', 'pardon our interruption'].some((s) => h.toLowerCase().includes(s));

let KEY = process.env.GEMINI_API_KEY;
async function loadKey() {
    if (KEY) return;
    try { const env = await readFile(path.join(ROOT, 'backend', '.env'), 'utf8'); const m = env.match(/^GEMINI_API_KEY=(.+)$/m); if (m) KEY = m[1].trim().replace(/^["']|["']$/g, ''); } catch {}
}
async function dismissConsent(page) {
    for (const sel of ['#onetrust-accept-btn-handler', 'button#truste-consent-button']) { try { const el = page.locator(sel).first(); if (await el.count()) { await el.click({ timeout: 2000 }); return; } } catch {} }
    for (const re of [/accept all/i, /^accept$/i, /agree/i, /got it/i]) { try { const b = page.getByRole('button', { name: re }).first(); if ((await b.count()) && (await b.isVisible())) { await b.click({ timeout: 1500 }); return; } } catch {} }
}
async function waitForClearance(page) {
    for (let i = 0; i < 60; i++) {
        const title = (await page.title()).toLowerCase();
        if (!title.includes('just a moment') && !looksBlocked(await page.content())) return true;
        if (!HEADLESS && i === 0) log('   🔐 Cloudflare — solve it in the window if asked; waiting…');
        await sleep(3000);
    }
    return false;
}
async function isProductish(page) {
    const text = await page.evaluate(() => document.body.innerText).catch(() => '');
    const widget = (await page.locator('select, [data-testid*="quantity" i], [class*="quantity" i]').count()) > 0;
    return widget || /add to cart|select quantity|paper stock|paper thickness|qty/i.test(text);
}
async function expandQuantity(page) {
    try { await page.getByText(/^\s*quantity\s*$/i).first().scrollIntoViewIfNeeded({ timeout: 1500 }); } catch {}
    const tries = [
        () => page.locator('[data-testid*="quantity" i] [role="button"], [data-testid*="quantity" i] button').first(),
        () => page.getByRole('combobox', { name: /quantity/i }).first(),
        () => page.getByRole('button', { name: /quantity/i }).first(),
        () => page.locator('[class*="quantity" i]').getByRole('button').first(),
        () => page.locator('button,[role="button"]').filter({ hasText: /\d+\s*(\(|\$|\/ea|per unit)/i }).first(),
    ];
    for (const get of tries) {
        try { const el = get(); if ((await el.count()) && (await el.isVisible())) { await el.click({ timeout: 1500 }); await sleep(1000); return true; } } catch {}
    }
    return false;
}
async function parseWithGemini(buf) {
    if (!KEY) return { isProduct: false, error: 'no key' };
    try {
        const r = await fetch(`https://generativelanguage.googleapis.com/v1beta/models/${MODEL}:generateContent?key=${KEY}`, {
            method: 'POST', headers: { 'content-type': 'application/json' },
            body: JSON.stringify({ contents: [{ parts: [{ text: PROMPT }, { inlineData: { mimeType: 'image/png', data: buf.toString('base64') } }] }], generationConfig: { responseMimeType: 'application/json' } }),
        });
        const j = await r.json();
        return JSON.parse((j?.candidates?.[0]?.content?.parts || []).map((p) => p.text || '').join('') || '{}');
    } catch (e) { return { isProduct: false, error: String(e.message || e) }; }
}
async function collectLinks(page, kw, minDepth) {
    const links = await page.$$eval('a[href]', (as) => as.map((a) => a.href));
    const out = [];
    for (const href of links) {
        if (!sameHost(href)) continue;
        let u; try { u = new URL(href); } catch { continue; }
        const segs = u.pathname.split('/').filter(Boolean);
        if (segs.length <= minDepth || segs.length > 4) continue;
        if (kw.test(u.pathname)) out.push(u.origin + u.pathname);
    }
    return [...new Set(out)];
}

async function captureProduct(page, slug, cat, url) {
    const fname = `${slug}.png`;
    const shot = path.join(SHOTS, fname);
    await expandQuantity(page);
    const buf = await page.screenshot({ path: shot, fullPage: false });
    const parsed = await parseWithGemini(buf);
    if (parsed?.isProduct && (parsed.quantities || []).length) {
        log(`   ✅ ${slug}: ${(parsed.quantities || []).length} tiers — ${parsed.title}`);
        return { ourSlug: slug, ourCategory: cat, url, ...parsed, screenshot: path.relative(ROOT, shot) };
    }
    log(`   — ${slug}: no tiers parsed`);
    return null;
}

async function main() {
    await loadKey();
    await mkdir(SHOTS, { recursive: true });
    await mkdir(USERDATA, { recursive: true });
    if (!KEY) log('⚠️  no GEMINI key — cannot parse.');

    const ctx = await chromium.launchPersistentContext(USERDATA, {
        headless: HEADLESS, userAgent: UA, viewport: { width: 1440, height: 1700 }, locale: 'en-US', timezoneId: 'America/New_York',
        extraHTTPHeaders: { 'Accept-Language': 'en-US,en;q=0.9' }, args: ['--disable-blink-features=AutomationControlled'],
    });
    await ctx.addInitScript(() => Object.defineProperty(navigator, 'webdriver', { get: () => undefined }));
    const page = ctx.pages()[0] || (await ctx.newPage());
    page.setDefaultTimeout(40000);

    const products = [];
    for (const [slug, cat, landing, kw] of TARGETS) {
        log(`\n▶ ${slug}  (${landing})`);
        try {
            await page.goto(landing, { waitUntil: 'domcontentloaded' });
            await dismissConsent(page);
            await jitter();
            if (!(await waitForClearance(page))) { log(`   🚧 blocked`); continue; }

            let rec = null;
            if (await isProductish(page)) {
                rec = await captureProduct(page, slug, cat, landing);
            }
            if (!rec) {
                // landing was a listing — try product links matching the keyword
                const landingDepth = new URL(landing).pathname.split('/').filter(Boolean).length;
                const cands = (await collectLinks(page, kw, Math.max(1, landingDepth))).slice(0, 3);
                for (const c of cands) {
                    try {
                        await page.goto(c, { waitUntil: 'domcontentloaded' });
                        await dismissConsent(page);
                        await jitter();
                        if (!(await waitForClearance(page))) continue;
                        if (await isProductish(page)) { rec = await captureProduct(page, slug, cat, c); if (rec) break; }
                    } catch {}
                }
            }
            if (rec) products.push(rec);
        } catch (e) { log(`   error: ${e.message}`); }
    }

    await ctx.close();
    await writeFile(path.join(DATA, 'vistaprint-targets.json'), JSON.stringify(products, null, 2));
    const pRows = [['ourSlug', 'ourCategory', 'title', 'quantity', 'totalPrice', 'perUnit', 'url']];
    const oRows = [['ourSlug', 'title', 'option', 'value', 'priceDelta', 'url']];
    for (const p of products) {
        for (const q of p.quantities || []) pRows.push([p.ourSlug, p.ourCategory, p.title, q.quantity, q.totalPrice, q.perUnit ?? '', p.url]);
        for (const o of p.options || []) for (const v of o.values || []) oRows.push([p.ourSlug, p.title, o.name, v.label, v.priceDelta ?? 0, p.url]);
    }
    const csv = (r) => r.map((x) => x.map((c) => `"${String(c ?? '').replace(/"/g, '""')}"`).join(',')).join('\n');
    await writeFile(path.join(DATA, 'vistaprint-targets-prices.csv'), csv(pRows));
    await writeFile(path.join(DATA, 'vistaprint-targets-options.csv'), csv(oRows));
    log(`\nDone. Captured ${products.length}/${TARGETS.length} targets -> research/data/vistaprint-targets*.{json,csv}`);
}

main().catch((e) => { console.error('FATAL', e); process.exit(1); });
