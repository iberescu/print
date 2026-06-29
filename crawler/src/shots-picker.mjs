// Open the designer's template picker and screenshot it.
import { chromium } from 'playwright';
import { mkdir } from 'node:fs/promises';
import path from 'node:path';

const OUT = path.resolve('..', 'research', 'our-screens');
const BASE = process.env.APP_URL || 'http://localhost:8080';
await mkdir(OUT, { recursive: true });

const browser = await chromium.launch();
const page = await (await browser.newContext({ viewport: { width: 1440, height: 900 } })).newPage();
await page.goto(BASE + '/design/standard-business-cards?mode=design', { waitUntil: 'domcontentloaded', timeout: 40000 });
await page.waitForTimeout(3500);
await page.getByRole('button', { name: /templates/i }).click();
await page.waitForTimeout(3000); // let preview thumbnails load
await page.screenshot({ path: path.join(OUT, 'editor-templates.png') });
console.log('template tiles visible:', await page.locator('button img').count());
await browser.close();
