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
import { unzipSync } from 'fflate';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(HERE, '..', '..');
const DATA = path.join(ROOT, 'research', 'data');
const SHOTS = path.join(DATA, 'shots-100');
const TPL = path.join(DATA, 'templates-100'); // downloaded SVG print templates (for auditing/re-parsing)
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
// Override directly with PACE_MS (e.g. PACE_MS=2000 for a quick selector test).
const PACE_MS = process.env.PACE_MS
    ? Number(process.env.PACE_MS)
    : Math.max(20_000, Math.round((TARGET_HOURS * 3600_000) / MAX_PRODUCTS));
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
const PROMPT = `You are reading a print-shop PRODUCT page: screenshots (top = buy panel with
price/options/quantity; next = the Specifications / "Specs & Templates" / product-details section)
AND, when present, "TEMPLATE DIAGRAM DATA" — the extracted labels + geometry of the spec template
SVG (the little diagram that shows trim / bleed / safety / fold / no-print lines with measurements).
Return ONLY JSON:
{"isProduct":true,"title":string,"category":string,"price":number,
 "options":[{"name":string,"values":[{"label":string,"priceDelta":number,
    "dimensions":{"width":number,"height":number,"unit":"in"|"mm"|"cm"|"ft"}|null}]}],
 "quantities":[{"quantity":number,"totalPrice":number,"perUnit":number}],
 "surface":{"unit":"in"|"mm"|"cm"|"ft","width":number,"height":number,
    "bleed":number|null,"safety":number|null,
    "fold":[{"orientation":"vertical"|"horizontal","position":number}]|null,
    "noPrint":[{"label":string,"x":number,"y":number,"w":number,"h":number}]|null,
    "folded":boolean,"noPrintNote":string|null},
 "material":string|null,"specsText":string}
Rules:
- Capture EVERY option group (Paper/Stock/Thickness, Size/Format, Corners, Finish, Sides, Material, Shape, Orientation…) and EVERY value with its price delta (0 / + / -).
- For any Size/Format value, fill "dimensions" with the finished (flat) size; else null.
- "surface" = the product's default/primary finished size (numbers only, same unit throughout).
- Use the TEMPLATE DIAGRAM DATA (and the diagram in the screenshot) to fill REAL "bleed" and "safety" margins (the distance from trim to bleed / safe line), the "fold" line positions (measured from the left/top trim edge, in the surface unit), and any "noPrint"/keep-clear rectangles (x,y,w,h in the surface unit from the top-left trim corner). If the diagram doesn't show one of these, use null.
- "folded"=true only for folded products; "noPrintNote"= the raw pocket/grommet/hem note if any.
- "specsText" = a short copy of the raw dimension/spec lines used (for auditing).
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

// Extract the spec/template diagram SVG(s). Their <text> labels + rect/line geometry
// are the precise source for bleed / safety / fold / no-print measurements.
async function extractSpecSvg(page) {
    try {
        return await page.evaluate(() => {
            const isDiagram = (s) => {
                const t = (s.textContent || '').toLowerCase();
                return /bleed|trim|safe|fold|margin|\bmm\b|\bcm\b|inch|"/.test(t) || s.querySelectorAll('text').length >= 3;
            };
            const num = (el, a) => Number(el.getAttribute(a)) || 0;
            return [...document.querySelectorAll('svg')].filter(isDiagram).slice(0, 3).map((s) => ({
                viewBox: s.getAttribute('viewBox') || null,
                width: s.getAttribute('width') || null,
                height: s.getAttribute('height') || null,
                texts: [...s.querySelectorAll('text, tspan')].map((t) => (t.textContent || '').trim()).filter(Boolean).slice(0, 40),
                rects: [...s.querySelectorAll('rect')].slice(0, 12).map((r) => ({ x: num(r, 'x'), y: num(r, 'y'), w: num(r, 'width'), h: num(r, 'height'), dash: r.getAttribute('stroke-dasharray') || null })),
                dashed: s.querySelectorAll('[stroke-dasharray]').length,
                lines: s.querySelectorAll('line, polyline').length,
            }));
        });
    } catch { return []; }
}

async function parseWithGemini(images, extraText) {
    if (!KEY) return { isProduct: false, error: 'no GEMINI_API_KEY' };
    try {
        const parts = [{ text: PROMPT }];
        if (extraText) parts.push({ text: extraText });
        for (const b of images) parts.push({ inlineData: { mimeType: 'image/png', data: b.toString('base64') } });
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

// Option values live behind custom listbox buttons (not native <select>). Open each
// and read all its values from the DOM. Call BEFORE opening the specs drawer so only
// the buy-panel option dropdowns are present (keeps order aligned with the screenshot).
async function expandOptions(page) {
    const out = [];
    try {
        const triggers = await page.$$('button[aria-haspopup="listbox"]');
        for (const t of triggers) {
            try {
                await t.scrollIntoViewIfNeeded({ timeout: 1500 });
                await t.click({ timeout: 2000 });
                await sleep(450);
                const values = await page.$$eval('[role="option"]', (els) => els.map((e) => (e.textContent || '').trim()).filter(Boolean));
                await page.keyboard.press('Escape').catch(() => {});
                await sleep(150);
                if (values.length) out.push({ values: [...new Set(values)] });
            } catch {}
        }
    } catch {}
    return out;
}

// Select the first value of every option dropdown — Vistaprint only generates the
// print template (the specs SVG) once all required options are chosen.
async function selectAllOptions(page) {
    try {
        const triggers = await page.$$('button[aria-haspopup="listbox"]');
        for (const t of triggers) {
            try {
                await t.click({ timeout: 2000 });
                await sleep(400);
                const first = page.locator('[role="option"]').first();
                if (await first.count()) await first.click({ timeout: 2000 });
                await sleep(400);
            } catch {}
        }
    } catch {}
}

// Download the generated SVG print template (Vistaprint serves it as a .zip bundle
// that includes the guidelines PDF). Returns the raw bytes — best-effort, null if none.
async function grabTemplateSvg(page) {
    for (const loc of [page.getByRole('link', { name: /^\s*SVG/i }), page.getByText(/^SVG$/i), page.locator('a:has-text("SVG"), button:has-text("SVG")')]) {
        try {
            const el = loc.first();
            if (!(await el.count())) continue;
            const [dl] = await Promise.all([
                page.waitForEvent('download', { timeout: 15000 }).catch(() => null),
                el.click({ timeout: 3000 }).catch(() => {}),
            ]);
            if (dl) {
                const p = await dl.path();
                if (p) return { buf: await readFile(p), name: dl.suggestedFilename() || '' }; // BINARY (no utf8!)
            }
        } catch {}
    }
    return null;
}

// The download is usually a .zip (guidelines PDF + template SVG). Pull the SVG text out.
function svgFromDownload(buf) {
    if (!buf || !buf.length) return null;
    if (buf[0] === 0x50 && buf[1] === 0x4b) { // 'PK' -> zip
        try {
            const files = unzipSync(new Uint8Array(buf));
            const key = Object.keys(files).find((k) => /\.svg$/i.test(k));
            if (key) return new TextDecoder().decode(files[key]);
        } catch {}
        return null;
    }
    const text = buf.toString('utf8');
    return /<svg[\s>]/i.test(text) ? text : null;
}

// Parse a Vistaprint SVG print template into precise surface geometry (mm). The
// template has semantic layers (<g id="Bleed|Trim|Safety|Fold|Cut">) whose <path>s
// are rectangles/lines; viewBox is in points, physical size in the width/height attrs.
function parseSvgTemplate(svg) {
    if (!svg || svg.length > 4_000_000) return null;
    const vbm = svg.match(/viewBox=["']([^"']+)["']/);
    if (!vbm) return null;
    const vbW = vbm[1].trim().split(/[\s,]+/).map(Number)[2];
    if (!vbW) return null;
    const toMm = (v, u) => (u === 'cm' ? v * 10 : u === 'in' ? v * 25.4 : u === 'pt' ? (v * 25.4) / 72 : v);
    const pm = svg.match(/\bwidth=["']([\d.]+)\s*(cm|mm|in|pt)["']/i);
    const mmPerUnit = pm ? toMm(parseFloat(pm[1]), pm[2].toLowerCase()) / vbW : 25.4 / 72; // fallback: viewBox in pt
    const mm = (n) => +(n * mmPerUnit).toFixed(2);

    // bounding box of the coordinate pairs inside a named <g> layer
    const box = (name) => {
        const m = svg.match(new RegExp(`<g[^>]*(?:id|class)=["'][^"']*\\b${name}\\b[^"']*["'][^>]*>([\\s\\S]*?)</g>`, 'i'));
        if (!m) return null;
        // coordinates come ONLY from path d="…" attrs (avoid stroke-dasharray/stroke-width numbers)
        const ds = [...m[1].matchAll(/\bd=["']([^"']+)["']/g)].map((x) => x[1]).join(' ');
        const pts = [...ds.matchAll(/([-\d.]+)\s*[, ]\s*([-\d.]+)/g)].map((x) => [+x[1], +x[2]]).filter((p) => isFinite(p[0]) && isFinite(p[1]));
        if (!pts.length) return null;
        const xs = pts.map((p) => p[0]); const ys = pts.map((p) => p[1]);
        return { minX: Math.min(...xs), maxX: Math.max(...xs), minY: Math.min(...ys), maxY: Math.max(...ys) };
    };

    const trim = box('Trim');
    if (!trim) return null;
    const bleed = box('Bleed'); const safe = box('Safety') || box('Safe'); const fold = box('Fold'); const cut = box('Cut');
    const geo = { unit: 'mm', width: mm(trim.maxX - trim.minX), height: mm(trim.maxY - trim.minY) };
    if (bleed) geo.bleed = Math.max(0, mm(trim.minX - bleed.minX));
    if (safe) geo.safety = Math.max(0, mm(safe.minX - trim.minX));
    if (fold) {
        const vertical = (fold.maxX - fold.minX) <= (fold.maxY - fold.minY);
        geo.fold = [{ orientation: vertical ? 'vertical' : 'horizontal', position: mm((vertical ? (fold.minX + fold.maxX) / 2 : (fold.minY + fold.maxY) / 2) - (vertical ? trim.minX : trim.minY)) }];
    }
    if (cut) geo.hasCut = true; // die-cut / custom shape present
    return geo;
}

