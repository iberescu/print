// Diagnose the pqsg gallery step: run one logo through the prod funnel and log
// the /pqsg/status polling + every widget request to the third-party engine.
import { chromium } from '@playwright/test';
import { fileURLToPath } from 'url';

const base = process.env.APP_URL || 'https://runmyprint.com';
const logo = fileURLToPath(new URL('../e2e/fixtures/logos/logo-01.png', import.meta.url));

const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });

page.on('console', (m) => { if (m.type() !== 'debug') console.log(`[console:${m.type()}]`, m.text().slice(0, 300)); });
page.on('response', async (r) => {
    const u = r.url();
    if (/\/pqsg\/status\//.test(u) || /pqsmartgenerator/i.test(u) || /cloudlab-internal/i.test(u)) {
        let body = '';
        try { body = (await r.text()).slice(0, 500); } catch (e) { body = `<unreadable: ${e.message.slice(0, 80)}>`; }
        console.log(`[${r.status()}] ${u.slice(0, 160)}\n    ${body.replace(/\n/g, ' ')}`);
    }
});
page.on('requestfailed', (r) => {
    const u = r.url();
    if (/pqsmartgenerator|cloudlab-internal|pqsg/i.test(u)) console.log(`[FAILED] ${u.slice(0, 160)} — ${r.failure()?.errorText}`);
});

await page.goto(`${base}/design/matte-business-cards?test=1`);
await page.waitForFunction(() => window.__rmpCanvas?.getObjects().some((o) => o.rmpRole === 'logo'));

if (process.env.MODE === 'website') {
    // website-only brand: the engine can fetch a public site even when our host
    // (localhost) is unreachable for it — lets the widget start locally.
    await page.evaluate(() => {
        const c = window.__rmpCanvas;
        c.getObjects().find((o) => o.rmpRole === 'url')?.set('text', 'anthropic.com');
        c.requestRenderAll();
    });
} else {
    const [chooser] = await Promise.all([
        page.waitForEvent('filechooser'),
        page.getByRole('button', { name: /upload image/i }).click(),
    ]);
    await chooser.setFiles(logo);
    await page.waitForFunction(() => window.__rmpCanvas.getObjects()
        .filter((o) => o.rmpRole === 'logo' && !(o.getSrc?.() || '').includes('logo-placeholder')).length === 1);
}

await page.getByRole('button', { name: /^review/i }).click();
await page.waitForURL('**/review');
await page.waitForTimeout(10000);
await page.getByRole('checkbox').first().check();
await page.getByRole('button', { name: /add to cart/i }).click();
await page.waitForURL('**/upsell');
await page.waitForTimeout(10000);
await Promise.all([
    page.waitForResponse((r) => /\/upsell\/next\b/.test(r.url())),
    page.getByRole('button', { name: /continue/i }).last().click(),
]);
console.log('--- on pqsg step, watching for 90s ---');
await page.waitForTimeout(90000);

const widgetState = await page.evaluate(() => {
    const el = document.getElementById('pqsg-widget');
    return {
        exists: !!el,
        uuidAttr: el?.getAttribute('uuid') || null,
        hasStart: !!el && typeof el.start === 'function',
        childCount: el ? el.children.length : 0,
        html: el ? el.innerHTML.slice(0, 400) : null,
        shadow: el?.shadowRoot ? el.shadowRoot.innerHTML.slice(0, 400) : null,
        scriptLoaded: !!document.querySelector('script[data-pqsg]'),
        customElementDefined: !!customElements.get('pq-smart-generator-widget'),
    };
});
console.log('widget state:', JSON.stringify(widgetState, null, 2));
await browser.close();
