const { chromium } = require('playwright-core');
const OUT = process.argv[2], LOGO = process.argv[3];
async function run(withLogo, page) {
  await page.goto('https://www.runmyprint.com/qr-code-generator', { waitUntil: 'networkidle', timeout: 60000 });
  await page.waitForTimeout(2000);
  if (withLogo) {
    await page.setInputFiles('input[type="file"]', LOGO);
    await page.waitForTimeout(4000); // logo upload + preview
  }
  await page.fill('input[placeholder="yourcompany.com"]', 'cedarandkey.com');
  await page.waitForTimeout(500);
  await page.click('button:has-text("Download SVG")');
  let n = 0;
  for (let i = 0; i < 55; i++) {
    await page.waitForTimeout(3000);
    n = await page.evaluate(() => document.querySelectorAll('#qr-gallery .grid img').length);
    if (i % 3 === 0) console.log(`  ${withLogo?'logo+QR':'QR-only'} products: ${n}`);
    if (n >= (withLogo ? 8 : 4)) break;
  }
  console.log(`${withLogo?'logo+QR':'QR-only'} FINAL: ${n} products`);
  await page.evaluate(() => document.getElementById('qr-gallery')?.scrollIntoView());
  await page.waitForTimeout(500);
  const sec = await page.$('#qr-gallery');
  if (sec) await sec.screenshot({ path: OUT });
  return n;
}
(async () => {
  const b = await chromium.launch({ args: ['--no-sandbox','--disable-dev-shm-usage','--disable-gpu'] });
  const c1 = await b.newContext({ viewport: { width: 1200, height: 1600 }, acceptDownloads: true });
  c1.on('page', p => p.on('download', d => d.saveAs('/tmp/d'+Date.now()).catch(()=>{})));
  const p1 = await c1.newPage(); p1.on('download', d => d.saveAs('/tmp/d'+Date.now()).catch(()=>{}));
  await run(false, p1); await c1.close();
  const c2 = await b.newContext({ viewport: { width: 1200, height: 1600 }, acceptDownloads: true });
  const p2 = await c2.newPage(); p2.on('download', d => d.saveAs('/tmp/d'+Date.now()).catch(()=>{}));
  await run(true, p2); await c2.close();
  await b.close();
})();
