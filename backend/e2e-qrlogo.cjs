const { chromium } = require('playwright-core');
const OUT = process.argv[2], LOGO = process.argv[3];
(async () => {
  const b = await chromium.launch({ args: ['--no-sandbox','--disable-dev-shm-usage','--disable-gpu'] });
  const c = await b.newContext({ viewport: { width: 1200, height: 1700 }, acceptDownloads: true });
  const p = await c.newPage(); p.on('download', d => d.saveAs('/tmp/d'+Date.now()).catch(()=>{}));
  await p.goto('https://www.runmyprint.com/qr-code-generator', { waitUntil: 'networkidle', timeout: 60000 });
  await p.waitForTimeout(2000);
  await p.setInputFiles('input[type="file"]', LOGO);
  await p.waitForTimeout(4000);
  await p.fill('input[placeholder="yourcompany.com"]', 'cedarandkey.com');
  await p.waitForTimeout(500);
  await p.click('button:has-text("Download SVG")');
  let n = 0;
  for (let i = 0; i < 60; i++) {
    await p.waitForTimeout(3000);
    n = await p.evaluate(() => document.querySelectorAll('#qr-gallery .grid img').length);
    if (i % 3 === 0) console.log('  products:', n);
    if (n >= 15) break;
  }
  console.log('FINAL:', n);
  await p.evaluate(() => document.getElementById('qr-gallery')?.scrollIntoView());
  await p.waitForTimeout(500);
  const sec = await p.$('#qr-gallery'); if (sec) await sec.screenshot({ path: OUT });
  await b.close();
})();
