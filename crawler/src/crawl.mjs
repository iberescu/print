// Chromium crawler: discover top-N Vistaprint products, screenshot each buy panel,
// and parse options + per-quantity prices with Gemini vision (robust vs. obfuscated markup).
//
// Run:  $env:GEMINI_API_KEY='...'; node src/crawl.mjs
import { chromium } from 'playwright';
import { mkdir, writeFile } from 'node:fs/promises';
import path from 'node:path';

const ROOT = path.resolve('..');
const DATA = path.join(ROOT, 'research', 'data');
const SHOTS = path.join(DATA, 'shots');
const BASE = 'https://www.vistaprint.com';
const KEY = process.env.GEMINI_API_KEY;
const MODEL = 'gemini-3.5-flash';
const MAX = Number(process.env.MAX_PRODUCTS || 20);
const MAX_ATTEMPTS = Number(process.env.MAX_ATTEMPTS || 45);
const UA =
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';

const BLOCK =
    /\/(account|cart|help|sign-?in|login|customer|orders?|gallery|about|careers|reviews|contact|blog|ideas|incentives|promotions|wallet|saved|my-|legal|privacy|terms|sitemap|store-locator|design-services|customer-care)\b/i;

const PROMPT = `You are looking at a screenshot of a single print-shop PRODUCT page (or possibly a category/listing page).
Return ONLY JSON. If it is a single configurable product page, return:
{"isProduct":true,"title":string,"category":string,
 "price":number (the currently displayed total price),
 "options":[{"name":string,"values":[{"label":string,"priceDelta":number}]}],
 "quantities":[{"quantity":number,"totalPrice":number,"perUnit":number}]}
Capture option groups like Paper Stock, Paper Thickness, Corners, Size, Finish, Sides, Material, Shape.
For quantities, read the quantity selector/dropdown (often shows "50 ($0.20/unit)" or "100 ($15.99)").
priceDelta may be 0, positive (+$x) or negative (-$x). If it is NOT a single product page, return {"isProduct":false}.`;

const log = (...a) => console.log(...a);
const sameHost = (u) => {
    try {
        return new URL(u).hostname.endsWith('vistaprint.com');
    } catch {
        return false;
    }
};

function looksBlocked(h) {
    h = h.toLowerCase();
    return ['just a moment', 'access denied', 'are you a human', 'captcha', 'pardon our interruption'].some((s) =>
        h.includes(s)
    );
}

async function dismissConsent(page) {
    // OneTrust + generic accept buttons
    for (const sel of ['#onetrust-accept-btn-handler', 'button#truste-consent-button']) {
        try {
            const el = page.locator(sel).first();
            if (await el.count()) {
                await el.click({ timeout: 2500 });
                await page.waitForTimeout(400);
                return;
            }
        } catch {}
    }
    for (const re of [/accept all/i, /^accept$/i, /agree/i, /got it/i]) {
        try {
            const b = page.getByRole('button', { name: re }).first();
            if ((await b.count()) && (await b.isVisible())) {
                await b.click({ timeout: 2000 });
                await page.waitForTimeout(400);
                return;
            }
        } catch {}
    }
}

async function collectLinks(page) {
    const links = await page.$$eval('a[href]', (as) =>
        as.map((a) => ({ href: a.href, text: (a.textContent || '').trim() }))
    );
    const out = [];
    for (const l of links) {
        if (!sameHost(l.href)) continue;
        let u;
        try {
            u = new URL(l.href);
        } catch {
            continue;
        }
        const segs = u.pathname.split('/').filter(Boolean);
        if (segs.length < 1 || segs.length > 3) continue;
        if (BLOCK.test(u.pathname)) continue;
        out.push(u.origin + u.pathname);
    }
    return out;
}

async function parseWithGemini(buf) {
    if (!KEY) return { isProduct: false, error: 'no GEMINI_API_KEY' };
    const body = {
        contents: [{ parts: [{ text: PROMPT }, { inlineData: { mimeType: 'image/png', data: buf.toString('base64') } }] }],
        generationConfig: { responseMimeType: 'application/json' },
    };
    try {
        const r = await fetch(`https://generativelanguage.googleapis.com/v1beta/models/${MODEL}:generateContent?key=${KEY}`, {
            method: 'POST',
            headers: { 'content-type': 'application/json' },
            body: JSON.stringify(body),
        });
        const j = await r.json();
        const text = (j?.candidates?.[0]?.content?.parts || []).map((p) => p.text || '').join('') || '{}';
        return JSON.parse(text);
    } catch (e) {
        return { isProduct: false, error: String(e.message || e) };
    }
}

