// Deep price crawler (stealth + Gemini-vision).
//
// Captures options (with price deltas) + per-quantity prices for every product it can reach.
// Uses a realistic UA, randomized human-like waits, a persistent browser profile, and a
// Cloudflare-clearance wait. Parses each page's buy panel with Gemini vision (robust against
// Vistaprint's obfuscated/custom widgets — native <select> scraping does NOT work there).
//
// Cloudflare: run HEADED (default) so the passive "Just a moment" JS check clears and the
// clearance cookie persists. If a real CAPTCHA appears, SOLVE IT MANUALLY in the window —
// this tool does NOT automate CAPTCHA-solving (that would circumvent an access control).
//
// Run (headed):   $env:GEMINI_API_KEY='...'; node src/crawl-deep.mjs
// Run headless:   $env:GEMINI_API_KEY='...'; $env:HEADLESS='true'; node src/crawl-deep.mjs
import { chromium } from 'playwright';
import { mkdir, writeFile } from 'node:fs/promises';
import path from 'node:path';

const ROOT = path.resolve('..');
const DATA = path.join(ROOT, 'research', 'data');
const SHOTS = path.join(DATA, 'shots-deep');
const USERDATA = path.resolve('.userdata');
const BASE = 'https://www.vistaprint.com';
const KEY = process.env.GEMINI_API_KEY;
const MODEL = 'gemini-3.5-flash';
const HEADLESS = process.env.HEADLESS === 'true';
const MAX = Number(process.env.MAX_PRODUCTS || 12);
const MAX_ATTEMPTS = Number(process.env.MAX_ATTEMPTS || 45);
const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';

const BLOCK =
    /\/(account|cart|help|sign-?in|login|customer|orders?|gallery|about|careers|reviews|contact|blog|ideas|incentives|promotions|wallet|saved|my-|legal|privacy|terms|sitemap|store-locator|design-services|customer-care)\b/i;

const PROMPT = `Screenshot of a print-shop PRODUCT page. Return ONLY JSON.
If it is a single configurable product:
{"isProduct":true,"title":string,"category":string,"price":number,
 "options":[{"name":string,"values":[{"label":string,"priceDelta":number}]}],
 "quantities":[{"quantity":number,"totalPrice":number,"perUnit":number}]}
Capture EVERY option group shown (Paper Stock/Thickness, Corners, Size, Finish, Sides, Material, Shape, etc.)
and EVERY value with its price delta (0/+/-). Read all quantity tiers with total + per-unit price.
If NOT a single product page, return {"isProduct":false}.`;

const rnd = (a, b) => Math.floor(a + Math.random() * (b - a));
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const jitter = () => sleep(rnd(1300, 3400));
const log = (...a) => console.log(...a);
const sameHost = (u) => { try { return new URL(u).hostname.endsWith('vistaprint.com'); } catch { return false; } };
const looksBlocked = (h) =>
    ['just a moment', 'verify you are human', 'captcha', 'attention required', 'pardon our interruption'].some((s) =>
        h.toLowerCase().includes(s)
    );

async function dismissConsent(page) {
    for (const sel of ['#onetrust-accept-btn-handler', 'button#truste-consent-button']) {
        try { const el = page.locator(sel).first(); if (await el.count()) { await el.click({ timeout: 2500 }); return; } } catch {}
    }
    for (const re of [/accept all/i, /^accept$/i, /agree/i, /got it/i]) {
        try { const b = page.getByRole('button', { name: re }).first(); if ((await b.count()) && (await b.isVisible())) { await b.click({ timeout: 2000 }); return; } } catch {}
    }
}

async function waitForClearance(page) {
    for (let i = 0; i < 60; i++) {
        const title = (await page.title()).toLowerCase();
        if (!title.includes('just a moment') && !looksBlocked(await page.content())) return true;
        if (!HEADLESS && i === 0) log('   🔐 Cloudflare challenge — solve it in the window if asked; waiting up to 3 min…');
        await sleep(3000);
    }
    return false;
}

async function collectLinks(page) {
    const links = await page.$$eval('a[href]', (as) => as.map((a) => a.href));
    const out = [];
    for (const href of links) {
        if (!sameHost(href)) continue;
        let u; try { u = new URL(href); } catch { continue; }
        const segs = u.pathname.split('/').filter(Boolean);
        if (segs.length < 1 || segs.length > 3 || BLOCK.test(u.pathname)) continue;
        out.push(u.origin + u.pathname);
    }
    return out;
}

async function parseWithGemini(buf) {
    if (!KEY) return { isProduct: false, error: 'no GEMINI_API_KEY' };
    try {
        const r = await fetch(`https://generativelanguage.googleapis.com/v1beta/models/${MODEL}:generateContent?key=${KEY}`, {
            method: 'POST',
            headers: { 'content-type': 'application/json' },
            body: JSON.stringify({
                contents: [{ parts: [{ text: PROMPT }, { inlineData: { mimeType: 'image/png', data: buf.toString('base64') } }] }],
                generationConfig: { responseMimeType: 'application/json' },
            }),
        });
        const j = await r.json();
        return JSON.parse((j?.candidates?.[0]?.content?.parts || []).map((p) => p.text || '').join('') || '{}');
    } catch (e) {
        return { isProduct: false, error: String(e.message || e) };
    }
}

