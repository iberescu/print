<script setup>
import { ref, reactive, onMounted, onBeforeUnmount } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import * as fabric from 'fabric';
import AppLogo from '../Components/AppLogo.vue';
import LogoBuilder from '../Components/LogoBuilder.vue';

const props = defineProps({
    product: { type: Object, required: true },
    category: { type: Object, default: () => ({}) },
    mode: { type: String, default: 'design' },
    templates: { type: Array, default: () => [] },
    template: { type: String, default: null },
    project: { type: String, default: null },     // this editing session's project id (fresh unless resuming)
    savedDesign: { type: Object, default: null }, // the resumed project's design — restore instead of seeding
    canvas: { type: Object, default: () => ({}) },
    selection: { type: Object, default: () => ({}) },
});

// Canvas matches the product's print format (A4 flyer, business-card landscape, …)
// and includes print bleed: the full canvas is W×H, the trim (cut) box sits inset
// by `bleed`, and the safe area inset by `bleed + safety`.
const W = props.canvas?.w || 760;
const H = props.canvas?.h || 434;
const bleed = props.canvas?.bleed || 0;
const safety = props.canvas?.safety || 0;
const trimW = props.canvas?.trimW || W;
const trimH = props.canvas?.trimH || H;
const safeW = Math.max(0, trimW - 2 * safety);
const safeH = Math.max(0, trimH - 2 * safety);
// Filled bleed band = full canvas minus the trim rect (evenodd makes the trim a hole).
const guidePath = `M0 0H${W}V${H}H0Z M${bleed} ${bleed}h${trimW}v${trimH}h${-trimW}Z`;
const noPrint = props.canvas?.noPrint || []; // [{x,y,w,h,label}] (canvas px)
const fold = props.canvas?.fold || [];       // [{orientation,pos,label}] (canvas px)
// Die-cut / sewn edge: SVG path in normalized 0–100 coords relative to the trim box.
// Rendered via a nested <svg viewBox="0 0 100 100"> so no path math is needed.
const cutPath = props.canvas?.cut || null;
const isEmbroidery = props.product?.decoration === 'embroidery';
const isBusinessCard = props.category?.slug === 'business-cards';

const canvasEl = ref(null);
const stageEl = ref(null);
let canvas = null;
const side = ref('front');
const store = { front: null, back: null };
const uploaded = ref(false);
const saving = ref(false);
const fileInput = ref(null);
const showTemplates = ref(false);
// Replace-logo popup: opens on a click (not drag) on any logo image on the canvas.
const showLogoPopup = ref(false);
const logoPopupSrc = ref('');
const logoInput = ref(null);
let logoTarget = null;        // the fabric image the popup will replace
let logoClickCandidate = null; // pressed logo; cleared if the press turns into a drag

// AI logo builder modal (toolbar button, or "create with AI" from the popup)
const showLogoBuilder = ref(false);
const builderError = ref('');
let builderTarget = null;     // logo to replace when opened from the popup (null = insert new)
const applyingTpl = ref(false);
const showGuides = ref(true);

// Template/seed art is authored at the trim origin (0,0). Shift it into the trim
// area so the margins stay bleed, and grow any full-bleed colour block (a rect
// sitting on the trim edge) OUTWARD into the bleed — this never changes the
// trimmed result, it just prevents white slivers if the cut drifts.
function offsetByBleed() {
    if (!bleed) return;
    const eps = 2;
    const L = bleed, T = bleed, R = bleed + trimW, B = bleed + trimH;
    canvas.getObjects().forEach((o) => {
        o.left += bleed;
        o.top += bleed;
        if ((o.type || '').toLowerCase() === 'rect' && !o.angle) {
            const sx = o.scaleX || 1, sy = o.scaleY || 1;
            const w = o.width * sx, h = o.height * sy;
            const left = o.left, top = o.top;
            let nl = left, nt = top, nw = w, nh = h;
            if (Math.abs(left - L) <= eps) { nl = 0; nw += bleed; }
            if (Math.abs(top - T) <= eps) { nt = 0; nh += bleed; }
            if (Math.abs(left + w - R) <= eps) { nw += bleed; }
            if (Math.abs(top + h - B) <= eps) { nh += bleed; }
            if (nl !== left || nt !== top || nw !== w || nh !== h) {
                o.set({ left: nl, top: nt, width: nw / sx, height: nh / sy });
            }
        }
        o.setCoords();
    });
}

// Google Fonts only — curated set (loaded from fonts.googleapis.com below).
const fonts = ['Montserrat', 'Inter', 'Bebas Neue', 'Oswald', 'Poppins', 'Playfair Display', 'Cormorant Garamond', 'DM Serif Display', 'Anton', 'Archivo Black', 'Raleway', 'Rubik', 'Nunito', 'Lora', 'Abril Fatface', 'Barlow Condensed', 'League Spartan', 'Space Grotesk', 'Urbanist', 'Libre Baskerville', 'Merriweather', 'Figtree', 'Manrope', 'Sora', 'Outfit', 'Rajdhani', 'Work Sans', 'Plus Jakarta Sans', 'Great Vibes', 'Pinyon Script', 'Pacifico', 'Caveat', 'Fredericka the Great'];
const sizes = [12, 14, 16, 18, 20, 24, 28, 32, 40, 48, 56, 64, 72];
const palette = ['#0c1f17', '#2b3b55', '#c7f23d', '#ffffff', '#111111', '#c0392b', '#1f2a44', '#b0703a'];
const bgPalette = ['#ffffff', '#f8f6ef', '#0c1f17', '#2b3b55', '#1f2a44', '#e7dcc4', '#c0392b'];

const GOOGLE_FONTS_HREF =
    'https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&family=Inter:wght@400;600;700&family=Bebas+Neue&family=Oswald:wght@400;600;700&family=Poppins:wght@400;600;700&family=Playfair+Display:wght@400;700;900&family=Cormorant+Garamond:wght@400;600;700&family=DM+Serif+Display&family=Anton&family=Archivo+Black&family=Raleway:wght@400;600;700&family=Rubik:wght@400;600;700&family=Nunito:wght@400;600;700&family=Lora:wght@400;600;700&family=Abril+Fatface&family=Barlow+Condensed:wght@400;600;700&family=League+Spartan:wght@400;700&family=Space+Grotesk:wght@400;600;700&family=Urbanist:wght@400;600;700&family=Libre+Baskerville:wght@400;700&family=Merriweather:wght@400;700&family=Figtree:wght@400;600;700&family=Manrope:wght@400;600;700&family=Sora:wght@400;600;700&family=Outfit:wght@400;600;700&family=Rajdhani:wght@400;600;700&family=Work+Sans:wght@400;600;700&family=Plus+Jakarta+Sans:wght@400;600;700&family=Great+Vibes&family=Pinyon+Script&family=Pacifico&family=Caveat:wght@400;700&family=Fredericka+the+Great&display=swap';

