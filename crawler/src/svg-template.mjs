// Shared Vistaprint print-template parsing (SVG + PDF): geometry (trim/bleed/safety
// in mm) plus the ACTUAL die-cut outline, normalized to 0–100 coordinates relative to
// the trim box (the app's surfaces.cut_path format). Used by the live crawler and by
// offline re-parses of saved template downloads.
import { unzipSync, unzlibSync, inflateSync } from 'fflate';

// The download is usually a .zip (guidelines PDF + template SVGs). Pull the SVG text out.
export function svgFromDownload(buf) {
    if (!buf || !buf.length) return null;
    if (buf[0] === 0x50 && buf[1] === 0x4b) { // 'PK' -> zip
        try {
            const files = unzipSync(new Uint8Array(buf));
            const key = Object.keys(files).find((k) => /\.svg$/i.test(k));
            if (key) return new TextDecoder().decode(files[key]);
        } catch {}
        return null;
    }
    const text = Buffer.isBuffer(buf) ? buf.toString('utf8') : String(buf);
    return /<svg[\s>]/i.test(text) ? text : null;
}

// Some products (sewn goods like flags) ship a PDF-only template. Pull its bytes out.
export function pdfFromDownload(buf) {
    if (!buf || !buf.length) return null;
    if (buf[0] === 0x50 && buf[1] === 0x4b) {
        try {
            const files = unzipSync(new Uint8Array(buf));
            const key = Object.keys(files).find((k) => /\.pdf$/i.test(k));
            if (key) return Buffer.from(files[key]);
        } catch {}
        return null;
    }
    return buf.length > 4 && buf.toString('latin1', 0, 4) === '%PDF' ? buf : null;
}

