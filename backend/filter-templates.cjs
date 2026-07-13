// QA filter for generated templates: drop score<7 and vision-detected text/logo clipping.
// Writes gen/filter.json with a pass/fail verdict per variant. Usage: node filter-templates.cjs
const fs = require('fs');
const KEY = (fs.readFileSync('.env', 'utf8').match(/^GEMINI_API_KEY=(.+)$/m) || [])[1]?.trim().replace(/^["']|["']$/g, '');
const GL = 'https://generativelanguage.googleapis.com/v1beta/models';
const VIS = 'gemini-3.5-flash';
const OUT = '/tmp/claude-0/-root-work-print/6fc57cdb-3cd9-4601-99d8-4c7e4f78bbb0/scratchpad/gen';
const PRODUCTS = ['bcard', 'flyer', 'poster', 'postcard', 'letterhead', 'banner'];
const SCORE_MIN = 7;
const CONC = 6;

const PROMPT = 'You are QA for print-design templates. Look ONLY at whether the TEXT and the LOGO are fully inside the frame. '
  + 'Full-bleed background photos or colour blocks reaching the edges are FINE and expected — do NOT flag those. '
  + 'Flag a problem ONLY if: readable TEXT (headline, name, contact line, etc.) or the LOGO is cut off / clipped / running off any '
  + 'edge of the image, OR text overlaps other text/graphics so badly it is unreadable. '
  + 'Reply STRICT JSON: {"textClipped":true|false,"unreadable":true|false,"note":"<=6 words"}.';

async function gem(parts) {
  const body = { contents: [{ parts }], generationConfig: { responseMimeType: 'application/json' } };
  for (let a = 0; a < 4; a++) {
    try {
      const r = await fetch(`${GL}/${VIS}:generateContent?key=${KEY}`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
      if (!r.ok) { if ([429, 500, 502, 503, 529].includes(r.status)) { await new Promise(s => setTimeout(s, 2000 * (a + 1))); continue; } throw new Error('HTTP ' + r.status); }
      const t = (await r.json()).candidates?.[0]?.content?.parts?.map(p => p.text || '').join('') || '{}';
      return JSON.parse(t.replace(/^```(?:json)?\s*/i, '').replace(/\s*```$/, '').trim());
    } catch (e) { if (a === 3) return null; await new Promise(s => setTimeout(s, 2000 * (a + 1))); }
  }
  return null;
}

(async () => {
  const items = [];
  for (const pk of PRODUCTS) {
    const meta = JSON.parse(fs.readFileSync(`${OUT}/${pk}/meta.json`, 'utf8'));
    for (const m of meta) items.push({ pk, ref: m.ref, orientation: m.orientation, score: m.score });
  }
  console.log('variants:', items.length);
  const results = []; let idx = 0;
  async function worker() {
    while (idx < items.length) {
      const it = items[idx++];
      if (!(it.score >= SCORE_MIN)) { results.push({ ...it, verdict: 'fail', reason: 'score' }); continue; }
      const png = `${OUT}/${it.pk}/${it.ref}.png`;
      if (!fs.existsSync(png)) { results.push({ ...it, verdict: 'fail', reason: 'nopng' }); continue; }
      const v = await gem([{ text: PROMPT }, { inlineData: { mimeType: 'image/png', data: fs.readFileSync(png).toString('base64') } }]);
      if (v && (v.textClipped || v.unreadable)) results.push({ ...it, verdict: 'fail', reason: v.textClipped ? 'clipped' : 'unreadable', note: v.note || '' });
      else results.push({ ...it, verdict: 'pass', reason: v ? 'ok' : 'vision-null' });
      if (results.length % 40 === 0) console.log(`  ${results.length}/${items.length}`);
    }
  }
  await Promise.all(Array.from({ length: CONC }, () => worker()));
  fs.writeFileSync(`${OUT}/filter.json`, JSON.stringify(results, null, 2));
  const fail = results.filter(r => r.verdict === 'fail');
  const byProd = fail.reduce((a, f) => { (a[f.pk] = a[f.pk] || {})[f.reason] = (a[f.pk][f.reason] || 0) + 1; return a; }, {});
  console.log('TOTAL', results.length, 'PASS', results.length - fail.length, 'FAIL', fail.length);
  console.log('fail by product/reason:', JSON.stringify(byProd, null, 0));
})();