function ensureFonts() {
    if (document.getElementById('rmp-google-fonts')) return;
    const link = document.createElement('link');
    link.id = 'rmp-google-fonts';
    link.rel = 'stylesheet';
    link.href = GOOGLE_FONTS_HREF;
    document.head.appendChild(link);
}

const hasSel = ref(false);
const isText = ref(false);
const sel = reactive({ text: '', fontFamily: 'Playfair Display', fontSize: 40, fill: '#0c1f17', bold: false, italic: false, align: 'left' });

function addText(text, opts = {}) {
    const t = new fabric.IText(text, {
        left: 56, top: 60, originX: 'left', originY: 'top', fontFamily: 'Work Sans', fill: '#0c1f17', fontSize: 22, ...opts,
    });
    canvas.add(t);
    return t;
}

// Drop the "your logo here" placeholder image at final canvas coords (inside the
// safe area). Async — the caller doesn't need to await it.
async function addLogoPlaceholder({ left, top, width, center = false }) {
    try {
        const img = await fabric.FabricImage.fromURL('/storage/brand/logo-placeholder.webp', { crossOrigin: 'anonymous' });
        img.scaleToWidth(width);
        img.set({ left, top, originX: center ? 'center' : 'left', originY: 'top', rmpRole: 'logo', hoverCursor: 'pointer' });
        canvas.add(img);
        canvas.requestRenderAll();
    } catch (e) { /* placeholder is optional */ }
}

function seedTemplate() {
    canvas.backgroundColor = '#f8f6ef';
    addText('Company Name', { left: 56, top: 58, fontSize: 32, fontWeight: 'bold', fontFamily: 'Playfair Display', rmpRole: 'companyName' });
    addText('Your Name', { left: 56, top: 110, fontSize: 22, fontFamily: 'Work Sans', rmpRole: 'name' });
    addText('Title / Role', { left: 56, top: 140, fontSize: 17, fill: '#2b3b55', fontFamily: 'Work Sans', rmpRole: 'title' });
    addText('yourcompany.com', { left: 56, top: 250, fontSize: 17, fontFamily: 'Work Sans', rmpRole: 'url' });
    addText('+1 (555) 123-4567', { left: 56, top: 282, fontSize: 17, fontFamily: 'Work Sans', rmpRole: 'phone' });
    offsetByBleed();
    canvas.renderAll();
    // logo placeholder, top-right, inside the safe area (final canvas coords)
    addLogoPlaceholder({ left: bleed + 560, top: bleed + 52, width: 150 });
}

// Generic starter for non-business-card formats (flyers, posters, signs, …),
// scaled to whatever canvas size the product's format produced.
function seedGeneric() {
    canvas.backgroundColor = '#ffffff';
    const cx = W / 2;
    addText('Your Headline', { left: cx, top: Math.round(H * 0.34), originX: 'center', textAlign: 'center', fontSize: Math.max(20, Math.round(W * 0.075)), fontWeight: 'bold', fontFamily: 'Poppins', fill: '#2b3b55', rmpRole: 'companyName' });
    addText('Add your message, details or call to action here.', { left: cx, top: Math.round(H * 0.49), originX: 'center', textAlign: 'center', fontSize: Math.max(13, Math.round(W * 0.032)), fontFamily: 'Work Sans', fill: '#16233b' });
    addText('yourcompany.com', { left: cx, top: Math.round(H * 0.64), originX: 'center', textAlign: 'center', fontSize: Math.max(12, Math.round(W * 0.028)), fontFamily: 'Work Sans', fill: '#647ba0', rmpRole: 'url' });
    canvas.renderAll();
    // logo placeholder, top-centre inside the safe area
    addLogoPlaceholder({ left: cx, top: Math.max(bleed + safety + 6, Math.round(H * 0.12)), width: Math.round(W * 0.24), center: true });
}

function syncSelection() {
    const o = canvas.getActiveObject();
    hasSel.value = !!o;
    isText.value = !!o && (o.type === 'i-text' || o.type === 'textbox');
    if (o && isText.value) {
        sel.text = o.text ?? '';
        sel.fontFamily = o.fontFamily || sel.fontFamily;
        sel.fontSize = o.fontSize || sel.fontSize;
        sel.fill = typeof o.fill === 'string' ? o.fill : sel.fill;
        sel.bold = o.fontWeight === 'bold' || Number(o.fontWeight) >= 700;
        sel.italic = o.fontStyle === 'italic';
        sel.align = o.textAlign || 'left';
    }
}

function fitCanvas() {
    if (!canvas || !stageEl.value) return;
    // Fit by BOTH width and height so portrait/tall formats (A4, banners) stay in view.
    const availW = Math.max(220, stageEl.value.clientWidth - 32);
    const availH = Math.max(220, stageEl.value.clientHeight - 120); // room for label + side toggle
    const scale = Math.min(1, availW / W, availH / H);
    // Scale both the canvas size AND the viewport zoom so object/pointer coords stay
    // correct (cssOnly scaling misaligns clicks). setZoom is the supported responsive path.
    canvas.setDimensions({ width: Math.round(W * scale), height: Math.round(H * scale) });
    canvas.setZoom(scale);
    canvas.requestRenderAll();
}

