import { chromium } from 'playwright';
import fs from 'node:fs';
import { PNG } from 'pngjs';
import jsQR from 'jsqr';

const BASE = process.env.APP_URL || 'http://localhost:8080';
const LOGO = process.env.LOGO || '/tmp/claude-0/-root-work-print/774c9334-bf19-4937-b3ea-a0ecd0b2e8e3/scratchpad/test-logo.png';
const OUT = '/tmp/claude-0/-root-work-print/774c9334-bf19-4937-b3ea-a0ecd0b2e8e3/scratchpad';

const browser = await chromium.launch();
const ctx = await browser.newContext({ acceptDownloads: true });
const page = await ctx.newPage();

let captureBody = null;
page.on('request', (r) => { if (r.url().includes('/qr/capture')) captureBody = r.postData(); });

await page.goto(BASE + '/qr-code-generator', { waitUntil: 'networkidle' });
await page.fill('input[placeholder="yourcompany.com"]', 'https://www.runmyprint.com');

// styling: rounded + navy
await page.click('button:text-is("Rounded")');
await page.locator('button[aria-label="#2b3b55"]').click();

// upload the logo
const [chooser] = await Promise.all([
    page.waitForEvent('filechooser'),
    page.click('button:has-text("Add logo in the middle")'),
]);
await chooser.setFiles(LOGO);
await page.waitForSelector('img[alt="Your logo"]', { timeout: 10000 });
console.log('OK logo uploaded, chip visible');

await page.waitForTimeout(600);
const src = await page.getAttribute('img[src*="/qr/image"]', 'src');
console.log('preview params:', src.includes('style=rounded') && src.includes('color=2b3b55') && src.includes('logo=1') ? 'OK style+color+logo in URL' : 'FAIL ' + src);

// screenshot the live preview (SVG with embedded logo)
await page.locator('img[src*="/qr/image"]').screenshot({ path: OUT + '/qr-logo-preview.png' });

// download PNG and decode it to prove it still scans
const dl = page.waitForEvent('download', { timeout: 15000 });
await page.click('button:has-text("Download PNG")');
const download = await dl;
const file = OUT + '/qr-logo-download.png';
await download.saveAs(file);
console.log('downloaded:', download.suggestedFilename());

const png = PNG.sync.read(fs.readFileSync(file));
const code = jsQR(new Uint8ClampedArray(png.data), png.width, png.height);
console.log(code ? 'OK decodes to: ' + code.data : 'FAIL: composited QR does not decode');

await page.waitForSelector('#qr-gallery', { timeout: 10000 });
console.log('capture body:', captureBody);
await browser.close();