async function main() {
    await mkdir(SHOTS, { recursive: true });
    if (!KEY) log('⚠️  GEMINI_API_KEY not set — will crawl + screenshot but skip parsing.');

    const browser = await chromium.launch({ headless: true });
    const ctx = await browser.newContext({
        userAgent: UA,
        viewport: { width: 1440, height: 1600 },
        locale: 'en-US',
        extraHTTPHeaders: { 'Accept-Language': 'en-US,en;q=0.9' },
    });
    const page = await ctx.newPage();
    page.setDefaultTimeout(45000);

    const queue = [];
    const seen = new Set();
    const enqueue = (urls) => urls.forEach((u) => !seen.has(u) && (seen.add(u), queue.push(u)));

    // Seed from homepage + likely category landings
    for (const seed of [`${BASE}/`, `${BASE}/business-cards`, `${BASE}/marketing-materials`, `${BASE}/signage`]) {
        try {
            await page.goto(seed, { waitUntil: 'domcontentloaded' });
            await dismissConsent(page);
            await page.waitForTimeout(2500);
            if (looksBlocked(await page.content())) {
                log(`🚧 blocked at seed ${seed}`);
                continue;
            }
            const found = await collectLinks(page);
            enqueue(found);
            log(`seed ${seed} -> ${found.length} links (queue ${queue.length})`);
        } catch (e) {
            log(`seed fail ${seed}: ${e.message}`);
        }
    }

    const products = [];
    let attempts = 0;

    while (queue.length && products.length < MAX && attempts < MAX_ATTEMPTS) {
        const url = queue.shift();
        attempts++;
        try {
            await page.goto(url, { waitUntil: 'domcontentloaded' });
            await dismissConsent(page);
            await page.waitForTimeout(2200);

            const html = await page.content();
            if (looksBlocked(html)) {
                log(`[${attempts}] blocked  ${url}`);
                continue;
            }
            const text = await page.evaluate(() => document.body.innerText);
            const hasSelect = (await page.locator('select').count()) > 0;
            const productish = hasSelect || /add to cart|quantity|paper stock|paper thickness/i.test(text);

            if (!productish) {
                // dig into category pages for product links
                enqueue(await collectLinks(page));
                log(`[${attempts}] browse   ${url}  (+links, queue ${queue.length})`);
                continue;
            }

            const slug = (new URL(url).pathname.split('/').filter(Boolean).pop() || 'product').slice(0, 40);
            const shot = path.join(SHOTS, `${String(products.length + 1).padStart(2, '0')}-${slug}.png`);
            const buf = await page.screenshot({ path: shot, fullPage: false });

            const parsed = await parseWithGemini(buf);
            if (parsed?.isProduct) {
                products.push({ url, title: parsed.title || (await page.title()), ...parsed, screenshot: path.relative(ROOT, shot) });
                log(`[${attempts}] ✅ PRODUCT ${products.length}/${MAX}  ${parsed.title || url}  (${(parsed.quantities || []).length} qty tiers)`);
            } else {
                enqueue(await collectLinks(page));
                log(`[${attempts}] not-product ${url}`);
            }
        } catch (e) {
            log(`[${attempts}] error ${url}: ${e.message}`);
        }
    }

    await browser.close();

    // Write outputs
    await writeFile(path.join(DATA, 'vistaprint-products.json'), JSON.stringify(products, null, 2));

    const rows = [['title', 'category', 'quantity', 'totalPrice', 'perUnit', 'url']];
    for (const p of products) {
        for (const q of p.quantities || []) {
            rows.push([p.title, p.category || '', q.quantity, q.totalPrice, q.perUnit ?? '', p.url]);
        }
    }
    const csv = rows.map((r) => r.map((c) => `"${String(c ?? '').replace(/"/g, '""')}"`).join(',')).join('\n');
    await writeFile(path.join(DATA, 'vistaprint-prices.csv'), csv);

    log(`\nDone. ${products.length} products, ${rows.length - 1} price rows.`);
    log(`-> research/data/vistaprint-products.json`);
    log(`-> research/data/vistaprint-prices.csv`);
}

main().catch((e) => {
    console.error('FATAL', e);
    process.exit(1);
});
