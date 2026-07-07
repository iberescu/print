import { chromium } from 'playwright';

const BASE = process.env.APP_URL || 'http://localhost:8080';
const browser = await chromium.launch();
const page = await browser.newPage();
const bad = [];
page.on('response', (r) => { if (r.status() >= 400) bad.push(r.status() + ' ' + r.url()); });

await page.goto(BASE + '/qr-code-generator', { waitUntil: 'networkidle' });

// content sections present
for (const t of ['How to make a QR code', 'Put your QR code on print', 'add a QR while you design', 'QR codes that scan every time', 'Need a logo to go next to that QR code', 'common questions']) {
    const ok = await page.getByText(t, { exact: false }).first().isVisible().catch(() => false);
    console.log((ok ? 'OK ' : 'FAIL ') + 'section: ' + t);
}

// 4 use-case images loaded (natural size > 0)
const imgs = await page.$$eval('img[src*="promos/qr-"]', (els) => els.map((e) => ({ src: e.src.split('/').pop(), w: e.naturalWidth })));
console.log('use-case images:', JSON.stringify(imgs));

// FAQ count + JSON-LD blocks
console.log('faq items:', await page.locator('details').count());
const ld = await page.$$eval('script[type="application/ld+json"]', (els) => els.flatMap((e) => JSON.parse(e.text)).map((x) => x['@type']));
console.log('json-ld types:', JSON.stringify(ld));
console.log('meta description:', await page.getAttribute('meta[name="description"]', 'content'));

// tool still works: type URL -> live preview appears
await page.fill('input[placeholder="yourcompany.com"]', 'cloudlab-solutions.com');
await page.waitForSelector('img[src*="/qr/image"]', { timeout: 8000 });
console.log('OK live preview renders');

// vcard tab
await page.click('button:has-text("Contact card")');
await page.fill('input[placeholder="Alex Carter"]', 'Alex Carter');
await page.waitForTimeout(400);
const src = await page.getAttribute('img[src*="/qr/image"]', 'src');
console.log('OK vcard preview:', decodeURIComponent(src).includes('BEGIN:VCARD'));

if (bad.length) console.log('HTTP errors:', bad.slice(0, 5));
else console.log('OK no 4xx/5xx responses');
await browser.close();
