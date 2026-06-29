// E2E funnel screenshots: product -> designer -> add to cart -> cart (req 7/11/15).
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
page.on('pageerror', (e) => errors.push('PAGEERR: ' + e.message));
page.on('console', (m) => m.type() === 'error' && errors.push('CONSOLE: ' + m.text()));

await page.goto(BASE + '/product/standard-business-cards', { waitUntil: 'networkidle', timeout: 40000 });
await page.waitForTimeout(1000);
await page.screenshot({ path: path.join(OUT, 'funnel-product.png'), fullPage: true });

await page.getByRole('button', { name: /design online/i }).first().click();
await page.waitForURL('**/design/**', { timeout: 20000 }).catch(() => {});
await page.waitForTimeout(2500);
await page.screenshot({ path: path.join(OUT, 'funnel-editor.png') });

await page.getByRole('button', { name: /add to cart/i }).click();
await page.waitForURL('**/cart', { timeout: 20000 }).catch(() => {});
await page.waitForTimeout(1500);
await page.screenshot({ path: path.join(OUT, 'funnel-cart.png'), fullPage: true });

console.log('final url:', page.url());
console.log('JS ERRORS:', errors.length ? '\n' + errors.join('\n') : 'none');
await browser.close();