onMounted(() => {
    ensureFonts();

    // Bigger, touch-friendly selection handles — easier to grab/resize on mobile.
    if (fabric.InteractiveFabricObject?.ownDefaults) {
        Object.assign(fabric.InteractiveFabricObject.ownDefaults, {
            cornerSize: 13, touchCornerSize: 44, cornerStyle: 'circle',
            transparentCorners: false, cornerColor: '#2b3b55',
            cornerStrokeColor: '#ffffff', borderColor: '#2b3b55', padding: 4,
        });
    }

    canvas = new fabric.Canvas(canvasEl.value, { backgroundColor: '#ffffff', preserveObjectStacking: true });
    // Test seam: expose the fabric canvas only when ?test=1 (used by e2e/audit scripts; no-op in production).
    if (typeof window !== 'undefined' && new URLSearchParams(window.location.search).get('test') === '1') window.__rmpCanvas = canvas;
    canvas.setDimensions({ width: W, height: H });

    // No stretching: images and text scale from the corners only (uniform), so
    // aspect ratio always holds. The one-axis middle handles are hidden —
    // except a textbox's left/right, which re-flow the wrap width in fabric
    // rather than stretching glyphs. Shift must not unlock distortion either.
    canvas.uniformScaling = true;
    canvas.uniScaleKey = null;
    const keepAspect = (o) => {
        if (!o || typeof o.setControlsVisibility !== 'function') return;
        if (o.type === 'image' || o.type === 'i-text' || o.type === 'text') {
            o.setControlsVisibility({ ml: false, mr: false, mt: false, mb: false });
        } else if (o.type === 'textbox') {
            o.setControlsVisibility({ mt: false, mb: false });
        }
    };
    canvas.on('object:added', (e) => keepAspect(e.target));
    canvas.on('selection:created', syncSelection);
    canvas.on('selection:updated', syncSelection);
    canvas.on('selection:cleared', () => { hasSel.value = false; isText.value = false; });

    // A plain click on a logo (placeholder or uploaded) opens the replace popup;
    // any move/scale/rotate between press and release means the user was editing,
    // not asking to swap the image.
    canvas.on('mouse:down', (o) => {
        logoClickCandidate = o.target && o.target.rmpRole === 'logo' && o.target.type === 'image' ? o.target : null;
    });
    canvas.on('object:moving', () => { logoClickCandidate = null; });
    canvas.on('object:scaling', () => { logoClickCandidate = null; });
    canvas.on('object:rotating', () => { logoClickCandidate = null; });
    canvas.on('mouse:up', (o) => {
        if (logoClickCandidate && o.target === logoClickCandidate) openLogoPopup(logoClickCandidate);
        logoClickCandidate = null;
    });

    const hasSaved = !!(props.savedDesign?.front || props.savedDesign?.back);
    if (props.template) applyTemplate(props.template);
    else if (hasSaved) restoreSaved(); // back from Review — the buyer's work, not a fresh seed
    // shaped (die-cut) products get the CENTERED generic seed — the rectangular
    // business-card layout would fall outside the cut edge (e.g. circle cards)
    else if (props.mode === 'design') (isBusinessCard && !cutPath ? seedTemplate : seedGeneric)();
    else { canvas.backgroundColor = '#ffffff'; canvas.renderAll(); }

    fitCanvas();
    window.addEventListener('resize', fitCanvas);

    // Autosave + undo history arm AFTER the initial content lands (seed/template/
    // restore all fire object:added storms) — only user edits count from then on.
    setTimeout(() => {
        autosaveArmed = true;
        historyBaseline();
        ['object:added', 'object:removed', 'object:modified', 'text:changed'].forEach((ev) => canvas.on(ev, markDirty));
        ['object:added', 'object:removed', 'object:modified', 'text:changed'].forEach((ev) => canvas.on(ev, recordHistory));
    }, 2000);
    window.addEventListener('keydown', onHistoryKeys);

    // Seeds are measured before webfonts finish loading → load the fonts then re-fit
    // the text so a wider webfont isn't clipped. Templates and restored designs
    // keep their authored widths (both repaint after loading their fonts).
    if (typeof document !== 'undefined' && !props.template && !hasSaved) refitText();
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', fitCanvas);
    window.removeEventListener('keydown', onHistoryKeys);
    if (autosaveTimer) clearTimeout(autosaveTimer);
    if (historyTimer) clearTimeout(historyTimer);
    canvas && canvas.dispose();
});

// ---- undo / redo -------------------------------------------------------------
// Serialized-canvas snapshots after each user edit (debounced so one drag is
// one step). Programmatic loads (undo itself, templates, side flips) are
// suppressed; the side flip resets history to the new side's baseline.
let undoStack = [];
let redoStack = [];
let historySuppressed = false;
let historyTimer = null;
const canUndo = ref(false);
const canRedo = ref(false);

const historySnapshot = () => JSON.stringify(canvas.toJSON(['rmpRole']));

function historyBaseline() {
    undoStack = [historySnapshot()];
    redoStack = [];
    syncHistoryFlags();
}

function syncHistoryFlags() {
    canUndo.value = undoStack.length > 1;
    canRedo.value = redoStack.length > 0;
}

function recordHistory() {
    if (!autosaveArmed || historySuppressed || applyingTpl.value) return;
    if (historyTimer) clearTimeout(historyTimer);
    historyTimer = setTimeout(() => {
        const snap = historySnapshot();
        if (snap === undoStack[undoStack.length - 1]) return;
        undoStack.push(snap);
        if (undoStack.length > 50) undoStack.shift();
        redoStack = [];
        syncHistoryFlags();
    }, 300);
}

async function applyHistory(snap) {
    historySuppressed = true;
    try {
        await canvas.loadFromJSON(JSON.parse(snap));
        markLogos();
        canvas.discardActiveObject();
        canvas.renderAll();
        syncSelection();
        markDirty(); // an undo is a change worth autosaving
    } finally {
        // let the load's object:added storm drain before re-enabling capture
        setTimeout(() => (historySuppressed = false), 50);
    }
}

function undo() {
    if (undoStack.length < 2) return;
    redoStack.push(undoStack.pop());
    syncHistoryFlags();
    applyHistory(undoStack[undoStack.length - 1]);
}

function redo() {
    if (!redoStack.length) return;
    const snap = redoStack.pop();
    undoStack.push(snap);
    syncHistoryFlags();
    applyHistory(snap);
}

function onHistoryKeys(e) {
    const mod = e.ctrlKey || e.metaKey;
    if (!mod) return;
    // never steal keys from form fields or in-canvas text editing
    const tag = (document.activeElement?.tagName || '').toLowerCase();
    if (['input', 'textarea', 'select'].includes(tag)) return;
    if (canvas?.getActiveObject()?.isEditing) return;
    if (e.key.toLowerCase() === 'z' && !e.shiftKey) { e.preventDefault(); undo(); }
    else if (e.key.toLowerCase() === 'y' || (e.key.toLowerCase() === 'z' && e.shiftKey)) { e.preventDefault(); redo(); }
}
// -------------------------------------------------------------------------------

// ---- autosave ---------------------------------------------------------------
// Debounced best-effort save so the design lands in "My designs" without ever
// reaching Review. Plain fetch — an Inertia visit would remount the editor.
let autosaveArmed = false;
let autosaveTimer = null;

function markDirty() {
    if (!autosaveArmed || applyingTpl.value) return;
    if (autosaveTimer) clearTimeout(autosaveTimer);
    autosaveTimer = setTimeout(autosave, 2500);
}

function autosave() {
    autosaveTimer = null;
    try {
        store[side.value] = canvas.toJSON(['rmpRole']);
        // small card preview; Review later overwrites it with the 1600px one
        const preview = canvas.toDataURL({ format: 'jpeg', quality: 0.7, multiplier: Math.min(4, Math.max(0.3, 640 / canvas.getWidth())) });
        const token = decodeURIComponent((document.cookie.match(/XSRF-TOKEN=([^;]+)/) || [])[1] || '');
        fetch(`/design/${props.product.slug}/autosave`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': token },
            credentials: 'same-origin',
            body: JSON.stringify({ design: JSON.stringify(store), project: props.project, preview }),
        }).catch(() => {}); // best-effort — Review still does the authoritative save
    } catch (e) { /* never let autosave break editing */ }
}
// -----------------------------------------------------------------------------

