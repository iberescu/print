const { chromium } = require('playwright-core');
const URL = process.argv[2], OUT = process.argv[3];
(async () => {
  const b = await chromium.launch({ args: ['--no-sandbox','--disable-dev-shm-usage','--disable-gpu'] });
  const p = await b.newPage({ viewport: { width: 1300, height: 900 }, deviceScaleFactor: 2 });
  await p.goto(URL, { waitUntil: 'networkidle', timeout: 60000 });
  await p.waitForTimeout(9000);
  const clip = { x: 852, y: 106, width: 430, height: 56 };
  const sel = (r) => p.evaluate((role)=>{const c=window.__rmpCanvas;const o=c.getObjects().find(x=>x.rmpRole===role);c.discardActiveObject();c.setActiveObject(o);c.requestRenderAll();}, r);
  await sel('companyName'); await p.waitForTimeout(600); await p.screenshot({ path: OUT+'-a.png', clip });
  await sel('url'); await p.waitForTimeout(600); await p.screenshot({ path: OUT+'-b.png', clip }); // url, no check yet
  const inp = await p.$('input[placeholder="yourcompany.com"]');
  await inp.click(); await inp.fill('google.com'); await inp.dispatchEvent('input'); await inp.dispatchEvent('blur');
  await p.waitForTimeout(3500); await p.screenshot({ path: OUT+'-c.png', clip }); // url, valid
  await b.close();
})();
