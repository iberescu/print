// Screenshot our own storefront (localhost:8080) for design review + parity checks.
import { chromium } from 'playwright';
import { mkdir } from 'node:fs/promises';
import path from 'node:path';

const OUT = path.resolve('..', 'research', 'our-screens');
const BASE = process.env.APP_URL || 'http://localhost:8080';

const targets = [
    ['home', '/'],
    ['category', '/category/business-cards'],
    ['product', '/product/standard-business-cards'],
];

await mkdir(OUT, { recursive: true });
const browser = await chromium.launch();
const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
const page = await ctx.newPage();

for (const [name, url] of targets) {
    try {
        await page.goto(BASE + url, { waitUntil: 'networkidle', timeout: 40000 });
        await page.waitForTimeout(1500);
        await page.screenshot({ path: path.join(OUT, `${name}.png`), fullPage: true });
        console.log(`shot ${name}  "${await page.title()}"`);
    } catch (e) {
        console.log(`FAIL ${name}: ${e.message}`);
    }
}
await browser.close();
console.log('Done -> research/our-screens/');