// Parse a PDF print template (sewn goods like flags ship PDF-only). Same semantics
// as the SVG templates: OCG layers for Trim/Bleed/Safety whose vector paths carry the
// real outline. Large-format PDFs are often at reduced scale, so absolute mm can be
// unreliable — returns RELATIVE bleed/safety fractions + the normalized cut path, and
// computed mm as a fallback. Layers are identified by bbox size (bleed ⊃ trim ⊃ safe),
// which is scale- and naming-robust.
export function parsePdfTemplate(buf) {
    if (!buf || buf.length > 8_000_000) return null;
    const raw = buf.toString('latin1');

    // inflate every Flate stream; keep textual ones
    const parts = [];
    const streamRe = /stream\r?\n/g;
    let m;
    while ((m = streamRe.exec(raw))) {
        const start = m.index + m[0].length;
        const end = raw.indexOf('endstream', start);
        if (end < 0) break;
        const slice = new Uint8Array(buf.slice(start, end));
        try { parts.push(Buffer.from(unzlibSync(slice)).toString('latin1')); }        // FlateDecode (zlib)
        catch { try { parts.push(Buffer.from(inflateSync(slice)).toString('latin1')); } // raw deflate
        catch { parts.push(Buffer.from(slice).toString('latin1')); } }                  // uncompressed
    }
    const content = parts.join('\n');

    // drawing chunks per optional-content layer
    const chunks = [...content.matchAll(/\/OC\s*\/\w+\s+BDC([\s\S]*?)EMC/g)].map((x) => x[1]);
    if (chunks.length < 2) return null;

    // y-flip when the page CTM mirrors the axis (very common: "1 0 0 -1 0 H cm")
    const flipY = /(?:^|\n)\s*1 0 0 -1 [\d.]+ [\d.]+ cm/.test(content);

    // walk one chunk's operators -> absolute points + an SVG path in raw coordinates
    const walk = (body) => {
        const pts = [];
        const segs = [];
        let cx = 0, cy = 0;
        const tokRe = /(-?[\d.]+(?:\s+-?[\d.]+)*)\s+(m|l|c|v|y|re|h)\b/g;
        let t;
        while ((t = tokRe.exec(body))) {
            const n = t[1].trim().split(/\s+/).map(Number);
            const op = t[2];
            const push = (x, y) => pts.push([x, y]);
            if (op === 'm' && n.length >= 2) { cx = n[0]; cy = n[1]; push(cx, cy); segs.push(['M', cx, cy]); }
            else if (op === 'l' && n.length >= 2) { cx = n[0]; cy = n[1]; push(cx, cy); segs.push(['L', cx, cy]); }
            else if (op === 'c' && n.length >= 6) { push(n[0], n[1]); push(n[2], n[3]); push(n[4], n[5]); segs.push(['C', ...n.slice(0, 6)]); cx = n[4]; cy = n[5]; }
            else if (op === 'v' && n.length >= 4) { segs.push(['C', cx, cy, n[0], n[1], n[2], n[3]]); push(n[0], n[1]); push(n[2], n[3]); cx = n[2]; cy = n[3]; }
            else if (op === 'y' && n.length >= 4) { segs.push(['C', n[0], n[1], n[2], n[3], n[2], n[3]]); push(n[0], n[1]); push(n[2], n[3]); cx = n[2]; cy = n[3]; }
            else if (op === 're' && n.length >= 4) { const [x, y, w, h] = n; push(x, y); push(x + w, y + h); segs.push(['M', x, y], ['L', x + w, y], ['L', x + w, y + h], ['L', x, y + h], ['Z']); }
            else if (op === 'h') segs.push(['Z']);
        }
        if (!pts.length) return null;
        const xs = pts.map((p) => p[0]); const ys = pts.map((p) => p[1]);
        return { segs, box: { minX: Math.min(...xs), maxX: Math.max(...xs), minY: Math.min(...ys), maxY: Math.max(...ys) } };
    };

    const walked = chunks.map(walk).filter(Boolean);
    if (walked.length < 2) return null;
    // identify by bbox area: largest = bleed, middle = trim, smallest = safety
    const area = (b) => (b.maxX - b.minX) * (b.maxY - b.minY);
    const sorted = [...walked].sort((a, b) => area(b.box) - area(a.box));
    const [bleedL, trimL, safeL] = sorted.length >= 3 ? sorted : [sorted[0], sorted[1] ?? sorted[0], null];
    const trim = trimL.box;
    const w = trim.maxX - trim.minX || 1;
    const h = trim.maxY - trim.minY || 1;

    // normalized cut path from the trim layer (real outline for shaped products)
    const sx = 100 / w, sy = 100 / h;
    const nx = (x) => +((x - trim.minX) * sx).toFixed(2);
    const ny = (y) => { const v = (y - trim.minY) * sy; return +(flipY ? 100 - v : v).toFixed(2); };
    const d = trimL.segs.map(([op, ...n]) => {
        if (op === 'Z') return 'Z';
        const out = [];
        for (let i = 0; i < n.length; i += 2) out.push(nx(n[i]), ny(n[i + 1]));
        return op + ' ' + out.join(' ');
    }).join(' ');
    const curved = /C/.test(d);

    // reduced-scale PDFs make absolute mm unreliable -> fractions of the trim size
    const geo = {
        unit: 'mm',
        width: +(w * 25.4 / 72).toFixed(2),
        height: +(h * 25.4 / 72).toFixed(2),
        scaleUnreliable: true,
        bleedFrac: +Math.max(0, (trim.minX - bleedL.box.minX) / w).toFixed(4),
        safetyFrac: safeL ? +Math.max(0, (safeL.box.minX - trim.minX) / w).toFixed(4) : null,
    };
    if (curved && d.length > 10 && d.length < 6000) geo.cutPath = d;
    return geo;
}

const CMD_ARITY = { M: 2, L: 2, T: 2, S: 4, Q: 4, C: 6, A: 7, H: 1, V: 1, Z: 0 };

// Re-map an absolute/relative SVG path from template coordinates into the app's
// normalized 0–100 space relative to the trim box. Handles M L H V C S Q T A Z.
export function normalizePath(d, box) {
    const w = box.maxX - box.minX || 1;
    const h = box.maxY - box.minY || 1;
    const sx = 100 / w;
    const sy = 100 / h;
    const tokens = d.match(/[MmLlHhVvCcSsQqTtAaZz]|-?\d*\.?\d+(?:e-?\d+)?/g) || [];
    const out = [];
    let cmd = null;
    let buf = [];
    const r2 = (n) => +n.toFixed(2);

    const flush = () => {
        if (!cmd) return;
        const upper = cmd.toUpperCase();
        const rel = cmd !== upper;
        const n = CMD_ARITY[upper];
        if (n === 0) { out.push('Z'); buf = []; return; }
        for (let i = 0; i + n <= buf.length; i += n) {
            const seg = buf.slice(i, i + n).map(Number);
            let mapped;
            if (upper === 'H') mapped = [rel ? seg[0] * sx : (seg[0] - box.minX) * sx];
            else if (upper === 'V') mapped = [rel ? seg[0] * sy : (seg[0] - box.minY) * sy];
            else if (upper === 'A') {
                mapped = [seg[0] * sx, seg[1] * sy, seg[2], seg[3], seg[4],
                    rel ? seg[5] * sx : (seg[5] - box.minX) * sx,
                    rel ? seg[6] * sy : (seg[6] - box.minY) * sy];
            } else {
                mapped = seg.map((v, j) => (j % 2 === 0
                    ? (rel ? v * sx : (v - box.minX) * sx)
                    : (rel ? v * sy : (v - box.minY) * sy)));
            }
            out.push(cmd + ' ' + mapped.map(r2).join(' '));
        }
        buf = [];
    };

    for (const t of tokens) {
        if (/^[MmLlHhVvCcSsQqTtAaZz]$/.test(t)) { flush(); cmd = t; if (t.toUpperCase() === 'Z') { out.push('Z'); cmd = null; } }
        else buf.push(t);
    }
    flush();
    return out.join(' ');
}

