// Measure REAL per-option price deltas: Vistaprint shows absolute prices per
// configuration (not "+$X"), so screenshots can't capture up-charges. This pass
// clicks every option value and reads the selected quantity tier's price; the
// delta vs the option's first value is written back into the bundle
// (options[].values[].priceDelta), which catalog:import maps to price_delta.
//
// RESUMABLE: records are marked with deltasDone; re-run to continue. Writes the
// bundle after every product. Don't run while another crawler is writing it.
import { chromium } from 'playwright';
import { readFile, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(HERE, '..', '..');
const OUT = path.join(ROOT, 'backend', 'database', 'seed', 'vistaprint-100.json');
const USERDATA = path.resolve(HERE, '..', '.userdata');
const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';
const HEADLESS = process.env.HEADLESS === 'true';
const MAX_PRODUCTS = Number(process.env.MAX_PRODUCTS || 500);

const rnd = (a, b) => Math.floor(a + Math.random() * (b - a));
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const log = (...a) => console.log(new Date().toISOString().slice(11, 19), ...a);

function looksLikeQuantities(vals) {
    if (!vals.length) return false;
    const q = vals.filter((v) => /\$|\/\s*unit|savings|get a quote|^\s*\d[\d,]*\s*$/i.test(v)).length;
    return q >= Math.ceil(vals.length * 0.6);
}

// current price of the SELECTED quantity tier (radiogroup tier or dropdown trigger)
async function readPrice(page) {
    return await page.evaluate(() => {
        const money = (t) => { const m = (t || '').match(/\$\s*([\d,]+(?:\.\d{1,2})?)/); return m ? parseFloat(m[1].replace(/,/g, '')) : null; };
        const sel = document.querySelector('[role="radio"][aria-checked="true"]');
        if (sel) { const v = money(sel.textContent); if (v != null) return v; }
        for (const b of document.querySelectorAll('button[aria-haspopup="listbox"]')) {
            const t = (b.textContent || '').trim();
            if (/\$/.test(t) && /^\d/.test(t)) { const v = money(t); if (v != null) return v; }
        }
        const el = [...document.querySelectorAll('h2,h3,p,span,div')].find((e) => e.children.length === 0 && /^\$\s*[\d,]+(\.\d{1,2})?/.test((e.textContent || '').trim()));
        return el ? money(el.textContent) : null;
    });
}

async function openList(page, trigger) {
    try {
        await trigger.scrollIntoViewIfNeeded({ timeout: 1500 });
        await trigger.click({ timeout: 2000 });
        await sleep(450);
        return await page.$$('[role="option"]');
    } catch { return []; }
}

const bundle = JSON.parse(await readFile(OUT, 'utf8'));
const todo = bundle.filter((p) => !p.deltasDone && (p.options || []).some((o) => (o.values || []).length > 1));
log(`products needing deltas: ${todo.length} / ${bundle.length}`);

const ctx = await chromium.launchPersistentContext(USERDATA, {
    headless: HEADLESS, userAgent: UA, viewport: { width: 1440, height: 1600 },
    locale: 'en-US', timezoneId: 'America/New_York', args: ['--disable-blink-features=AutomationControlled'],
});
await ctx.addInitScript(() => Object.defineProperty(navigator, 'webdriver', { get: () => undefined }));
const page = ctx.pages()[0] || (await ctx.newPage());
page.setDefaultTimeout(40000);

let done = 0;
for (const rec of todo) {
    if (done >= MAX_PRODUCTS) break;
    try {
        await page.goto(rec.url, { waitUntil: 'domcontentloaded' });
        await sleep(rnd(2500, 4000));
        try { const c = page.locator('#onetrust-accept-btn-handler').first(); if (await c.count()) await c.click({ timeout: 2000 }); } catch {}
        if ((await page.title()).toLowerCase().includes('just a moment')) { log('🔐 solve Cloudflare in the window…'); for (let i = 0; i < 40 && (await page.title()).toLowerCase().includes('just a moment'); i++) await sleep(3000); }
        await page.waitForSelector('button[aria-haspopup="listbox"]', { timeout: 12000 }).catch(() => {});

        // option controls can hydrate late on some variant pages — nudge + retry
        let triggers = await page.$$('button[aria-haspopup="listbox"]');
        for (let retry = 0; retry < 2 && !triggers.length; retry++) {
            await page.mouse.wheel(0, 500);
            await sleep(3000);
            triggers = await page.$$('button[aria-haspopup="listbox"]');
        }
        const groups = []; // [{values:[labels], deltas:[numbers]}]
        let optionTriggers = 0; // listboxes that are real print options (not the quantity picker)
        for (const t of triggers) {
            let opts = await openList(page, t);
            if (!opts.length) continue;
            const labels = [];
            for (const o of opts) labels.push(((await o.textContent()) || '').trim());
            if (looksLikeQuantities(labels)) { await page.keyboard.press('Escape').catch(() => {}); await sleep(200); continue; }
            optionTriggers++;

            const deltas = new Array(labels.length).fill(0);
            // select first value -> baseline for this group
            try { await opts[0].click({ timeout: 2000 }); } catch { await page.keyboard.press('Escape').catch(() => {}); continue; }
            await sleep(rnd(1100, 1600));
            const base = await readPrice(page);
            for (let i = 1; i < labels.length && base != null; i++) {
                opts = await openList(page, t);
                if (opts.length <= i) break;
                try { await opts[i].click({ timeout: 2000 }); } catch { await page.keyboard.press('Escape').catch(() => {}); break; }
                await sleep(rnd(1100, 1600));
                const price = await readPrice(page);
                if (price != null) deltas[i] = +(price - base).toFixed(2);
            }
            // reset group back to its first value so groups don't compound
            opts = await openList(page, t);
            if (opts.length) { try { await opts[0].click({ timeout: 2000 }); } catch { await page.keyboard.press('Escape').catch(() => {}); } }
            await sleep(500);
            groups.push({ values: labels, deltas });
        }

        // match measured groups to the record's options by label overlap
        let applied = 0;
        for (const g of groups) {
            const gl = g.values.map((v) => v.toLowerCase());
            let best = null, score = 0;
            for (const o of rec.options || []) {
                const overlap = (o.values || []).filter((v) => gl.includes(String(v.label).toLowerCase())).length;
                if (overlap > score) { score = overlap; best = o; }
            }
            if (!best || !score) continue;
            for (const v of best.values) {
                const idx = gl.indexOf(String(v.label).toLowerCase());
                if (idx >= 0) { v.priceDelta = g.deltas[idx]; applied++; }
            }
        }
        // done when we measured something OR the page has no measurable option
        // dropdowns (options rendered as chips/links, or only the quantity picker)
        rec.deltasDone = groups.length > 0 || optionTriggers === 0;
        await writeFile(OUT, JSON.stringify(bundle, null, 2));
        done++;
        const nonZero = (rec.options || []).flatMap((o) => o.values || []).filter((v) => v.priceDelta).length;
        log(`${rec.deltasDone ? '✅' : '↻ retry-later'} ${rec.title}  groups=${groups.length} applied=${applied} nonZeroDeltas=${nonZero}  (${done}/${todo.length})`);
        await sleep(rnd(4000, 9000));
    } catch (e) {
        log(`error ${rec.title}: ${String(e.message).slice(0, 90)}`);
        await sleep(rnd(4000, 8000));
    }
}
await ctx.close();
log(`Done. ${done} products measured.`);
