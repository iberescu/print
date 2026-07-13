const { chromium } = require('playwright-core');
const OUT = process.argv[2];
(async () => {
  const b = await chromium.launch({ args: ['--no-sandbox','--disable-dev-shm-usage','--disable-gpu'] });
  const c = await b.newContext({ viewport: { width: 1200, height: 1000 }, acceptDownloads: true });
  const p = await c.newPage(); p.on('download', d => d.saveAs('/tmp/d'+Date.now()).catch(()=>{}));
  await p.goto('https://www.runmyprint.com/qr-code-generator', { waitUntil: 'networkidle', timeout: 60000 });
  await p.waitForTimeout(2000);
  await p.fill('input[placeholder="yourcompany.com"]', 'harborandco.com');
  await p.waitForTimeout(400);
  await p.click('button:has-text("Download SVG")');
  let n = 0;
  for (let i = 0; i < 50; i++) {
    await p.waitForTimeout(3000);
    n = await p.evaluate(() => document.querySelectorAll('#qr-gallery .grid img').length);
    if (n >= 4) break;
  }
  console.log('QR-only products:', n);
  await p.waitForTimeout(1500);
  await p.evaluate(() => document.getElementById('qr-gallery')?.scrollIntoView());
  await p.waitForTimeout(400);
  const sec = await p.$('#qr-gallery'); if (sec) await sec.screenshot({ path: OUT });
  await b.close();
})();