// Parse a Vistaprint SVG print template: semantic layers <g id="Bleed|Trim|Safety|Fold|Cut">
// with <path d> coords in points; physical size in the width/height (cm) attrs.
// Returns { unit:'mm', width, height, bleed?, safety?, fold?, cutPath? } or null.
export function parseSvgTemplate(svg) {
    if (!svg || svg.length > 4_000_000) return null;
    const vbm = svg.match(/viewBox=["']([^"']+)["']/);
    if (!vbm) return null;
    const vbW = vbm[1].trim().split(/[\s,]+/).map(Number)[2];
    if (!vbW) return null;
    const toMm = (v, u) => (u === 'cm' ? v * 10 : u === 'in' ? v * 25.4 : u === 'pt' ? (v * 25.4) / 72 : v);
    const pm = svg.match(/\bwidth=["']([\d.]+)\s*(cm|mm|in|pt)["']/i);
    const mmPerUnit = pm ? toMm(parseFloat(pm[1]), pm[2].toLowerCase()) / vbW : 25.4 / 72;
    const mm = (n) => +(n * mmPerUnit).toFixed(2);

    const layer = (name) => {
        const m = svg.match(new RegExp(`<g[^>]*(?:id|class)=["'][^"']*\\b${name}\\b[^"']*["'][^>]*>([\\s\\S]*?)</g>`, 'i'));
        return m ? m[1] : null;
    };
    const pathOf = (inner) => {
        if (!inner) return null;
        const d = inner.match(/\bd=["']([^"']+)["']/);
        return d ? d[1] : null;
    };
    const box = (inner) => {
        if (!inner) return null;
        const ds = [...inner.matchAll(/\bd=["']([^"']+)["']/g)].map((x) => x[1]).join(' ');
        const pts = [...ds.matchAll(/([-\d.]+)\s*[, ]\s*([-\d.]+)/g)].map((x) => [+x[1], +x[2]]).filter((p) => isFinite(p[0]) && isFinite(p[1]));
        if (!pts.length) return null;
        const xs = pts.map((p) => p[0]); const ys = pts.map((p) => p[1]);
        return { minX: Math.min(...xs), maxX: Math.max(...xs), minY: Math.min(...ys), maxY: Math.max(...ys) };
    };

    const trimLayer = layer('Trim');
    const trim = box(trimLayer);
    if (!trim) return null;
    const bleed = box(layer('Bleed'));
    const safe = box(layer('Safety')) || box(layer('Safe'));
    const fold = box(layer('Fold'));
    const geo = { unit: 'mm', width: mm(trim.maxX - trim.minX), height: mm(trim.maxY - trim.minY) };
    if (bleed) geo.bleed = Math.max(0, mm(trim.minX - bleed.minX));
    if (safe) geo.safety = Math.max(0, mm(safe.minX - trim.minX));
    if (fold) {
        const vertical = (fold.maxX - fold.minX) <= (fold.maxY - fold.minY);
        geo.fold = [{ orientation: vertical ? 'vertical' : 'horizontal', position: mm((vertical ? (fold.minX + fold.maxX) / 2 : (fold.minY + fold.maxY) / 2) - (vertical ? trim.minX : trim.minY)) }];
    }

    // The REAL die-cut outline: a Cut layer if present, else a curved (non-rectangular)
    // Trim path — circles/ovals/die-cuts draw the shape right in the trim layer.
    const rawCut = pathOf(layer('Cut')) || pathOf(trimLayer);
    if (rawCut && /[CcQqAaSsTt]/.test(rawCut)) {
        try {
            const cut = normalizePath(rawCut, trim);
            if (cut && cut.length > 10 && cut.length < 4000) geo.cutPath = cut;
        } catch {}
    }

    return geo;
}