function apply(prop, val) {
    const o = canvas.getActiveObject();
    if (!o) return;
    o.set(prop, val);
    canvas.requestRenderAll();
}
const setFont = async (f) => {
    sel.fontFamily = f;
    const o = canvas.getActiveObject();
    if (!o) return;
    o.set('fontFamily', f);
    o.dirty = true;
    canvas.requestRenderAll();
    // Load the webfont, then resize the text box to the REAL metrics. Changing to a
    // wider font otherwise clips the text: fabric's offscreen measuring canvas keeps
    // reporting the fallback width for a just-loaded webfont, so the object stays too
    // narrow and the render cache cuts the overflow. fitTextWidth() measures with an
    // on-DOM canvas (which does see the loaded font) and sets the width explicitly.
    try {
        await Promise.all([document.fonts.load(`400 40px "${f}"`), document.fonts.load(`700 40px "${f}"`)]);
    } catch (e) { /* keep fallback */ }
    fitTextWidth(o);
    canvas.requestRenderAll();
};

// Resize a text object's box to fit its text in the currently-loaded font. fabric's
// own metrics cache can miss a late-loaded webfont (leaving a wider font clipped), so
// we measure each line with a real canvas 2D context and set the width ourselves.
function fitTextWidth(o) {
    if (!o || !['i-text', 'text', 'textbox'].includes(o.type)) return;
    if (typeof o.initDimensions === 'function') o.initDimensions(); // recompute line heights
    if (o.type !== 'textbox') { // textbox has a fixed width and wraps — don't override it
        try {
            const ctx = document.createElement('canvas').getContext('2d');
            const style = o.fontStyle && o.fontStyle !== 'normal' ? `${o.fontStyle} ` : '';
            const weight = o.fontWeight && o.fontWeight !== 'normal' ? `${o.fontWeight} ` : '';
            ctx.font = `${style}${weight}${o.fontSize}px "${o.fontFamily}"`;
            const gap = ((o.charSpacing || 0) / 1000) * o.fontSize;
            let max = 0;
            for (const line of String(o.text ?? '').split('\n')) {
                const w = ctx.measureText(line).width + Math.max(0, line.length - 1) * gap;
                if (w > max) max = w;
            }
            if (max > 0) o.set('width', max);
        } catch (e) { /* keep fabric's width */ }
    }
    o.setCoords();
    o.dirty = true;
}

// Force every text object to re-render (used after webfonts finish loading).
function repaintFonts() {
    if (!canvas) return;
    canvas.getObjects().forEach((o) => { o.dirty = true; });
    canvas.requestRenderAll();
}

// Load every webfont currently used on the canvas (both weights). We can't rely on
// document.fonts.ready — for dynamically-added @font-faces it can resolve BEFORE the
// specific fonts finish, so we load each family explicitly.
async function loadCanvasFonts() {
    if (!canvas || !document.fonts) return;
    const families = [...new Set(canvas.getObjects().map((o) => o.fontFamily).filter(Boolean))];
    await Promise.all(families.flatMap((f) => [
        document.fonts.load(`400 40px "${f}"`).catch(() => {}),
        document.fonts.load(`700 40px "${f}"`).catch(() => {}),
    ]));
}

// Re-fit seeded text to its real font metrics after webfonts load. The seed runs
// before the fonts finish downloading, so text is measured with the fallback; a
// wider webfont then overflows that stale width and clips. Refitting fixes it.
// (Template designs keep their authored widths — repaintFonts only.)
async function refitText() {
    if (!canvas) return;
    await loadCanvasFonts();
    if (!canvas) return; // may have unmounted while awaiting
    canvas.getObjects().forEach((o) => {
        if (['i-text', 'text', 'textbox'].includes(o.type)) fitTextWidth(o);
        else o.dirty = true;
    });
    canvas.requestRenderAll();
}
const setSize = (s) => { sel.fontSize = Number(s); apply('fontSize', Number(s)); };
const setColor = (c) => { sel.fill = c; apply('fill', c); };
const toggleBold = () => { sel.bold = !sel.bold; apply('fontWeight', sel.bold ? 'bold' : 'normal'); };
const toggleItalic = () => { sel.italic = !sel.italic; apply('fontStyle', sel.italic ? 'italic' : 'normal'); };
const setAlign = (a) => { sel.align = a; apply('textAlign', a); };
const setText = (v) => { sel.text = v; apply('text', v); };
const setBg = (c) => { canvas.backgroundColor = c; canvas.requestRenderAll(); };

function newText() {
    const t = addText('New text', { left: 80, top: 200, fontSize: 28 });
    canvas.setActiveObject(t);
    canvas.requestRenderAll();
    syncSelection();
}
function removeSel() {
    const o = canvas.getActiveObject();
    if (o) { canvas.remove(o); canvas.discardActiveObject(); canvas.requestRenderAll(); }
}

// ---- replace-logo popup -----------------------------------------------------
const logoIsPlaceholder = () => logoPopupSrc.value.includes('logo-placeholder');

function openLogoPopup(target) {
    logoTarget = target;
    logoPopupSrc.value = target.getSrc?.() || target._originalElement?.src || '';
    showLogoPopup.value = true;
}

function closeLogoPopup() {
    showLogoPopup.value = false;
    logoTarget = null;
}

// Swap the clicked logo for the chosen file, fitted inside the old logo's box so
// the design's layout is preserved (the customer can still resize afterwards).
async function onLogoFile(e) {
    const file = e.target.files?.[0];
    e.target.value = '';
    if (!file || !logoTarget) return;
    uploadArtwork(file); // fire-and-forget; no-op outside upload mode
    let url = await new Promise((res) => { const r = new FileReader(); r.onload = (ev) => res(ev.target.result); r.readAsDataURL(file); });
    // Big raster logos (phone photos…) blow the review payload cap and the
    // brand capture silently drops them — downscale to what print needs.
    // SVGs stay as-is (tiny text; the server rasterises them at Review).
    if (file.size > 1_500_000 && !url.startsWith('data:image/svg')) {
        url = await downscaleLogo(url).catch(() => url);
    }
    await swapLogoWith(url, logoTarget);
    closeLogoPopup();
}

async function downscaleLogo(dataUrl, max = 1024) {
    const img = await new Promise((res, rej) => { const i = new Image(); i.onload = () => res(i); i.onerror = rej; i.src = dataUrl; });
    const s = Math.min(1, max / Math.max(img.width, img.height));
    if (s >= 1) return dataUrl;
    const c = document.createElement('canvas');
    c.width = Math.round(img.width * s);
    c.height = Math.round(img.height * s);
    c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
    return c.toDataURL('image/png');
}

