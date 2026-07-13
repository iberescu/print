const { chromium } = require('playwright-core');
const URL = process.argv[2];
(async () => {
  const b = await chromium.launch({ args: ['--no-sandbox','--disable-dev-shm-usage','--disable-gpu'] });
  const p = await b.newPage({ viewport: { width: 1300, height: 900 } });
  await p.goto(URL, { waitUntil: 'networkidle', timeout: 60000 });
  await p.waitForTimeout(9000);
  async function inspect(role) {
    await p.evaluate((r) => { const c=window.__rmpCanvas; const o=c.getObjects().find(x=>x.rmpRole===r); c.discardActiveObject(); c.setActiveObject(o); c.requestRenderAll(); }, role);
    await p.waitForTimeout(600);
    return await p.evaluate(() => {
      const inp = document.querySelector('input[placeholder="yourcompany.com"], input[placeholder="Edit text"]');
      if (!inp) return { err: 'no input' };
      const box = inp.parentElement;
      const cs = getComputedStyle(inp), bcs = getComputedStyle(box);
      const pick = (s, ks) => ks.reduce((a,k)=>(a[k]=s[k],a), {});
      return {
        inputHTML: inp.outerHTML.replace(/\s+/g,' ').slice(0,180),
        containerHTML: box.outerHTML.replace(/\s+/g,' ').replace(/<svg[\s\S]*?<\/svg>/g,'<svg/>').slice(0,300),
        input: pick(cs, ['width','height','paddingLeft','paddingRight','borderRadius','borderWidth','boxSizing','backgroundColor']),
        container: pick(bcs, ['display','width','columnGap','flexShrink']),
      };
    });
  }
  console.log('COMPANY', JSON.stringify(await inspect('companyName'), null, 1));
  console.log('URL', JSON.stringify(await inspect('url'), null, 1));
  await b.close();
})();
