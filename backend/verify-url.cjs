const { chromium } = require('playwright-core');
const URL = process.argv[2], OUT = process.argv[3];
(async () => {
  const b = await chromium.launch({ args: ['--no-sandbox','--disable-dev-shm-usage','--disable-gpu'] });
  const p = await b.newPage({ viewport: { width: 1300, height: 900 }, deviceScaleFactor: 2 });
  await p.goto(URL, { waitUntil: 'networkidle', timeout: 60000 });
  await p.waitForTimeout(9000);
  await p.evaluate(() => { const c = window.__rmpCanvas; if(!c) return; const o = c.getObjects().find(x => x.rmpRole === 'url'); if (o) { c.setActiveObject(o); c.requestRenderAll(); } });
  await p.waitForTimeout(800);
  const inp = await p.$('input[placeholder="yourcompany.com"]');
  await inp.click(); await inp.fill('acme-example.com'); await inp.dispatchEvent('input'); await inp.dispatchEvent('blur');
  await p.waitForTimeout(400);
  const box = await inp.boundingBox();
  await p.screenshot({ path: OUT, clip: { x: Math.max(0, box.x - 20), y: Math.max(0, box.y - 16), width: Math.min(560, 1300 - box.x + 20), height: box.height + 32 } });
  console.log('input box:', JSON.stringify(box));
  await b.close();
})();
