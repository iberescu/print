// Parse the saved feather-flag PDF template and patch the bundle with the REAL
// outline + relative bleed/safety (combined with the crawl's known 2.4x7.5ft size).
import { readFile, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { pdfFromDownload, parsePdfTemplate } from './svg-template.mjs';
const HERE = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(HERE, '..', '..');
const OUT = path.join(ROOT, 'backend', 'database', 'seed', 'vistaprint-100.json');
const zip = await readFile(path.join(ROOT, 'research', 'data', 'templates-100', '046-feather-flags.zip'));
const pdf = pdfFromDownload(zip);
console.log('pdf extracted:', pdf ? pdf.length + ' bytes' : 'NONE');
const geo = parsePdfTemplate(pdf);
console.log('geo:', JSON.stringify({ ...geo, cutPath: geo?.cutPath ? geo.cutPath.slice(0, 120) + `… (${geo.cutPath.length} ch)` : null }, null, 1));
if (!geo?.cutPath) process.exit(1);

const bundle = JSON.parse(await readFile(OUT, 'utf8'));
const rec = bundle.find((p) => /feather flag/i.test(p.title));
const s = (rec.surface = rec.surface || {});
// keep the crawl's known physical size (2.4 x 7.5 ft); take shape + relative margins from the PDF
const wKnown = s.width || 2.4, unit = s.unit || 'ft';
s.cut = geo.cutPath;
if (geo.bleedFrac) s.bleed = +(geo.bleedFrac * wKnown).toFixed(3);
if (geo.safetyFrac) s.safety = +(geo.safetyFrac * wKnown).toFixed(3);
rec.surfacePdf = { bleedFrac: geo.bleedFrac, safetyFrac: geo.safetyFrac, scaleUnreliable: true };
await writeFile(OUT, JSON.stringify(bundle, null, 2));
console.log(`PATCHED feather: unit=${unit} w=${s.width} h=${s.height} bleed=${s.bleed} safety=${s.safety} cut=${s.cut.length} ch`);
