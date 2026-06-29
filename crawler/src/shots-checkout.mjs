// E2E: product -> design -> add to cart -> checkout -> place order -> success (demo mode).
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
await page.getByRole('button', { name: /design online/i }).first().click();
await page.waitForURL('**/design/**', { timeout: 20000 }).catch(() => {});
await page.waitForTimeout(2500);
await page.getByRole('button', { name: /add to cart/i }).click();
await page.waitForURL('**/cart', { timeout: 20000 }).catch(() => {});
await page.waitForTimeout(1200);
console.log('cart url:', page.url(), '| cart rows:', await page.locator('button:has-text("Remove")').count());

await page.getByRole('link', { name: /proceed to checkout/i }).click();
await page.waitForURL('**/checkout', { timeout: 20000 }).catch(() => {});
await page.waitForTimeout(1200);
console.log('checkout url:', page.url(), '| forms:', await page.locator('form').count(), '| form inputs:', await page.locator('form input').count());
await page.screenshot({ path: path.join(OUT, 'checkout-form.png'), fullPage: true });

const f = page.locator('form').first().locator('input');
const n = await f.count();
console.log('checkout form input count:', n);
if (n >= 6) {
    await f.nth(0).fill('test@runmyprint.com');
    await f.nth(1).fill('Jane Tester');
    await f.nth(2).fill('123 Print Street');
    await f.nth(3).fill('Austin');
    await f.nth(4).fill('78701');
    await f.nth(5).fill('United States');
    await page.getByRole('button', { name: /pay/i }).click();
    await page.waitForURL('**/checkout/success**', { timeout: 20000 }).catch(() => {});
    await page.waitForTimeout(1500);
    await page.screenshot({ path: path.join(OUT, 'checkout-success.png'), fullPage: true });
}

console.log('final url:', page.url());
console.log('JS ERRORS:', errors.length ? '\n' + errors.join('\n') : 'none');
await browser.close();
