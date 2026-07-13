const { chromium } = require('playwright-core');
const OUT = process.argv[2];
(async () => {
  const b = await chromium.launch({ args: ['--no-sandbox','--disable-dev-shm-usage','--disable-gpu'] });
  const p = await b.newPage({ viewport: { width: 1100, height: 1400 }, deviceScaleFactor: 1.5 });
  await p.goto('https://www.runmyprint.com/logo-maker', { waitUntil: 'networkidle', timeout: 60000 });
  await p.waitForTimeout(2500);
  await p.fill('input[placeholder="e.g. Harbor & Co"]', 'Harbor & Co');
  await p.selectOption('select', { index: 1 });
  await p.waitForTimeout(300);
  await p.click('button:has-text("Generate my logo")');
  async function shotAt(sec, file) {
    await p.waitForTimeout(sec*1000);
    const bar = await p.$('div[style*="width"]');
    const w = await p.evaluate(() => { const el=[...document.querySelectorAll('div[style*="width"]')].find(e=>/%$/.test(e.style.width)); return el?el.style.width:null; });
    // clip around the generate button + bar region
    const btn = await p.$('button:has-text("Designing"), button:has-text("Generate")');
    const bb = btn ? await btn.boundingBox() : null;
    const y = bb ? Math.max(0, bb.y - 10) : 300;
    await p.screenshot({ path: file, clip: { x: 40, y, width: 760, height: 170 } });
    return w;
  }
  console.log('bar width @~4s:', await shotAt(4, OUT+'-t4.png'));
  console.log('bar width @~14s:', await shotAt(10, OUT+'-t14.png'));
  await b.close();
})();
