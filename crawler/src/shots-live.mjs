// Screenshot the live deployed site.
import { chromium } from 'playwright';
import { mkdir } from 'node:fs/promises';
import path from 'node:path';

const OUT = path.resolve('..', 'research', 'our-screens');
const BASE = process.env.APP_URL || 'http://174.138.35.202';
await mkdir(OUT, { recursive: true });

const browser = await chromium.launch();
const page = await (await browser.newContext({ viewport: { width: 1440, height: 900 } })).newPage();
for (const [name, url] of [['live-home', '/'], ['live-product', '/product/standard-business-cards']]) {
    await page.goto(BASE + url, { waitUntil: 'networkidle', timeout: 40000 });
    await page.waitForTimeout(1500);
    await page.screenshot({ path: path.join(OUT, `${name}.png`), fullPage: true });
    console.log('shot', name, '->', await page.title());
}
await browser.close();