// Swap `target` for the image at `url`, fitted inside the old logo's box.
async function swapLogoWith(url, target) {
    try {
        const img = await fabric.FabricImage.fromURL(url, { crossOrigin: 'anonymous' });
        const old = target;
        const c = old.getCenterPoint();
        const s = Math.min(old.getScaledWidth() / img.width, old.getScaledHeight() / img.height);
        img.set({ left: c.x, top: c.y, originX: 'center', originY: 'center', angle: old.angle || 0, scaleX: s, scaleY: s, rmpRole: 'logo', hoverCursor: 'pointer' });
        const idx = canvas.getObjects().indexOf(old);
        canvas.remove(old);
        canvas.add(img);
        if (idx > -1 && typeof canvas.moveObjectTo === 'function') canvas.moveObjectTo(img, idx);
        canvas.setActiveObject(img);
        canvas.requestRenderAll();
    } catch (err) { /* unloadable image — keep the old logo */ }
}

// ---- AI logo builder --------------------------------------------------------
function openLogoBuilder(fromPopup = false) {
    builderTarget = fromPopup ? logoTarget : null;
    showLogoPopup.value = false; // keep logoTarget out of closeLogoPopup's reset
    builderError.value = '';
    showLogoBuilder.value = true;
}

async function useGeneratedLogo(logo) {
    builderError.value = '';
    try {
        // The server rasterises the SVG to a transparent PNG (mutool) — the old
        // in-browser SVG→canvas conversion fails silently on older iOS WebKit.
        const pngUrl = `/logo-maker/png?path=${encodeURIComponent(logo.path)}`;
        // prefer the design's logo slot: explicit popup target, else the untouched
        // placeholder — only a design with no logo slot gets a fresh insert
        const target = builderTarget
            ?? canvas.getObjects().find((o) => o.rmpRole === 'logo' && (o.getSrc?.() || '').includes('logo-placeholder'));
        if (target) {
            await swapLogoWith(pngUrl, target);
        } else {
            // fresh insert: centred, ~30% of the canvas width, inside the design
            const img = await fabric.FabricImage.fromURL(pngUrl, { crossOrigin: 'anonymous' });
            const s = (canvas.getWidth() * 0.3) / img.width;
            img.set({ left: canvas.getWidth() / 2, top: canvas.getHeight() / 2, originX: 'center', originY: 'center', scaleX: s, scaleY: s, rmpRole: 'logo', hoverCursor: 'pointer' });
            canvas.add(img);
            canvas.setActiveObject(img);
            canvas.requestRenderAll();
        }
    } catch (err) {
        // keep the modal open so they can retry — but say why, a silent
        // nothing-happens is indistinguishable from a broken button
        builderError.value = "We couldn't place that logo — please try again or pick another concept.";
        return;
    }
    builderTarget = null;
    logoTarget = null;
    showLogoBuilder.value = false;
}
// -----------------------------------------------------------------------------

function removeLogo() {
    if (logoTarget) { canvas.remove(logoTarget); canvas.discardActiveObject(); canvas.requestRenderAll(); }
    closeLogoPopup();
}
// -----------------------------------------------------------------------------

// Hand uploaded artwork to the backend: the upsell engine gets it (pdf_url /
// logo_url), and PDFs come back as rendered page images for the canvas.
function uploadArtwork(file) {
    if (props.mode !== 'upload') return Promise.resolve(null);
    try {
        const token = decodeURIComponent((document.cookie.match(/XSRF-TOKEN=([^;]+)/) || [])[1] || '');
        const form = new FormData();
        form.append('file', file);
        return fetch('/pqsg/upload', {
            method: 'POST',
            body: form,
            credentials: 'same-origin',
            headers: { 'X-XSRF-TOKEN': token, Accept: 'application/json' },
        }).then((r) => (r.ok ? r.json() : null)).catch(() => null);
    } catch (e) { return Promise.resolve(null); }
}

// Place rendered PDF pages: page 1 full-bleed, the rest cascaded smaller — all
// normal fabric objects the customer can drag, scale or delete into position.
async function placePdfPages(pages) {
    for (let i = 0; i < pages.length; i++) {
        try {
            const img = await fabric.FabricImage.fromURL(pages[i], { crossOrigin: 'anonymous' });
            if (i === 0) {
                img.scaleToWidth(W);
                img.set({ left: 0, top: 0, selectable: true });
            } else {
                img.scaleToWidth(Math.round(W * 0.38));
                img.set({ left: bleed + 24 + (i - 1) * 32, top: bleed + 24 + (i - 1) * 32 });
            }
            canvas.add(img);
        } catch (e) { /* skip unloadable page */ }
    }
    canvas.requestRenderAll();
}

async function onFile(e) {
    const file = e.target.files?.[0];
    if (!file) return;

    // PDFs: the backend renders pages with MuPDF; we place them for positioning.
    if (/pdf$/i.test(file.type) || /\.pdf$/i.test(file.name)) {
        if (props.mode === 'upload') {
            uploaded.value = true;
            const resp = await uploadArtwork(file);
            const pages = resp?.pages || [];
            if (pages.length) {
                await placePdfPages(pages);
            } else {
                const note = addText(`PDF artwork attached:\n${file.name}\nWe print from your original file.`, {
                    left: W / 2, top: H / 2, originX: 'center', originY: 'center',
                    textAlign: 'center', fontSize: Math.max(16, Math.round(W * 0.03)), fill: '#647ba0',
                });
                canvas.setActiveObject(note); canvas.requestRenderAll();
            }
        }
        e.target.value = '';
        return;
    }

    uploadArtwork(file); // images: fire-and-forget (canvas uses the local file below)

    const url = await new Promise((res) => { const r = new FileReader(); r.onload = (ev) => res(ev.target.result); r.readAsDataURL(file); });
    const img = await fabric.FabricImage.fromURL(url, { crossOrigin: 'anonymous' });
    if (props.mode === 'upload' && !uploaded.value) {
        img.scaleToWidth(W); img.set({ left: 0, top: 0, selectable: true }); uploaded.value = true;
    } else {
        // same box as the seeded placeholder — inside the safe area (560,60 sat
        // above the safe guide and could cross the trim line once bleed > 0)
        img.scaleToWidth(150); img.set({ left: bleed + 560, top: bleed + 52, rmpRole: 'logo', hoverCursor: 'pointer' });
    }
    canvas.add(img); canvas.setActiveObject(img); canvas.requestRenderAll();
    e.target.value = '';
}

// Logos keep their role (and click-to-replace cursor) across side flips and
// template loads — toJSON() drops custom props unless they're listed explicitly.
function markLogos() {
    canvas.getObjects().forEach((o) => { if (o.rmpRole === 'logo' && o.type === 'image') o.hoverCursor = 'pointer'; });
}

function flip(to) {
    if (to === side.value) return;
    store[side.value] = canvas.toJSON(['rmpRole']);
    side.value = to;
    historySuppressed = true;
    canvas.clear();
    const done = () => { historySuppressed = false; historyBaseline(); };
    if (store[to]) canvas.loadFromJSON(store[to]).then(() => { markLogos(); canvas.renderAll(); done(); });
    else { canvas.backgroundColor = '#f8f6ef'; canvas.renderAll(); done(); }
}

