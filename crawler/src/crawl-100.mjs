// Deep, slow, resumable Vistaprint crawler — up to ~100 products across all catalog
// categories. Captures prices, every print option/value (with price deltas), all
// quantity tiers, AND surface geometry (dimensions per size value, fold lines,
// no-print areas) read from the product's Specifications / "Specs & Templates" section.
//
// Built to run gently over ~4-5 hours: long, jittered pauses between products so the
// traffic looks human and stays light on Vistaprint's servers. Human-solves-CAPTCHA
// only (no automated bot-protection bypass); the cleared cookie persists in .userdata.
//
// RESUMABLE: writes research/data/vistaprint-100.json after every product and reloads
// it on start, so you can stop/restart (or recover from a crash) without losing work.
//
// Run:   node src/crawl-100.mjs
// Tune:  MAX_PRODUCTS=100 PER_CAT=25 TARGET_HOURS=4.5 HEADLESS=false node src/crawl-100.mjs
import { chromium } from 'playwright';
import { mkdir, writeFile, readFile } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(HERE, '..', '..');
const DATA = path.join(ROOT, 'research', 'data');
const SHOTS = path.join(DATA, 'shots-100');
// Canonical output lives under backend/ so it's mounted into the app container,
// importable (catalog:import) and committable — like the SEO/template bundles.
const SEED_DIR = path.join(ROOT, 'backend', 'database', 'seed');
const OUT = path.join(SEED_DIR, 'vistaprint-100.json');
const USERDATA = path.resolve(HERE, '..', '.userdata');
const BASE = 'https://www.vistaprint.com';
const MODEL = 'gemini-3.5-flash';

const HEADLESS = process.env.HEADLESS === 'true';
const MAX_PRODUCTS = Number(process.env.MAX_PRODUCTS || 100);
const PER_CAT = Number(process.env.PER_CAT || 25);
const MAX_ATTEMPTS = Number(process.env.MAX_ATTEMPTS || 600);
const TARGET_HOURS = Number(process.env.TARGET_HOURS || 4.5);
// Average cool-down per captured product to spread the run across TARGET_HOURS.
const PACE_MS = Math.max(20_000, Math.round((TARGET_HOURS * 3600_000) / MAX_PRODUCTS));
const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';

const BLOCK =
    /\/(account|cart|help|sign-?in|login|customer|orders?|gallery|about|careers|reviews|contact|blog|ideas|incentives|promotions|wallet|saved|my-|legal|privacy|terms|sitemap|store-locator|design-services|customer-care)\b/i;

const SEEDS = [
    ['*', `${BASE}/`],
    ['business-cards', `${BASE}/business-cards`],
    ['marketing-materials', `${BASE}/marketing-materials`],
    ['marketing-materials', `${BASE}/flyers`],
    ['marketing-materials', `${BASE}/marketing-materials/postcards`],
    ['signs-banners', `${BASE}/signs-posters`],
    ['signs-banners', `${BASE}/banners`],
    ['signs-banners', `${BASE}/signs-banners/yard-signs`],
    ['stickers-labels', `${BASE}/labels-stickers`],
    ['stickers-labels', `${BASE}/stickers`],
    ['stationery', `${BASE}/stationery`],
    ['apparel-bags', `${BASE}/clothing-bags`],
];

// Gemini vision prompt — options + prices + quantity tiers + SURFACE GEOMETRY.
const PROMPT = `You are reading screenshots of a print-shop PRODUCT page (top = buy panel with
price/options/quantity; others = the Specifications / "Specs & Templates" / product-details section).
Return ONLY JSON:
{"isProduct":true,"title":string,"category":string,"price":number,
 "options":[{"name":string,"values":[{"label":string,"priceDelta":number,
    "dimensions":{"width":number,"height":number,"unit":"in"|"mm"|"cm"|"ft"}|null}]}],
 "quantities":[{"quantity":number,"totalPrice":number,"perUnit":number}],
 "surface":{"unit":"in"|"mm"|"cm"|"ft","width":number,"height":number,
    "folded":boolean,"foldOrientation":"vertical"|"horizontal"|null,
    "noPrintNote":string|null},
 "material":string|null,"specsText":string}
Rules:
- Capture EVERY option group (Paper/Stock/Thickness, Size/Format, Corners, Finish, Sides, Material, Shape, Orientation…) and EVERY value with its price delta (0 / + / -).
- For any Size/Format/Dimensions value, fill "dimensions" with the finished (flat) size from the specs; else null.
- "surface" = the product's default/primary finished size (numbers only). Set "folded":true only if it is a folded product (folded card/brochure) and give foldOrientation. Set "noPrintNote" if the specs mention a pole pocket, grommet margin, hem, or no-print/keep-clear zone; else null.
- "specsText" = a short copy of the raw dimension/spec lines you used (for auditing).
- Read ALL quantity tiers with total + per-unit price. If NOT a single configurable product, return {"isProduct":false}.`;

const rnd = (a, b) => Math.floor(a + Math.random() * (b - a));
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const jitter = () => sleep(rnd(1300, 3400));
const log = (...a) => console.log(new Date().toISOString().slice(11, 19), ...a);
const sameHost = (u) => { try { return new URL(u).hostname.endsWith('vistaprint.com'); } catch { return false; } };
const looksBlocked = (h) =>
    ['just a moment', 'verify you are human', 'captcha', 'attention required', 'pardon our interruption'].some((s) =>
        h.toLowerCase().includes(s));

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
        try { const bt = page.getByRole('button', { name: re }).first(); if ((await bt.count()) && (await bt.isVisible())) { await bt.click({ timeout: 2000 }); return; } } catch {}
    }
}

