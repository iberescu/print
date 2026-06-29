// Phase 4 — generate B2B business-card templates with Gemini, render via fabric.js,
// score with Gemini vision, and auto-fix-loop until score >= MIN_SCORE.
//
// Output (repo /templates): json/{id}.json (canonical fabric JSON), previews/{id}.png, index.json
// Run: $env:GEMINI_API_KEY='...'; $env:COUNT='200'; node src/gen-templates.mjs
import { chromium } from 'playwright';
import http from 'node:http';
import { readFile, writeFile, mkdir } from 'node:fs/promises';
import { existsSync } from 'node:fs';
import path from 'node:path';

const KEY = process.env.GEMINI_API_KEY;
const API = 'https://generativelanguage.googleapis.com/v1beta';
const TEXT_MODEL = process.env.GEMINI_TEXT_MODEL || 'gemini-3.5-flash';
const VISION_MODEL = process.env.GEMINI_VISION_MODEL || 'gemini-3.5-flash';
const IMAGE_MODEL = process.env.GEMINI_IMAGE_MODEL || 'gemini-3-pro-image'; // "nano banana 2"
const COUNT = Number(process.env.COUNT || 200);
const CONCURRENCY = Number(process.env.CONCURRENCY || 3);
const MIN_SCORE = Number(process.env.MIN_SCORE || 9);
const MAX_ITERS = Number(process.env.MAX_ITERS || 4);

const OUT = path.resolve('..', 'templates');
const JSON_DIR = path.join(OUT, 'json');
const PNG_DIR = path.join(OUT, 'previews');
const jsonPath = (id) => path.join(JSON_DIR, `${id}.json`);
const pngPath = (id) => path.join(PNG_DIR, `${id}.png`);

const FONTS = ['Montserrat', 'Inter', 'Bebas Neue', 'Oswald', 'Poppins', 'Playfair Display', 'Cormorant Garamond', 'DM Serif Display', 'Anton', 'Archivo Black', 'Raleway', 'Rubik', 'Nunito', 'Lora', 'Abril Fatface', 'Barlow Condensed', 'League Spartan', 'Space Grotesk', 'Urbanist', 'Libre Baskerville', 'Merriweather', 'Figtree', 'Manrope', 'Sora', 'Outfit', 'Rajdhani', 'Work Sans', 'Plus Jakarta Sans', 'Great Vibes', 'Pinyon Script', 'Pacifico', 'Caveat', 'Fredericka the Great'];
const FONT_GUIDE = `Montserrat=polished corporate; Inter=readable tech/body; Bebas Neue=bold poster headline; Oswald=condensed title; Poppins=friendly beauty/retail; Playfair Display=elegant luxury serif; Cormorant Garamond=refined boutique serif; DM Serif Display=stylish editorial serif; Anton=loud display; Archivo Black=strong headline; Raleway=airy premium; Rubik=rounded approachable; Nunito=soft friendly; Lora=readable serif/body; Abril Fatface=dramatic fashion serif; Barlow Condensed=narrow tech/fitness; League Spartan=bold branding; Space Grotesk=quirky creative/tech; Urbanist=clean corporate/body; Libre Baskerville=classic formal serif; Merriweather=robust serif/body; Figtree=friendly lifestyle; Manrope=minimal premium; Sora=digital AI/tech; Outfit=versatile modern; Rajdhani=futuristic gaming; Work Sans=neutral business/body; Plus Jakarta Sans=fresh youthful; Great Vibes/Pinyon Script/Pacifico/Caveat=scripts (accent word only); Fredericka the Great=decorative vintage`;
const INDUSTRIES = ['technology startup', 'law firm', 'design studio', 'medical clinic', 'financial advisory', 'real estate agency', 'marketing agency', 'construction company', 'restaurant', 'consulting firm', 'photography studio', 'fitness coaching', 'architecture firm', 'accounting firm', 'e-commerce brand', 'dental practice', 'beauty salon', 'auto repair shop', 'landscaping company', 'craft brewery'];
const LAYOUTS = ['left-aligned classic', 'centered minimal', 'split color block', 'left sidebar accent bar', 'top banner header', 'bold typographic', 'monogram-forward', 'geometric accent shapes'];
const MOODS = ['modern & clean', 'elegant & premium', 'bold & energetic', 'warm & approachable', 'corporate & trustworthy', 'creative & playful', 'minimal monochrome', 'vibrant single-accent'];

const briefFor = (i) =>
    `${MOODS[i % MOODS.length]} business card for a ${INDUSTRIES[i % INDUSTRIES.length]}, with a ${LAYOUTS[Math.floor(i / 2) % LAYOUTS.length]} layout. Design variation #${i + 1} — make it visually distinct.`;

