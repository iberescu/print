// Screenshot tour of the reference site (Vistaprint) for UX + parity baselines.
// Each step is guarded so partial failures still capture what they can.
import { chromium } from 'playwright';
import { mkdir, writeFile } from 'node:fs/promises';
import path from 'node:path';

const OUT = path.resolve('..', 'research', 'screenshots');
const BASE = process.env.BASE_URL || 'https://www.vistaprint.com';
const UA =
  'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 ' +
  '(KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';

const log = (...a) => console.log(...a);

async function shoot(page, name) {
  try {
    await page.screenshot({ path: path.join(OUT, `${name}.png`), fullPage: true });
    log(`  📸 ${name}.png  title="${await page.title()}"  url=${page.url()}`);
  } catch (e) {
    log(`  ⚠️  screenshot ${name} failed: ${e.message}`);
  }
}

function looksBlocked(html) {
  const h = html.toLowerCase();
  return [
    'access denied', 'are you a human', 'captcha', 'verify you are',
    'unusual traffic', 'reference #', 'pardon our interruption',
  ].some((s) => h.includes(s));
}

async function step(name, fn) {
  log(`→ ${name}`);
  try {
    await fn();
  } catch (e) {
    log(`  ⚠️  step "${name}" failed: ${e.message}`);
  }
}

async function main() {
  await mkdir(OUT, { recursive: true });
  const browser = await chromium.launch({ headless: true });
  const ctx = await browser.newContext({
    userAgent: UA,
    viewport: { width: 1440, height: 900 },
    locale: 'en-US',
    extraHTTPHeaders: { 'Accept-Language': 'en-US,en;q=0.9' },
  });
  const page = await ctx.newPage();
  page.setDefaultTimeout(45000);

  await step('Home', async () => {
    await page.goto(BASE, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(5000);
    const html = await page.content();
    await writeFile(path.join(OUT, '00-home.html'), html);
    if (looksBlocked(html)) log('  🚧 BOT WALL likely on home — will pivot to fallbacks.');
    await shoot(page, '01-home');
  });

  await step('Business cards landing', async () => {
    // try direct URL first, then a nav link
    try {
      await page.goto(`${BASE}/business-cards`, { waitUntil: 'domcontentloaded' });
    } catch {
      const link = page.getByRole('link', { name: /business cards/i }).first();
      if (await link.count()) await link.click();
    }
    await page.waitForTimeout(5000);
    await shoot(page, '02-business-cards');
  });

  await step('Product / config page', async () => {
    const cta = page.getByRole('link', { name: /standard|start designing|choose|create/i }).first();
    if (await cta.count()) {
      await cta.click();
      await page.waitForTimeout(5000);
    }
    await shoot(page, '03-product');
  });

  await step('Editor', async () => {
    const design = page.getByRole('button', { name: /design|customize|create now|edit/i }).first();
    if (await design.count()) {
      await design.click();
      await page.waitForTimeout(7000);
    }
    await shoot(page, '04-editor');
  });

  await step('Cart / upsell (best-effort)', async () => {
    await page.goto(`${BASE}/cart`, { waitUntil: 'domcontentloaded' }).catch(() => {});
    await page.waitForTimeout(4000);
    await shoot(page, '05-cart');
  });

  await browser.close();
  log('Done. Review PNGs + 00-home.html in research/screenshots/.');
}

main().catch((e) => {
  console.error('FATAL', e);
  process.exit(1);
});
