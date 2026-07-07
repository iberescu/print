import { chromium } from 'playwright';

const BASE = process.env.APP_URL || 'http://localhost:8080';
const browser = await chromium.launch();
const ctx = await browser.newContext({ acceptDownloads: true });
const page = await ctx.newPage();

let captureResp = null;
page.on('response', async (r) => {
    if (r.url().includes('/qr/capture')) captureResp = { status: r.status(), body: await r.json().catch(() => null) };
});

await page.goto(BASE + '/qr-code-generator', { waitUntil: 'networkidle' });
await page.fill('input[placeholder="yourcompany.com"]', 'https://www.metmuseum.org');
await page.waitForSelector('img[src*="/qr/image"]');

const dl = page.waitForEvent('download', { timeout: 15000 }).catch(() => null);
await page.click('button:has-text("Download SVG")');
const download = await dl;
console.log('download:', download ? download.suggestedFilename() : 'NONE');

await page.waitForSelector('#qr-gallery', { timeout: 10000 });
console.log('capture response:', JSON.stringify(captureResp));
console.log('gallery visible:', await page.locator('#qr-gallery').isVisible());
console.log('gallery heading:', await page.locator('#qr-gallery h2').textContent());
await browser.close();
