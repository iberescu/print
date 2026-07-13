// Load the real editor URL, let the template auto-apply, screenshot the canvas.
const { chromium } = require('playwright-core');
const URL = process.argv[2];
const OUT = process.argv[3] || '/tmp/editor.png';
(async () => {
  const browser = await chromium.launch({ args: ['--no-sandbox', '--disable-dev-shm-usage', '--disable-gpu'] });
  const page = await browser.newPage({ viewport: { width: 1200, height: 1000 }, deviceScaleFactor: 2 });
  page.on('console', m => { if (m.type() === 'error') console.log('PAGE ERR:', m.text().slice(0, 160)); });
  await page.goto(URL, { waitUntil: 'networkidle', timeout: 60000 });
  // wait for the fabric canvas to exist + the template fetch/apply + fonts to settle
  await page.waitForSelector('canvas', { timeout: 30000 }).catch(() => {});
  await page.waitForTimeout(9000);
  const canvasBox = await page.evaluate(() => {
    const c = document.querySelector('.canvas-container') || document.querySelector('canvas');
    if (!c) return null;
    const r = c.getBoundingClientRect();
    return { x: r.x, y: r.y, width: r.width, height: r.height };
  });
  if (canvasBox && canvasBox.width > 50) {
    await page.screenshot({ path: OUT, clip: { x: Math.max(0, canvasBox.x - 30), y: Math.max(0, canvasBox.y - 30), width: canvasBox.width + 60, height: canvasBox.height + 60 } });
  } else {
    await page.screenshot({ path: OUT, fullPage: true });
  }
  console.log('shot:', OUT, 'canvas:', JSON.stringify(canvasBox));
  await browser.close();
})();
