// Category-balanced headed price crawler for ALL Vistaprint categories.
//
// Improves on crawl-deep: seeds every category, caps products PER category so one
// category can't starve the others, and tags each product with our catalog category
// slug for easy mapping back into the seeder. Parses each buy panel with Gemini vision
// (Vistaprint's markup is obfuscated; native <select> scraping doesn't work).
//
// Cloudflare: runs HEADED by default. If a "Just a moment"/CAPTCHA appears, SOLVE IT
// in the window — this tool does NOT automate CAPTCHA solving. The cleared cookie
// persists in .userdata so you usually solve once.
//
// Run:  node src/crawl-all.mjs        (reads GEMINI_API_KEY from ../backend/.env)
import { chromium } from 'playwright';
import { mkdir, writeFile, readFile } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(HERE, '..', '..');           // repo root, independent of CWD
const DATA = path.join(ROOT, 'research', 'data');
const SHOTS = path.join(DATA, 'shots-all');
const USERDATA = path.resolve(HERE, '..', '.userdata'); // reuse prior Cloudflare clearance
const BASE = 'https://www.vistaprint.com';
const MODEL = 'gemini-3.5-flash';
const HEADLESS = process.env.HEADLESS === 'true';
const PER_CAT = Number(process.env.PER_CAT || 4);
const MAX_ATTEMPTS = Number(process.env.MAX_ATTEMPTS || 80);
const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';

const BLOCK =
    /\/(account|cart|help|sign-?in|login|customer|orders?|gallery|about|careers|reviews|contact|blog|ideas|incentives|promotions|wallet|saved|my-|legal|privacy|terms|sitemap|store-locator|design-services|customer-care)\b/i;

// Our catalog categories, seeded from their Vistaprint landing pages (best-effort;
// homepage BFS backfills whatever a given path misses).
const SEEDS = [
    ['*', `${BASE}/`],
    ['business-cards', `${BASE}/business-cards`],
    ['marketing-materials', `${BASE}/marketing-materials`],
    ['marketing-materials', `${BASE}/flyers`],
    ['signs-banners', `${BASE}/signs-posters`],
    ['signs-banners', `${BASE}/banners`],
    ['stickers-labels', `${BASE}/labels-stickers`],
    ['stickers-labels', `${BASE}/stickers`],
    ['stationery', `${BASE}/stationery`],
    ['apparel-bags', `${BASE}/clothing-bags`],
];

const PROMPT = `Screenshot of a print-shop PRODUCT page. Return ONLY JSON.
If it is a single configurable product:
{"isProduct":true,"title":string,"category":string,"price":number,
 "options":[{"name":string,"values":[{"label":string,"priceDelta":number}]}],
 "quantities":[{"quantity":number,"totalPrice":number,"perUnit":number}]}
Capture EVERY option group (Paper Stock/Thickness, Corners, Size, Finish, Sides, Material, Shape, etc.)
with each value's price delta (0/+/-), and EVERY quantity tier with total + per-unit price.
If NOT a single product page, return {"isProduct":false}.`;

const rnd = (a, b) => Math.floor(a + Math.random() * (b - a));
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const jitter = () => sleep(rnd(1300, 3200));
const log = (...a) => console.log(...a);
const sameHost = (u) => { try { return new URL(u).hostname.endsWith('vistaprint.com'); } catch { return false; } };
const looksBlocked = (h) =>
    ['just a moment', 'verify you are human', 'captcha', 'attention required', 'pardon our interruption'].some((s) =>
        h.toLowerCase().includes(s));

// Map a Vistaprint url/title to our catalog category slug.
function catOf(s) {
    s = (s || '').toLowerCase();
    if (/business.?card/.test(s)) return 'business-cards';
    if (/flyer|postcard|brochure|poster|leaflet|menu|greeting|calendar|door.?hanger/.test(s)) return 'marketing-materials';
    if (/banner|yard.?sign|lawn.?sign|a.?frame|\bsign\b|decal|cling|feather|tablecloth|backdrop|foam/.test(s)) return 'signs-banners';
    if (/sticker|label|magnet/.test(s)) return 'stickers-labels';
    if (/letterhead|envelope|notepad|notebook|stationery|folder/.test(s)) return 'stationery';
    if (/t.?shirt|shirt|tote|\bbag\b|hoodie|\bhat\b|\bcap\b|apparel|clothing|polo|mug|drinkware|\bpen\b/.test(s)) return 'apparel-bags';
    return 'other';
}

