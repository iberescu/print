// Offline re-parse of saved Vistaprint template downloads (research/data/templates-100)
// with the CURRENT parser — refreshes each bundle record's surface geometry and adds the
// real die-cut outline (cutPath) where the template draws one. No network needed.
import { readFile, writeFile, readdir } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { svgFromDownload, parseSvgTemplate } from './svg-template.mjs';

const HERE = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(HERE, '..', '..');
const TPL = path.join(ROOT, 'research', 'data', 'templates-100');
const OUT = path.join(ROOT, 'backend', 'database', 'seed', 'vistaprint-100.json');

const bundle = JSON.parse(await readFile(OUT, 'utf8'));
const files = (await readdir(TPL)).filter((f) => /\.(zip|svg)$/i.test(f));

// group by "NNN-slug" stem; prefer the .zip (raw download), fall back to extracted .svg
const stems = new Map();
for (const f of files) {
    const stem = f.replace(/\.(zip|svg)$/i, '');
    if (!stems.has(stem) || f.endsWith('.zip')) stems.set(stem, f);
}

let patched = 0, cuts = 0, misses = 0;
for (const [stem, file] of stems) {
    const rec = bundle.find((p) => (p.screenshots || [])[0]?.startsWith(stem + '-'));
    if (!rec) { misses++; continue; }
    const buf = await readFile(path.join(TPL, file));
    const svg = file.endsWith('.zip') ? svgFromDownload(buf) : buf.toString('utf8');
    const geo = svg ? parseSvgTemplate(svg) : null;
    if (!geo) { misses++; continue; }

    rec.surface = rec.surface || {};
    Object.assign(rec.surface, { unit: 'mm', width: geo.width, height: geo.height });
    if (geo.bleed != null) rec.surface.bleed = geo.bleed;
    if (geo.safety != null) rec.surface.safety = geo.safety;
    if (geo.fold) { rec.surface.fold = geo.fold; rec.surface.folded = true; }
    if (geo.cutPath) { rec.surface.cut = geo.cutPath; cuts++; }
    rec.surfaceSvg = geo;
    patched++;
    console.log(`${geo.cutPath ? 'CUT ' : 'geo '} ${stem}  ${geo.width}x${geo.height}mm b=${geo.bleed ?? '?'}${geo.cutPath ? `  cut(${geo.cutPath.length} ch)` : ''}`);
}

await writeFile(OUT, JSON.stringify(bundle, null, 2));
console.log(`\npatched ${patched} records (${cuts} with die-cut outlines), ${misses} unmatched/unparsable -> ${path.relative(ROOT, OUT)}`);
