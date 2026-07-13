const { chromium } = require('playwright-core');
const URL = process.argv[2], OUT = process.argv[3];
(async () => {
  const b = await chromium.launch({ args: ['--no-sandbox','--disable-dev-shm-usage','--disable-gpu'] });
  const p = await b.newPage({ viewport: { width: 1300, height: 900 }, deviceScaleFactor: 2 });
  await p.goto(URL, { waitUntil: 'networkidle', timeout: 60000 });
  await p.waitForTimeout(9000);
  const clip = { x: 840, y: 104, width: 460, height: 60 };
  // company name
  await p.evaluate(() => { const c=window.__rmpCanvas; const o=c.getObjects().find(x=>x.rmpRole==='companyName'); c.discardActiveObject(); c.setActiveObject(o); c.requestRenderAll(); });
  await p.waitForTimeout(700);
  await p.screenshot({ path: OUT+'-company.png', clip });
  // url + trigger a check that resolves
  await p.evaluate(() => { const c=window.__rmpCanvas; const o=c.getObjects().find(x=>x.rmpRole==='url'); c.discardActiveObject(); c.setActiveObject(o); c.requestRenderAll(); });
  await p.waitForTimeout(700);
  const inp = await p.$('input[placeholder="yourcompany.com"]');
  await inp.click(); await inp.fill('google.com'); await inp.dispatchEvent('input'); await inp.dispatchEvent('blur');
  await p.waitForTimeout(3500); // let the validity check resolve
  await p.screenshot({ path: OUT+'-url.png', clip });
  await b.close();
})();