const genPrompt = (brief, hasImage) => `Design a premium, professional B2B business card. Canvas 760x434 px, origin top-left.
Style brief: ${brief}${hasImage ? '\nA full-bleed BACKGROUND PHOTO sits behind everything under a dark overlay — use LIGHT (near-white) text colors and include an "overlay" key set to a dark translucent rgba like "rgba(10,20,15,0.55)".' : ''}
Choose fontHeading & fontBody that FIT the industry/mood, using ONLY these Google Fonts (guide):
${FONT_GUIDE}
Scripts (Great Vibes/Pinyon Script/Pacifico/Caveat) may be used for at most ONE accent word — never for body or contact text.
Return ONLY JSON:
{"style":"short label","background":"#hex","accent":"#hex",
 "fontHeading":<a font from the guide>,"fontBody":<a readable font from the guide>,
 "shapes":[{"type":"rect"|"line","left":int,"top":int,"width":int,"height":int,"x1":int,"y1":int,"x2":int,"y2":int,"fill":"#hex","rx":int,"opacity":number}],
 "elements":{
   "companyName":{"text":string,"left":int,"top":int,"fontSize":int,"fill":"#hex","weight":"bold"|"normal","align":"left"|"center"|"right","fontFamily":<a font>},
   "personName":{...},"title":{...},"email":{...},"phone":{...}},
 "logo":{"left":int,"top":int,"width":int,"height":int,"shape":"rect"|"circle"}}
Rules for a 9/10 premium look:
- Use 1-3 decorative SHAPES for visual interest: a full-height side accent bar, a top/bottom color block, or a thin divider line under the company name. Keep shapes within bounds; text renders on top.
- The LOGO renders as a SOLID accent-colored mark with the company monogram (NOT a wireframe). Place it cleanly (top-left/top-right), not overlapping text.
- Strong hierarchy: company name largest; group the contact lines; consistent alignment.
- HIGH contrast between each text fill and whatever is behind it (background OR a shape). Light text on dark accent bars.
- ~36-44px outer margins; balanced; no overlapping text blocks. All 6 placeholders present with realistic B2B copy.`;

const scorePrompt = () => `You are a professional print-design reviewer scoring a B2B business-card TEMPLATE.
The logo is shown as a monogram placeholder and the names/contact are sample copy the buyer will replace —
judge the DESIGN (layout, hierarchy, color, contrast, alignment, balance, tasteful accents), not the placeholder text.
Calibrated rubric:
 10  = exceptional, award-worthy.
 9   = professional & clean with NO notable flaws — this is the target for a good template.
 7-8 = solid, but a minor refinement or two is still possible.
 5-6 = acceptable but noticeably amateur (spacing/contrast/hierarchy issues).
 1-4 = broken: overlaps, illegible, or unbalanced.
A clean, balanced, well-contrasted, well-aligned card with no real problems SHOULD score 9.
Return ONLY JSON: {"score": number (1-10, .5 allowed), "issues": [short, specific, actionable]}`;

const fixPrompt = (brief, spec, issues) => `Improve this premium B2B business-card design spec. Brief: ${brief}
Reviewer issues to FIX: ${JSON.stringify(issues || [])}
Return ONLY improved JSON (same schema, including "shapes" and "logo.shape"). You MAY add/adjust decorative shapes (accent bar / color block / divider), reposition elements, change colors/fonts, and raise contrast & hierarchy. Keep within 760x434, ~40px margins, no text overlap, all 6 placeholders, monogram logo placed cleanly. Current spec:
${JSON.stringify(spec)}`;

async function gemini(model, prompt, imgBuf, temperature) {
    const parts = [{ text: prompt }];
    if (imgBuf) parts.push({ inlineData: { mimeType: 'image/png', data: imgBuf.toString('base64') } });
    const r = await fetch(`${API}/models/${model}:generateContent?key=${KEY}`, {
        method: 'POST',
        headers: { 'content-type': 'application/json' },
        body: JSON.stringify({ contents: [{ parts }], generationConfig: { responseMimeType: 'application/json', temperature } }),
    });
    const j = await r.json();
    const t = (j?.candidates?.[0]?.content?.parts || []).map((p) => p.text || '').join('') || '{}';
    return JSON.parse(t);
}

async function geminiImage(prompt) {
    const r = await fetch(`${API}/models/${IMAGE_MODEL}:generateContent?key=${KEY}`, {
        method: 'POST',
        headers: { 'content-type': 'application/json' },
        body: JSON.stringify({ contents: [{ parts: [{ text: prompt }] }], generationConfig: { responseModalities: ['IMAGE'] } }),
    });
    const j = await r.json();
    for (const p of j?.candidates?.[0]?.content?.parts || []) {
        const inl = p.inlineData || p.inline_data;
        if (inl?.data) return { data: inl.data, mime: inl.mimeType || inl.mime_type || 'image/png' };
    }
    throw new Error('no image returned');
}