let KEY = process.env.GEMINI_API_KEY;
async function loadKey() {
    if (KEY) return;
    try {
        const env = await readFile(path.join(ROOT, 'backend', '.env'), 'utf8');
        const m = env.match(/^GEMINI_API_KEY=(.+)$/m);
        if (m) KEY = m[1].trim().replace(/^["']|["']$/g, '');
    } catch {}
}

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
    await loadKey();
    await mkdir(SHOTS, { recursive: true });
    await mkdir(USERDATA, { recursive: true });
    if (!KEY) log('⚠️  GEMINI_API_KEY not found (env or backend/.env) — pages visited but not parsed.');

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

    for (const [, seed] of SEEDS) {
        try {
            await page.goto(seed, { waitUntil: 'domcontentloaded' });
            await dismissConsent(page);
            await jitter();
            if (!(await waitForClearance(page))) { log(`🚧 blocked seed ${seed}`); continue; }
            const found = await collectLinks(page);
            enqueue([seed, ...found]);
            log(`seed ${seed} -> ${found.length} links (queue ${queue.length})`);
        } catch (e) { log(`seed fail ${seed}: ${e.message}`); }
    }

    const products = [];
    const counts = {};                       // per-category captured count
    const capped = (c) => (counts[c] || 0) >= PER_CAT;
    let attempts = 0;

    while (queue.length && attempts < MAX_ATTEMPTS) {
        const url = queue.shift();
        // Skip product-looking URLs whose category is already full (keeps breadth).
        const guess = catOf(url);
        if (guess !== 'other' && capped(guess) && new URL(url).pathname.split('/').filter(Boolean).length >= 2) continue;
        attempts++;
        try {
            await page.goto(url, { waitUntil: 'domcontentloaded' });
            await dismissConsent(page);
            await jitter();
            if (!(await waitForClearance(page))) { log(`[${attempts}] blocked ${url}`); continue; }

            const text = await page.evaluate(() => document.body.innerText);
            const productish = (await page.locator('select, [data-testid*="quantity" i]').count()) > 0
                || /add to cart|select quantity|paper stock|paper thickness/i.test(text);
            if (!productish) { enqueue(await collectLinks(page)); log(`[${attempts}] browse ${url} (queue ${queue.length})`); continue; }

            const slug = (new URL(url).pathname.split('/').filter(Boolean).pop() || 'product').slice(0, 40);
            const shot = path.join(SHOTS, `${String(products.length + 1).padStart(2, '0')}-${slug}.png`);
            const buf = await page.screenshot({ path: shot, fullPage: false });
            const parsed = await parseWithGemini(buf);

            if (parsed?.isProduct && (parsed.quantities || []).length) {
                const cat = catOf(`${url} ${parsed.title} ${parsed.category}`);
                if (cat !== 'other' && capped(cat)) { log(`[${attempts}] skip (cap ${cat}) ${parsed.title}`); continue; }
                counts[cat] = (counts[cat] || 0) + 1;
                products.push({ ourCategory: cat, url, ...parsed, screenshot: path.relative(ROOT, shot) });
                log(`[${attempts}] ✅ ${parsed.title}  [${cat}]  ${(parsed.quantities || []).length} tiers  (counts: ${JSON.stringify(counts)})`);
            } else {
                enqueue(await collectLinks(page));
                log(`[${attempts}] not-product ${url}`);
            }
        } catch (e) { log(`[${attempts}] error ${url}: ${e.message}`); }
    }

    await ctx.close();

    await writeFile(path.join(DATA, 'vistaprint-all.json'), JSON.stringify(products, null, 2));
    const pRows = [['ourCategory', 'title', 'quantity', 'totalPrice', 'perUnit', 'url']];
    const oRows = [['ourCategory', 'title', 'option', 'value', 'priceDelta', 'url']];
    for (const p of products) {
        for (const q of p.quantities || []) pRows.push([p.ourCategory, p.title, q.quantity, q.totalPrice, q.perUnit ?? '', p.url]);
        for (const o of p.options || []) for (const v of o.values || []) oRows.push([p.ourCategory, p.title, o.name, v.label, v.priceDelta ?? 0, p.url]);
    }
    const csv = (r) => r.map((x) => x.map((c) => `"${String(c ?? '').replace(/"/g, '""')}"`).join(',')).join('\n');
    await writeFile(path.join(DATA, 'vistaprint-all-prices.csv'), csv(pRows));
    await writeFile(path.join(DATA, 'vistaprint-all-options.csv'), csv(oRows));

    log(`\nDone. ${products.length} products across ${Object.keys(counts).length} categories: ${JSON.stringify(counts)}`);
    log('-> research/data/vistaprint-all.json (+ prices/options CSVs)');
}

main().catch((e) => { console.error('FATAL', e); process.exit(1); });