async function waitForClearance(page) {
    for (let i = 0; i < 60; i++) {
        const title = (await page.title()).toLowerCase();
        if (!title.includes('just a moment') && !looksBlocked(await page.content())) return true;
        if (!HEADLESS && i === 0) log('   🔐 Cloudflare challenge — solve it in the window; waiting up to 3 min…');
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

// Best-effort: reveal the specifications / dimensions section so it renders for capture.
async function openSpecs(page) {
    const labels = [/specification/i, /specs? &? ?(and)? ?templates?/i, /product details/i, /dimensions/i, /size (details|guide)/i];
    for (const re of labels) {
        try {
            const el = page.getByText(re).first();
            if (await el.count()) {
                await el.scrollIntoViewIfNeeded({ timeout: 2500 });
                try { await el.click({ timeout: 1500 }); } catch {} // may be an accordion
                await sleep(700);
                return true;
            }
        } catch {}
    }
    return false;
}

async function parseWithGemini(images) {
    if (!KEY) return { isProduct: false, error: 'no GEMINI_API_KEY' };
    try {
        const parts = [{ text: PROMPT }, ...images.map((b) => ({ inlineData: { mimeType: 'image/png', data: b.toString('base64') } }))];
        const r = await fetch(`https://generativelanguage.googleapis.com/v1beta/models/${MODEL}:generateContent?key=${KEY}`, {
            method: 'POST',
            headers: { 'content-type': 'application/json' },
            body: JSON.stringify({ contents: [{ parts }], generationConfig: { responseMimeType: 'application/json' } }),
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
    await mkdir(SEED_DIR, { recursive: true });
    await mkdir(USERDATA, { recursive: true });
    if (!KEY) log('⚠️  GEMINI_API_KEY not found (env or backend/.env) — pages visited but not parsed.');

    // resume from previous run
    let products = [];
    try { products = JSON.parse(await readFile(OUT, 'utf8')); } catch {}
    const doneUrls = new Set(products.map((p) => p.url));
    const counts = {};
    for (const p of products) counts[p.ourCategory] = (counts[p.ourCategory] || 0) + 1;
    if (products.length) log(`↻ resuming: ${products.length} products already captured ${JSON.stringify(counts)}`);

    const save = () => writeFile(OUT, JSON.stringify(products, null, 2));

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
    const seen = new Set(doneUrls);
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

    const capped = (c) => (counts[c] || 0) >= PER_CAT;
    let attempts = 0;

    while (queue.length && attempts < MAX_ATTEMPTS && products.length < MAX_PRODUCTS) {
        const url = queue.shift();
        const guess = catOf(url);
        if (guess !== 'other' && capped(guess) && new URL(url).pathname.split('/').filter(Boolean).length >= 2) continue;
        attempts++;
        try {
            await page.goto(url, { waitUntil: 'domcontentloaded' });
            await dismissConsent(page);
            await jitter();
            if (!(await waitForClearance(page))) { log(`[${attempts}] blocked ${url}`); await sleep(rnd(8000, 15000)); continue; }

            const text = await page.evaluate(() => document.body.innerText);
            const productish = (await page.locator('select, [data-testid*="quantity" i]').count()) > 0
                || /add to cart|select quantity|paper stock|paper thickness/i.test(text);
            if (!productish) { enqueue(await collectLinks(page)); log(`[${attempts}] browse ${url} (queue ${queue.length})`); await jitter(); continue; }

            const slug = (new URL(url).pathname.split('/').filter(Boolean).pop() || 'product').slice(0, 40);
            const n = String(products.length + 1).padStart(3, '0');
            const shots = [];
            shots.push(await page.screenshot({ path: path.join(SHOTS, `${n}-${slug}-buy.png`), fullPage: false }));
            // reveal + capture the specifications section for surface geometry
            await openSpecs(page);
            shots.push(await page.screenshot({ path: path.join(SHOTS, `${n}-${slug}-specs.png`), fullPage: false }));

            const parsed = await parseWithGemini(shots);
            if (parsed?.isProduct && (parsed.quantities || []).length) {
                const cat = catOf(`${url} ${parsed.title} ${parsed.category}`);
                if (cat !== 'other' && capped(cat)) { log(`[${attempts}] skip (cap ${cat}) ${parsed.title}`); continue; }
                counts[cat] = (counts[cat] || 0) + 1;
                products.push({ ourCategory: cat, url, ...parsed, screenshots: [`${n}-${slug}-buy.png`, `${n}-${slug}-specs.png`] });
                await save();
                log(`[${attempts}] ✅ ${parsed.title} [${cat}] ${(parsed.quantities || []).length} tiers · surface=${parsed.surface ? `${parsed.surface.width}x${parsed.surface.height}${parsed.surface.unit}` : '?'} (total ${products.length}/${MAX_PRODUCTS})`);
                // human-like cool-down between products to pace the whole run
                await sleep(rnd(Math.round(PACE_MS * 0.6), Math.round(PACE_MS * 1.4)));
                if (Math.random() < 0.1) { log('   ☕ longer pause…'); await sleep(rnd(60_000, 180_000)); }
            } else {
                enqueue(await collectLinks(page));
                log(`[${attempts}] not-product ${url}`);
                await jitter();
            }
        } catch (e) { log(`[${attempts}] error ${url}: ${e.message}`); await sleep(rnd(5000, 12000)); }
    }

    await ctx.close();
    await save();
    log(`\nDone. ${products.length} products ${JSON.stringify(counts)} -> ${path.relative(ROOT, OUT)}`);
}

main().catch((e) => { console.error('FATAL', e); process.exit(1); });