async function applyTemplate(ref) {
    applyingTpl.value = true;
    try {
        const res = await fetch(`/design/template/${ref}/data`, { headers: { Accept: 'application/json' } });
        const { data } = await res.json();
        await canvas.loadFromJSON(data);
        offsetByBleed(); // template art is authored at trim origin; nudge it inside the bleed
        markLogos();
        canvas.requestRenderAll();
        store[side.value] = canvas.toJSON(['rmpRole']);
        showTemplates.value = false;
        // repaint once fonts settle (non-blocking, so the design shows immediately)
        loadCanvasFonts().then(() => repaintFonts());
    } catch (e) { console.error('applyTemplate failed', e); }
    applyingTpl.value = false;
}

function extractBrand() {
    const b = { companyName: '', name: '', title: '', email: '', phone: '', url: '', logo: null };
    const objs = canvas.getObjects();
    objs.forEach((o) => {
        if (o.rmpRole && Object.prototype.hasOwnProperty.call(b, o.rmpRole) && (o.type === 'i-text' || o.type === 'textbox')) {
            b[o.rmpRole] = o.text;
        }
        if (o.rmpRole === 'logo' && o.type === 'image') {
            // the seeded "YOUR LOGO HERE" placeholder is not the customer's logo
            const src = o.getSrc?.() || o._originalElement?.src || '';
            if (!src.includes('logo-placeholder')) {
                try { b.logo = o.toDataURL({ format: 'png' }); } catch (e) { /* tainted/none */ }
            }
        }
    });
    if (!b.logo) {
        // any customer image can stand in for a logo — but never the seeded placeholder
        const img = objs.find((o) => o.type === 'image'
            && !((o.getSrc?.() || o._originalElement?.src || '').includes('logo-placeholder')));
        if (img) { try { b.logo = img.toDataURL({ format: 'png' }); } catch (e) {} }
    }
    return b;
}

// Back from Review: rehydrate both sides from the server-stored fabric JSON.
// Coordinates are already canvas-space (no bleed offset), exactly like flip().
async function restoreSaved() {
    try {
        store.front = props.savedDesign.front || null;
        store.back = props.savedDesign.back || null;
        const start = store[side.value] || store.front;
        if (!start) return;
        await canvas.loadFromJSON(start);
        markLogos();
        canvas.renderAll();
        loadCanvasFonts().then(() => repaintFonts());
    } catch (e) { console.error('restoreSaved failed', e); }
}

function goToReview() {
    saving.value = true;
    store[side.value] = canvas.toJSON(['rmpRole']);
    // Export at a fixed design resolution: the canvas itself is fitted to the
    // viewport (tiny on phones), so a flat multiplier produced blurry review
    // previews. getWidth() is the FITTED width — compensate to ~1600px.
    const preview = canvas.toDataURL({ format: 'jpeg', quality: 0.82, multiplier: Math.min(8, Math.max(0.5, 1600 / canvas.getWidth())) });
    router.post(`/design/${props.product.slug}/review`, {
        quantityId: props.selection?.quantityId ?? null,
        optionValueIds: props.selection?.optionValueIds ?? [],
        preview,
        design: JSON.stringify(store), // server keeps it so "Back to editor" can resume
        project: props.project,
        brand: extractBrand(),
        mode: props.mode,
    }, { onFinish: () => (saving.value = false) });
}
</script>

