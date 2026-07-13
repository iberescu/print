const { chromium } = require('playwright-core');
const OUT = process.argv[2];
(async () => {
  const b = await chromium.launch({ args: ['--no-sandbox','--disable-dev-shm-usage','--disable-gpu'] });
  const ctx = await b.newContext({ viewport: { width: 1200, height: 1500 }, deviceScaleFactor: 1, acceptDownloads: true });
  const p = await ctx.newPage();
  p.on('download', d => d.saveAs('/tmp/dl-'+Date.now()).catch(()=>{}));
  await p.goto('https://www.runmyprint.com/logo-maker', { waitUntil: 'networkidle', timeout: 60000 });
  await p.waitForTimeout(2000);
  await p.fill('input[placeholder="e.g. Harbor & Co"]', 'Cedar & Key');
  await p.selectOption('select', { index: 1 });
  await p.click('button:has-text("Generate my logo")');
  console.log('generating logo…');
  await p.waitForSelector('button:has-text("Download & continue")', { timeout: 70000 });
  console.log('concept ready; selecting…');
  await p.click('button:has-text("Download & continue")');
  // wait for product mockups to stream into the native gallery grid
  let n = 0;
  for (let i = 0; i < 60; i++) {
    await p.waitForTimeout(3000);
    n = await p.evaluate(() => document.querySelectorAll('#logo-gallery .grid img').length);
    if (i % 3 === 0) console.log(`  products so far: ${n}`);
    if (n >= 6) break;
  }
  console.log('final product count:', n);
  await p.evaluate(() => document.getElementById('logo-gallery')?.scrollIntoView());
  await p.waitForTimeout(500);
  const sec = await p.$('#logo-gallery');
  if (sec) await sec.screenshot({ path: OUT });
  await b.close();
})();
