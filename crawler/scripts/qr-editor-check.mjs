import { chromium } from 'playwright';

const BASE = process.env.APP_URL || 'http://localhost:8080';
const browser = await chromium.launch();
const page = await browser.newPage({ baseURL: BASE });

await page.goto('/design/standard-business-cards?test=1');
await page.waitForFunction(() => window.__rmpCanvas?.getObjects().length > 0, null, { timeout: 20000 });

// open the QR modal from the toolbar
await page.click('button:has-text("QR code")');
await page.waitForSelector('text=Add a QR code', { timeout: 5000 });
await page.fill('input[placeholder="yourcompany.com"]', 'runmyprint.com');
const before = await page.evaluate(() => window.__rmpCanvas.getObjects().length);
await page.click('button:has-text("Place on my design")');
await page.waitForFunction((n) => window.__rmpCanvas.getObjects().length > n, before, { timeout: 15000 });

const qr = await page.evaluate(() => {
    const o = window.__rmpCanvas.getObjects().find((x) => x.rmpRole === 'qr');
    return o ? { role: o.rmpRole, w: Math.round(o.width * o.scaleX), canvasW: window.__rmpCanvas.getWidth(), active: window.__rmpCanvas.getActiveObject() === o } : null;
});
console.log('qr object:', JSON.stringify(qr));
console.log(qr && qr.role === 'qr' ? 'OK inserted with rmpRole=qr' : 'FAIL no qr object');

const modalGone = await page.locator('button:has-text("Place on my design")').isHidden().catch(() => true);
console.log('modal closed:', modalGone);
await browser.close();