<template>
    <Head :title="`Design — ${product.name}`" />
    <div class="flex min-h-screen flex-col bg-paper-200">
        <header class="flex items-center justify-between gap-4 border-b border-paper-300 bg-paper px-5 py-3">
            <div class="flex items-center gap-4">
                <Link href="/"><AppLogo /></Link>
                <div class="hidden h-6 w-px bg-paper-300 sm:block"></div>
                <div class="hidden sm:block">
                    <p class="text-sm font-semibold text-ink">{{ product.name }}</p>
                    <p class="text-xs text-ink/50">{{ category.name }}<span v-if="props.canvas?.label"> · {{ props.canvas.label }}</span></p>
                </div>
            </div>
            <div class="hidden items-center gap-2 text-sm font-medium sm:flex">
                <span class="rounded-full bg-brand-50 px-3 py-1 text-brand-700">1 · Design</span>
                <span class="text-ink/30">2 · Review</span>
            </div>
            <button :disabled="saving" class="rounded-full bg-brand-600 px-6 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-700 disabled:opacity-60" @click="goToReview">
                {{ saving ? 'Saving…' : 'Review →' }}
            </button>
        </header>

        <div class="flex flex-wrap items-center gap-2 bg-ink px-4 py-2 text-paper">
            <button class="rounded-md px-2.5 py-1.5 text-sm font-medium hover:bg-white/10 disabled:opacity-30" :disabled="!canUndo" title="Undo (Ctrl+Z)" aria-label="Undo" @click="undo">↶</button>
            <button class="rounded-md px-2.5 py-1.5 text-sm font-medium hover:bg-white/10 disabled:opacity-30" :disabled="!canRedo" title="Redo (Ctrl+Y)" aria-label="Redo" @click="redo">↷</button>
            <div class="mx-1 h-6 w-px bg-white/15"></div>
            <button class="rounded-md px-3 py-1.5 text-sm font-medium hover:bg-white/10" @click="newText">+ Text</button>
            <button class="rounded-md px-3 py-1.5 text-sm font-medium hover:bg-white/10" @click="fileInput.click()">↑ Upload image</button>
            <button class="rounded-md px-3 py-1.5 text-sm font-medium text-[#9cc6ff] hover:bg-white/10" @click="openLogoBuilder(false)">✦ AI Logo</button>
            <button v-if="templates.length" class="rounded-md px-3 py-1.5 text-sm font-medium hover:bg-white/10" @click="showTemplates = true">▦ Templates</button>
            <button class="rounded-md px-3 py-1.5 text-sm font-medium hover:bg-white/10 disabled:opacity-30" :disabled="!hasSel" @click="removeSel">🗑 Delete</button>
            <button v-if="bleed || safety || cutPath" class="rounded-md px-3 py-1.5 text-sm font-medium hover:bg-white/10" :class="showGuides ? 'bg-white/15' : ''" @click="showGuides = !showGuides" title="Show print bleed &amp; safe-area guides">▣ Guides</button>
            <input ref="fileInput" type="file" :accept="mode === 'upload' ? 'image/*,application/pdf' : 'image/*'" class="hidden" @change="onFile" />

            <div class="mx-1 h-6 w-px bg-white/15"></div>

            <template v-if="isText">
                <input :value="sel.text" @input="setText($event.target.value)" placeholder="Edit text"
                       class="w-32 shrink-0 rounded-md bg-white/10 px-2 py-1.5 text-sm text-paper placeholder:text-paper/40 focus:outline-none sm:w-44" />
                <select :value="sel.fontFamily" class="rounded-md bg-white/10 px-2 py-1.5 text-sm focus:outline-none" @change="setFont($event.target.value)">
                    <option v-for="f in fonts" :key="f" :value="f" class="text-ink">{{ f }}</option>
                </select>
                <select :value="sel.fontSize" class="rounded-md bg-white/10 px-2 py-1.5 text-sm focus:outline-none" @change="setSize($event.target.value)">
                    <option v-for="s in sizes" :key="s" :value="s" class="text-ink">{{ s }}</option>
                </select>
                <label class="flex items-center gap-1.5 rounded-md bg-white/10 px-2 py-1">
                    <span class="inline-block h-4 w-4 rounded" :style="{ backgroundColor: sel.fill }"></span>
                    <input type="color" :value="sel.fill" class="h-5 w-6 cursor-pointer border-0 bg-transparent p-0" @input="setColor($event.target.value)" />
                </label>
                <button class="h-8 w-8 rounded-md font-bold hover:bg-white/10" :class="sel.bold ? 'bg-white/20' : ''" @click="toggleBold">B</button>
                <button class="h-8 w-8 rounded-md italic hover:bg-white/10" :class="sel.italic ? 'bg-white/20' : ''" @click="toggleItalic">I</button>
                <div class="flex overflow-hidden rounded-md bg-white/10">
                    <button v-for="a in ['left','center','right']" :key="a" class="px-2.5 py-1.5 text-xs hover:bg-white/15" :class="sel.align === a ? 'bg-white/25' : ''" @click="setAlign(a)">
                        {{ a === 'left' ? '⯇' : a === 'center' ? '≡' : '⯈' }}
                    </button>
                </div>
            </template>
            <span v-else class="px-2 text-sm text-paper/45">Select an element to edit its text style</span>
        </div>

        <div ref="stageEl" class="relative flex flex-1 flex-col items-center justify-center gap-4 overflow-auto p-4 sm:gap-6 sm:p-8">
            <div class="flex flex-col items-center gap-2">
                <p class="text-sm font-medium text-ink/50">
                    {{ side === 'front' ? 'Front' : 'Back' }} design<span class="hidden text-ink/40 sm:inline"> · every copy will print exactly like this</span>
                </p>
                <p v-if="isEmbroidery" class="rounded-full bg-amber-100 px-4 py-1.5 text-[11px] font-medium text-amber-900 sm:text-xs">
                    🧵 Embroidery — bold shapes stitch best · up to 6 thread colours · avoid fine text under ~5&nbsp;mm
                </p>
                <div v-if="(bleed || safety || noPrint.length || fold.length || cutPath) && showGuides" class="flex flex-wrap items-center justify-center gap-x-3 gap-y-1 text-[11px] text-ink/60 sm:gap-x-4 sm:text-xs">
                    <span v-if="bleed" class="flex items-center gap-1.5"><span class="inline-block h-3 w-4 bg-rose-500/15 ring-1 ring-rose-500/60"></span>Bleed<span class="hidden sm:inline"> — trimmed off</span></span>
                    <span v-if="cutPath" class="flex items-center gap-1.5"><span class="inline-block h-0 w-5 border-t-2 border-rose-500"></span>Die-cut<span class="hidden sm:inline"> / sewn edge — finished shape</span></span>
                    <span v-else-if="isEmbroidery" class="flex items-center gap-1.5"><span class="inline-block h-0 w-5 border-t-2 border-rose-500"></span>Embroidery area<span class="hidden sm:inline"> — max stitch zone</span></span>
                    <span v-else class="flex items-center gap-1.5"><span class="inline-block h-0 w-5 border-t-2 border-rose-500"></span>Trim<span class="hidden sm:inline"> / cut line</span></span>
                    <span class="flex items-center gap-1.5"><span class="inline-block h-0 w-5 border-t-2 border-dashed border-sky-500"></span>Safe<span class="hidden sm:inline"> area — keep text &amp; logos inside</span></span>
                    <span v-if="noPrint.length" class="flex items-center gap-1.5"><span class="inline-block h-3 w-4 bg-slate-800/40 ring-1 ring-slate-800"></span>No-print<span class="hidden sm:inline"> area</span></span>
                    <span v-if="fold.length" class="flex items-center gap-1.5"><span class="inline-block h-0 w-5 border-t-2 border-dashed border-purple-600"></span>Fold<span class="hidden sm:inline"> line</span></span>
                </div>
            </div>
            <div class="relative">
                <div class="overflow-hidden rounded-2xl bg-white shadow-[0_30px_60px_-25px_rgba(12,31,23,0.5)] ring-1 ring-paper-300">
                    <canvas ref="canvasEl"></canvas>
                </div>
                <!-- print guides: bleed band (red tint) · trim/cut line (solid) · safe area (dashed) -->
                <svg v-if="(bleed || safety || noPrint.length || fold.length || cutPath) && showGuides" class="pointer-events-none absolute inset-0 h-full w-full" :viewBox="`0 0 ${W} ${H}`" preserveAspectRatio="none">
                    <path v-if="bleed" :d="guidePath" fill="rgba(225,29,72,0.12)" fill-rule="evenodd" />
                    <template v-if="cutPath">
                        <!-- die-cut / sewn edge (normalized 0–100 path scaled onto the trim box);
                             inner scaled clone approximates the safe/stitch margin -->
                        <svg :x="bleed" :y="bleed" :width="trimW" :height="trimH" viewBox="0 0 100 100" preserveAspectRatio="none" class="overflow-visible">
                            <path :d="cutPath" fill="rgba(225,29,72,0.05)" stroke="#e11d48" stroke-width="1.5" vector-effect="non-scaling-stroke" />
                            <path v-if="safety" :d="cutPath" fill="none" stroke="#0ea5e9" stroke-width="1.5" stroke-dasharray="4 3" vector-effect="non-scaling-stroke" transform="translate(50 50) scale(0.9) translate(-50 -50)" />
                        </svg>
                    </template>
                    <template v-else>
                        <rect :x="bleed" :y="bleed" :width="trimW" :height="trimH" fill="none" stroke="#e11d48" stroke-width="1.5" />
                        <rect v-if="safety" :x="bleed + safety" :y="bleed + safety" :width="safeW" :height="safeH" fill="none" stroke="#0ea5e9" stroke-width="1.5" stroke-dasharray="7 5" />
                    </template>
                    <!-- no-print zones -->
                    <g v-for="(z, i) in noPrint" :key="'np' + i">
                        <rect :x="z.x" :y="z.y" :width="z.w" :height="z.h" fill="rgba(15,23,42,0.42)" stroke="#0f172a" stroke-width="1" stroke-dasharray="4 3" />
                        <text :x="z.x + 5" :y="z.y + 15" fill="#ffffff" font-size="11" font-family="sans-serif">{{ z.label }}</text>
                    </g>
                    <!-- fold lines -->
                    <g v-for="(f, i) in fold" :key="'fold' + i">
                        <line v-if="f.orientation === 'vertical'" :x1="f.pos" :y1="bleed" :x2="f.pos" :y2="bleed + trimH" stroke="#9333ea" stroke-width="1.5" stroke-dasharray="2 4" />
                        <line v-else :x1="bleed" :y1="f.pos" :x2="bleed + trimW" :y2="f.pos" stroke="#9333ea" stroke-width="1.5" stroke-dasharray="2 4" />
                        <text v-if="f.orientation === 'vertical'" :x="f.pos + 4" :y="bleed + 15" fill="#9333ea" font-size="11" font-family="sans-serif">{{ f.label }}</text>
                        <text v-else :x="bleed + 4" :y="f.pos - 5" fill="#9333ea" font-size="11" font-family="sans-serif">{{ f.label }}</text>
                    </g>
                </svg>
                <div v-if="mode === 'upload' && !uploaded" class="absolute inset-0 flex flex-col items-center justify-center gap-3 rounded-2xl bg-white/85 text-center backdrop-blur-sm">
                    <svg class="h-10 w-10 text-brand-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 16V4m0 0L8 8m4-4 4 4M5 20h14" stroke-linecap="round" stroke-linejoin="round" /></svg>
                    <p class="font-display text-lg font-semibold text-ink">Upload your artwork</p>
                    <p class="max-w-xs text-sm text-ink/55">PNG, JPG or PDF. We'll place it on your {{ product.name.toLowerCase() }}.</p>
                    <button class="mt-1 rounded-full bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-700" @click="fileInput.click()">Choose file</button>
                </div>
            </div>
            <div class="flex overflow-hidden rounded-full border border-paper-300 bg-paper text-sm font-medium">
                <button class="px-5 py-2" :class="side === 'front' ? 'bg-brand-600 text-white' : 'text-ink/70'" @click="flip('front')">Front</button>
                <button class="px-5 py-2" :class="side === 'back' ? 'bg-brand-600 text-white' : 'text-ink/70'" @click="flip('back')">Back</button>
            </div>
        </div>

        <div class="flex items-center justify-center gap-3 border-t border-paper-300 bg-paper px-5 py-3">
            <span class="text-sm font-medium text-ink/60">Background</span>
            <div class="flex gap-1.5">
                <button v-for="c in bgPalette" :key="c" class="h-7 w-7 rounded-full border border-paper-300 shadow-sm transition hover:scale-110" :style="{ backgroundColor: c }" @click="setBg(c)"></button>
            </div>
        </div>

        <!-- Templates drawer -->
        <div v-if="showTemplates" class="fixed inset-0 z-50 bg-ink/40" @click.self="showTemplates = false">
            <div class="absolute right-0 top-0 flex h-full w-full max-w-md flex-col bg-paper shadow-2xl">
                <div class="flex items-center justify-between border-b border-paper-300 px-5 py-4">
                    <h3 class="font-display text-lg font-semibold text-ink">Choose a template</h3>
                    <button class="text-xl text-ink/50 hover:text-ink" @click="showTemplates = false">✕</button>
                </div>
                <div class="grid flex-1 grid-cols-2 gap-4 overflow-auto bg-paper-200 p-4">
                    <button v-for="t in templates" :key="t.ref" class="group flex h-max flex-col overflow-hidden rounded-xl border border-paper-300 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg hover:ring-2 hover:ring-brand-600" @click="applyTemplate(t.ref)">
                        <div class="relative h-28 w-full overflow-hidden bg-paper-200 sm:h-32">
                            <img v-if="t.preview" :src="t.preview" :alt="t.name" loading="lazy" class="absolute inset-0 h-full w-full object-cover" />
                        </div>
                        <p class="truncate px-2.5 py-2 text-left text-xs font-medium text-ink/70">{{ t.name }}</p>
                    </button>
                </div>
                <div v-if="applyingTpl" class="border-t border-paper-300 p-3 text-center text-sm text-ink/60">Loading template…</div>
            </div>
        </div>

        <!-- Replace-logo popup (opens on a click on any logo on the canvas) -->
        <div v-if="showLogoPopup" class="fixed inset-0 z-50 grid place-items-center bg-ink/40 p-4" @click.self="closeLogoPopup">
            <div class="w-full max-w-sm rounded-2xl bg-paper p-5 shadow-2xl">
                <div class="flex items-center justify-between">
                    <h3 class="font-display text-lg font-semibold text-ink">{{ logoIsPlaceholder() ? 'Add your logo' : 'Replace your logo' }}</h3>
                    <button class="text-xl text-ink/50 hover:text-ink" @click="closeLogoPopup">✕</button>
                </div>
                <div class="mt-4 grid place-items-center rounded-xl border border-paper-300 bg-white p-4">
                    <img v-if="logoPopupSrc" :src="logoPopupSrc" alt="Current logo" class="max-h-28 w-auto max-w-full" />
                </div>
                <p class="mt-3 text-sm text-ink/55">{{ logoIsPlaceholder() ? 'Upload your logo — it will be placed exactly where the placeholder sits.' : 'Upload a different image to swap it in — size and position are kept.' }}</p>
                <button class="mt-4 w-full rounded-full bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-700" @click="logoInput.click()">
                    ↑ {{ logoIsPlaceholder() ? 'Upload your logo' : 'Replace logo' }}
                </button>
                <button class="mt-2 w-full rounded-full border border-brand-blue px-5 py-2.5 text-sm font-semibold text-brand-blue transition hover:bg-brand-50" @click="openLogoBuilder(true)">
                    ✦ No logo yet? Create one with AI
                </button>
                <div class="mt-2 flex items-center justify-between">
                    <button class="text-sm font-medium text-ink/55 transition hover:text-ink" @click="removeLogo">Remove logo</button>
                    <button class="text-sm font-medium text-ink/55 transition hover:text-ink" @click="closeLogoPopup">Keep current</button>
                </div>
                <input ref="logoInput" type="file" accept="image/*" class="hidden" @change="onLogoFile" />
            </div>
        </div>

        <!-- AI logo builder modal -->
        <div v-if="showLogoBuilder" class="fixed inset-0 z-50 grid place-items-center bg-ink/40 p-4" @click.self="showLogoBuilder = false">
            <div class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-paper p-6 shadow-2xl">
                <div class="flex items-center justify-between">
                    <h3 class="font-display text-lg font-semibold text-ink">✦ AI logo builder</h3>
                    <button class="text-xl text-ink/50 hover:text-ink" @click="showLogoBuilder = false">✕</button>
                </div>
                <p class="mt-1 text-sm text-ink/55">Describe your business — we'll design logo concepts and place your pick straight onto the design.</p>
                <p v-if="builderError" class="mt-2 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600">{{ builderError }}</p>
                <div class="mt-4">
                    <LogoBuilder compact use-label="Place on my design" @use="useGeneratedLogo" />
                </div>
            </div>
        </div>
    </div>
</template>
