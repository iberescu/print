const { chromium } = require('playwright-core');
const fs = require('fs');

(async () => {
  const ref = process.argv[2] || '001';
  const w = Number(process.argv[3] || 760), h = Number(process.argv[4] || 434);
  const out = process.argv[5] || `/tmp/claude-0/-root-work-print/6fc57cdb-3cd9-4601-99d8-4c7e4f78bbb0/scratchpad/render-${ref}.png`;

  const json = JSON.parse(fs.readFileSync(`templates/json/${ref}.json`, 'utf8'));
  const logo = 'data:image/webp;base64,' + fs.readFileSync('storage/app/public/brand/logo-placeholder.webp').toString('base64');
  for (const o of json.objects || []) {
    if ((o.type || '').toLowerCase() === 'image' && typeof o.src === 'string' && o.src.includes('logo-placeholder')) o.src = logo;
  }
  const families = [...new Set((json.objects || []).map(o => o.fontFamily).filter(Boolean))];

  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1000, height: 700 } });
  await page.goto('http://localhost:8091/render.html', { waitUntil: 'load' });
  await page.waitForFunction('window.__ready === true', { timeout: 15000 });
  await page.evaluate((f) => window.loadFonts(f), families);
  const dataUrl = await page.evaluate(([j, w, h]) => window.renderTemplate(j, w, h), [json, w, h]);
  fs.writeFileSync(out, Buffer.from(dataUrl.split(',')[1], 'base64'));
  await browser.close();
  console.log('rendered', ref, '->', out, `(${w}x${h})`);
})().catch((e) => { console.error('FAIL:', e.message); process.exit(1); });