const imagePromptFor = (brief) =>
    `Subtle, premium full-bleed background image for a business card. Theme: ${brief}. Muted, low-contrast, abstract or atmospheric; keep the center calm so overlaid text stays legible. No text, no logos, no faces, no watermark.`;

async function makeTemplate(page, i, index) {
    const id = String(i + 1).padStart(3, '0');
    if (existsSync(jsonPath(id))) return { id, skipped: true };
    const brief = briefFor(i);
    let image = null;
    if (i % 4 === 0) { // ~25% of templates get a generated background image (nano banana 2)
        try {
            const im = await geminiImage(imagePromptFor(brief));
            image = { data: `data:${im.mime};base64,${im.data}`, role: 'background' };
        } catch (e) { /* fall back to a shape-based design */ }
    }
    let spec = await gemini(TEXT_MODEL, genPrompt(brief, !!image), null, 0.95);
    let best = null;
    for (let iter = 1; iter <= MAX_ITERS; iter++) {
        const renderSpec = image ? { ...spec, image } : spec;
        const { png, json } = await page.evaluate((s) => window.buildAndRender(s), renderSpec);
        const buf = Buffer.from(png.split(',')[1], 'base64');
        let review = { score: 0, issues: [] };
        try { review = await gemini(VISION_MODEL, scorePrompt(), buf, 0.2); } catch (e) { review.issues = [String(e.message)]; }
        const score = Number(review.score) || 0;
        if (!best || score > best.score) best = { score, json, buf, iter, spec, issues: review.issues };
        if (score >= MIN_SCORE) break;
        if (iter < MAX_ITERS) {
            // hill-climb: always refine from the best version so far (avoids drift/regressions)
            try { spec = await gemini(TEXT_MODEL, fixPrompt(brief, best.spec, best.issues), null, 0.6); } catch {}
        }
    }
    await writeFile(jsonPath(id), JSON.stringify(best.json));
    await writeFile(pngPath(id), best.buf);
    const rec = { id, style: spec.style || brief, font: spec.fontHeading, score: best.score, iters: best.iter, reached: best.score >= MIN_SCORE };
    index.set(id, rec);
    return rec;
}

function serveDir() {
    const server = http.createServer(async (req, res) => {
        try {
            let p = decodeURIComponent((req.url || '/').split('?')[0]);
            if (p === '/' || p === '') p = '/render.html';
            const data = await readFile(path.join(process.cwd(), p));
            const ext = path.extname(p);
            res.writeHead(200, { 'content-type': ext === '.html' ? 'text/html' : 'application/octet-stream' });
            res.end(data);
        } catch { res.writeHead(404); res.end('not found'); }
    });
    return new Promise((resolve) => server.listen(0, () => resolve({ server, port: server.address().port })));
}

async function main() {
    if (!KEY) { console.error('GEMINI_API_KEY required'); process.exit(1); }
    await mkdir(JSON_DIR, { recursive: true });
    await mkdir(PNG_DIR, { recursive: true });

    const index = new Map();
    try { JSON.parse(await readFile(path.join(OUT, 'index.json'), 'utf8')).forEach((r) => index.set(r.id, r)); } catch {}

    const { server, port } = await serveDir();
    const url = `http://localhost:${port}/render.html`;
    const browser = await chromium.launch();
    const ctx = await browser.newContext({ viewport: { width: 800, height: 500 } });

    const pages = [];
    for (let k = 0; k < CONCURRENCY; k++) {
        const pg = await ctx.newPage();
        await pg.goto(url, { waitUntil: 'load' });
        await pg.waitForFunction('window.__ready === true', { timeout: 30000 });
        pages.push(pg);
    }

    let next = 0, done = 0;
    await Promise.all(pages.map(async (pg) => {
        while (true) {
            const i = next++;
            if (i >= COUNT) break;
            try {
                const r = await makeTemplate(pg, i, index);
                done++;
                if (!r.skipped) console.log(`[${done}] ${r.id} score=${r.score} iters=${r.iters} ${r.reached ? '✅' : '⚠️'} ${r.font} — ${r.style}`);
            } catch (e) { console.log(`[fail] #${i + 1}: ${e.message}`); }
        }
    }));

    await writeFile(path.join(OUT, 'index.json'), JSON.stringify([...index.values()], null, 2));
    await browser.close();
    server.close();
    const arr = [...index.values()];
    const reached = arr.filter((r) => r.reached).length;
    console.log(`\nDone. ${arr.length} templates, ${reached} scored >= ${MIN_SCORE}. -> templates/`);
}

main().catch((e) => { console.error('FATAL', e); process.exit(1); });