async function main() {
    await mkdir(SHOTS, { recursive: true });
    await mkdir(USERDATA, { recursive: true });
    if (!KEY) log('⚠️  GEMINI_API_KEY not set — pages will be visited but not parsed.');

    const ctx = await chromium.launchPersistentContext(USERDATA, {
        headless: HEADLESS,
        userAgent: UA,
        viewport: { width: 1440, height: 1600 },
        locale: 'en-US',
        timezoneId: 'America/New_York',
        extraHTTPHeaders: { 'Accept-Language': 'en-US,en;q=0.9' },
        args: ['--disable-blink-features=AutomationControlled'],
    });
    await ctx.addInitScript(() => Object.defineProperty(navigator, 'webdriver', { get: () => undefined }));
    const page = ctx.pages()[0] || (await ctx.newPage());
    page.setDefaultTimeout(45000);

    const queue = [];
    const seen = new Set();
    const enqueue = (urls) => urls.forEach((u) => !seen.has(u) && (seen.add(u), queue.push(u)));

    for (const seed of [`${BASE}/`, `${BASE}/business-cards`, `${BASE}/marketing-materials`, `${BASE}/signage`]) {
        try {
            await page.goto(seed, { waitUntil: 'domcontentloaded' });
            await dismissConsent(page);
            await jitter();
            if (!(await waitForClearance(page))) { log(`🚧 blocked seed ${seed}`); continue; }
            const found = await collectLinks(page);
            enqueue(found);
            log(`seed ${seed} -> ${found.length} links (queue ${queue.length})`);
        } catch (e) { log(`seed fail ${seed}: ${e.message}`); }
    }

    const products = [];
    let attempts = 0;
    while (queue.length && products.length < MAX && attempts < MAX_ATTEMPTS) {
        const url = queue.shift();
        attempts++;
        try {
            await page.goto(url, { waitUntil: 'domcontentloaded' });
            await dismissConsent(page);
            await jitter();
            if (!(await waitForClearance(page))) { log(`[${attempts}] blocked ${url}`); continue; }

            const text = await page.evaluate(() => document.body.innerText);
            const productish = (await page.locator('select, [data-testid*="quantity" i]').count()) > 0 || /add to cart|quantity|paper stock|paper thickness/i.test(text);
            if (!productish) { enqueue(await collectLinks(page)); log(`[${attempts}] browse ${url} (queue ${queue.length})`); continue; }

            const slug = (new URL(url).pathname.split('/').filter(Boolean).pop() || 'product').slice(0, 40);
            const shot = path.join(SHOTS, `${String(products.length + 1).padStart(2, '0')}-${slug}.png`);
            const buf = await page.screenshot({ path: shot, fullPage: false });
            const parsed = await parseWithGemini(buf);
            if (parsed?.isProduct) {
                products.push({ url, title: parsed.title, ...parsed, screenshot: path.relative(ROOT, shot) });
                const nOpt = (parsed.options || []).reduce((a, o) => a + (o.values || []).length, 0);
                log(`[${attempts}] ✅ ${products.length}/${MAX} ${parsed.title} — ${(parsed.quantities || []).length} qty, ${nOpt} option values`);
            } else {
                enqueue(await collectLinks(page));
                log(`[${attempts}] not-product ${url}`);
            }
        } catch (e) { log(`[${attempts}] error ${url}: ${e.message}`); }
    }

    await ctx.close();
    await writeFile(path.join(DATA, 'vistaprint-deep-prices.json'), JSON.stringify(products, null, 2));
    const rows = [['title', 'category', 'option', 'value', 'priceDelta', 'url']];
    const priceRows = [['title', 'quantity', 'totalPrice', 'perUnit', 'url']];
    for (const p of products) {
        for (const o of p.options || []) for (const v of o.values || []) rows.push([p.title, p.category || '', o.name, v.label, v.priceDelta ?? 0, p.url]);
        for (const q of p.quantities || []) priceRows.push([p.title, q.quantity, q.totalPrice, q.perUnit ?? '', p.url]);
    }
    const csv = (r) => r.map((x) => x.map((c) => `"${String(c ?? '').replace(/"/g, '""')}"`).join(',')).join('\n');
    await writeFile(path.join(DATA, 'vistaprint-deep-options.csv'), csv(rows));
    await writeFile(path.join(DATA, 'vistaprint-deep-prices.csv'), csv(priceRows));
    log(`\nDone. ${products.length} products -> research/data/vistaprint-deep-prices.json (+ options/prices CSVs)`);
}

main().catch((e) => { console.error('FATAL', e); process.exit(1); });