// Overwrite the product's surface geometry with the precise SVG-derived values (mm).
function applySvgGeometry(parsed, geo) {
    if (!geo) return;
    const s = (parsed.surface = parsed.surface || {});
    s.unit = 'mm';
    s.width = geo.width;
    s.height = geo.height;
    if (geo.bleed != null) s.bleed = geo.bleed;
    if (geo.safety != null) s.safety = geo.safety;
    if (geo.fold) { s.fold = geo.fold; s.folded = true; }
}

// DOM option values are authoritative; keep Gemini's names + any per-value deltas.
function mergeOptions(parsed, dom) {
    if (!dom?.length) return;
    const opts = parsed.options || [];
    for (let i = 0; i < dom.length; i++) {
        const byLabel = Object.fromEntries(((opts[i]?.values) || []).map((v) => [String(v.label).toLowerCase(), v.priceDelta || 0]));
        parsed.options = parsed.options || [];
        parsed.options[i] = {
            name: opts[i]?.name || `Option ${i + 1}`,
            values: dom[i].values.map((l) => ({ label: l, priceDelta: byLabel[l.toLowerCase()] || 0 })),
        };
    }
}

async function main() {
    await loadKey();
    await mkdir(SHOTS, { recursive: true });
    await mkdir(TPL, { recursive: true });
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
            const domOptions = await expandOptions(page);          // full option values from the DOM (before specs opens)
            // open specs, select all options so the print template generates, then download + parse the SVG
            await openSpecs(page);
            await selectAllOptions(page);
            await sleep(2500);
            shots.push(await page.screenshot({ path: path.join(SHOTS, `${n}-${slug}-specs.png`), fullPage: false }));
            let svgText = null, rawDl = null;
            try { rawDl = await grabTemplateSvg(page); if (rawDl?.buf) svgText = svgFromDownload(rawDl.buf); } catch {}
            const geo = svgText ? parseSvgTemplate(svgText) : null;  // precise bleed/trim/safe/fold (mm), deterministic

            const parsed = await parseWithGemini(shots, '');         // prices/tiers/dims/option names from the screenshots
            if (parsed?.isProduct && (parsed.quantities || []).length) {
                const cat = catOf(`${url} ${parsed.title} ${parsed.category}`);
                if (cat !== 'other' && capped(cat)) { log(`[${attempts}] skip (cap ${cat}) ${parsed.title}`); continue; }
                counts[cat] = (counts[cat] || 0) + 1;
                mergeOptions(parsed, domOptions);                  // DOM values are authoritative
                applySvgGeometry(parsed, geo);                     // exact surface geometry from the template SVG
                if (rawDl?.buf) {
                    const ext = rawDl.buf[0] === 0x50 && rawDl.buf[1] === 0x4b ? 'zip' : 'svg';
                    try {
                        await writeFile(path.join(TPL, `${n}-${slug}.${ext}`), rawDl.buf);       // raw download (binary)
                        if (svgText && ext === 'zip') await writeFile(path.join(TPL, `${n}-${slug}.svg`), svgText); // extracted svg
                    } catch {}
                }
                products.push({ ourCategory: cat, url, ...parsed, surfaceSvg: geo, domOptionCount: domOptions.length, screenshots: [`${n}-${slug}-buy.png`, `${n}-${slug}-specs.png`] });
                await save();
                const nOpts = (parsed.options || []).reduce((a, o) => a + (o.values || []).length, 0);
                log(`[${attempts}] ✅ ${parsed.title} [${cat}] ${(parsed.quantities || []).length} tiers · ${(parsed.options || []).length} opts/${nOpts} vals · surface=${parsed.surface ? `${parsed.surface.width}x${parsed.surface.height}${parsed.surface.unit} b=${parsed.surface.bleed ?? '?'}` : '?'} · svg=${svgText ? 'Y' : 'n'} (total ${products.length}/${MAX_PRODUCTS})`);
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
