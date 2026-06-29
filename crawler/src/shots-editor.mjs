// Verify the fabric.js editor renders + the design→cart save flow works (and surface JS errors).
import { chromium } from 'playwright';
import { mkdir } from 'node:fs/promises';
import path from 'node:path';

const OUT = path.resolve('..', 'research', 'our-screens');
const BASE = 'http://localhost:8080';
await mkdir(OUT, { recursive: true });

const browser = await chromium.launch();
const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
const page = await ctx.newPage();
const errors = [];
page.on('pageerror', (e) => errors.push('PAGEERROR: ' + e.message));
page.on('console', (m) => m.type() === 'error' && errors.push('CONSOLE: ' + m.text()));

async function go(url) {
    await page.goto(BASE + url, { waitUntil: 'networkidle', timeout: 40000 });
    await page.waitForTimeout(1800);
}

// 1. design mode
await go('/design/standard-business-cards?mode=design');
await page.screenshot({ path: path.join(OUT, 'editor-design.png') });
console.log('design: canvases =', await page.locator('canvas').count());

// 2. upload mode
await go('/design/standard-business-cards?mode=upload');
await page.screenshot({ path: path.join(OUT, 'editor-upload.png') });

// 3. add-to-cart save flow
await go('/design/standard-business-cards?mode=design');
await page.getByRole('button', { name: /add to cart/i }).click();
await page.waitForURL('**/cart', { timeout: 20000 }).catch(() => {});
await page.waitForTimeout(1500);
await page.screenshot({ path: path.join(OUT, 'editor-cart.png'), fullPage: true });
console.log('after save, url =', page.url());

console.log('JS ERRORS:', errors.length ? '\n' + errors.join('\n') : 'none');
await browser.close();
