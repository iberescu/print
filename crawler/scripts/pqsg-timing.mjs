// Measure the pqsg pipeline: when does the capture reach the third-party
// engine, and how long until logo mockups are visible to the shopper?
// Simulates a real user: EDIT_MS designing after the logo upload, 10 s on
// review, 10 s on accessories, then sits on the gallery until images appear.
//   APP_URL=https://runmyprint.com node scripts/pqsg-timing.mjs
import { chromium } from '@playwright/test';
import { fileURLToPath } from 'url';

const base = process.env.APP_URL || 'https://runmyprint.com';
const logoFile = process.env.LOGO || fileURLToPath(new URL('../e2e/fixtures/logos/logo-03.png', import.meta.url));
const EDIT_MS = Number(process.env.EDIT_MS ?? 15000);   // time "designing" after the upload
const t0 = Date.now();
const T = {};                                            // event → ms since t0
const mark = (k) => { if (T[k] === undefined) { T[k] = Date.now() - t0; console.log(`  [+${(T[k] / 1000).toFixed(1)}s] ${k}`); } };

const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });

let uuid = null;
page.on('response', async (r) => {
    const u = r.url();
    if (/\/pqsg\/upload\b/.test(u) && r.status() === 200) mark('capture_upload_response');
    if (/\/pqsg\/status\//.test(u)) {
        try { const j = await r.json(); if (j?.uuid && !uuid) { uuid = j.uuid; mark('uuid_known_to_page'); } } catch (e) { /* ignore */ }
    }
});

await page.goto(`${base}/design/matte-business-cards?test=1`);
await page.waitForFunction(() => window.__rmpCanvas?.getObjects().some((o) => o.rmpRole === 'logo'));
mark('editor_ready');

// upload the logo through the replace popup (the primary flow now)
const pos = await page.evaluate(() => {
    const c = window.__rmpCanvas;
    const o = c.getObjects().find((x) => x.rmpRole === 'logo');
    const p = o.getCenterPoint(); const z = c.getZoom();
    return { x: p.x * z, y: p.y * z };
});
const box = await page.locator('canvas.upper-canvas').boundingBox();
await page.mouse.click(box.x + pos.x, box.y + pos.y);
const [chooser] = await Promise.all([
    page.waitForEvent('filechooser'),
    page.getByRole('button', { name: /upload your logo|replace logo/i }).click(),
]);
await chooser.setFiles(logoFile);
await page.waitForFunction(() => {
    const os = window.__rmpCanvas.getObjects().filter((o) => o.rmpRole === 'logo');
    return os.length === 1 && !(os[0].getSrc?.() || '').includes('logo-placeholder');
});
mark('logo_on_canvas');

await page.waitForTimeout(EDIT_MS); // user keeps designing
await page.getByRole('button', { name: /^review/i }).click();
await page.waitForURL('**/review');
mark('review_page');
await page.waitForTimeout(10000);
await page.getByRole('checkbox').first().check();
await page.getByRole('button', { name: /add to cart/i }).click();
await page.waitForURL('**/upsell');
mark('accessories_page');
await page.waitForTimeout(10000);
await Promise.all([
    page.waitForResponse((r) => /\/upsell\/next\b/.test(r.url())),
    page.getByRole('button', { name: /continue/i }).last().click(),
]);
await page.getByRole('heading', { name: /your logo on more products/i }).waitFor();
mark('gallery_page');

// first visible mockup + count growth until stable
await page.waitForFunction(() => {
    const el = document.getElementById('pqsg-widget');
    return el?.shadowRoot?.querySelectorAll('img').length > 0;
}, undefined, { timeout: 300000 });
mark('first_image_visible');
let count = 0;
for (let quiet = 0; quiet < 3;) {
    await page.waitForTimeout(5000);
    const now = await page.evaluate(() => document.getElementById('pqsg-widget')?.shadowRoot?.querySelectorAll('img').length || 0);
    quiet = now === count ? quiet + 1 : 0;
    count = now;
}
mark('images_stable');
console.log(`  gallery images: ${count}`);

// server-side truth from the engine's widget endpoint
const server = await page.evaluate(async ({ id }) => {
    const el = document.getElementById('pqsg-widget');
    const apiBase = el?.getAttribute('api-base');
    const r = await fetch(`${apiBase}/capture/${id}/widget`, { headers: { Accept: 'application/json' } });
    const j = await r.json();
    return { received_at: j.received_at, started_at: j.started_at, completed_at: j.completed_at, images: j.counts?.images, tasks: j.counts?.tasks_by_status };
}, { id: uuid });
console.log('\nengine timeline (server clock):', JSON.stringify(server, null, 2));

console.log('\nclient timeline (s since script start):', JSON.stringify(Object.fromEntries(Object.entries(T).map(([k, v]) => [k, +(v / 1000).toFixed(1)])), null, 2));
console.log(`\nKEY METRICS
  logo upload → capture handed to engine: ${T.capture_upload_response !== undefined ? ((T.capture_upload_response - T.logo_on_canvas) / 1000).toFixed(1) + 's (at upload)' : 'NOT at upload — capture goes out at Review (+' + ((T.review_page - T.logo_on_canvas) / 1000).toFixed(1) + 's after upload)'}
  gallery arrival → first mockup visible: ${((T.first_image_visible - T.gallery_page) / 1000).toFixed(1)}s   <-- what the shopper waits
  gallery arrival → gallery stable:       ${((T.images_stable - T.gallery_page) / 1000).toFixed(1)}s`);
await browser.close();
