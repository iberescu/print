<script setup>
import { ref, reactive, onMounted, onBeforeUnmount } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import * as fabric from 'fabric';
import AppLogo from '../Components/AppLogo.vue';

const props = defineProps({
    product: { type: Object, required: true },
    category: { type: Object, default: () => ({}) },
    mode: { type: String, default: 'design' },
    templates: { type: Array, default: () => [] },
    template: { type: String, default: null },
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
        img.set({ left, top, originX: center ? 'center' : 'left', originY: 'top', rmpRole: 'logo' });
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
    canvas.on('selection:created', syncSelection);
    canvas.on('selection:updated', syncSelection);
    canvas.on('selection:cleared', () => { hasSel.value = false; isText.value = false; });

    if (props.template) applyTemplate(props.template);
    else if (props.mode === 'design') (isBusinessCard ? seedTemplate : seedGeneric)();
    else { canvas.backgroundColor = '#ffffff'; canvas.renderAll(); }

    fitCanvas();
    window.addEventListener('resize', fitCanvas);

    if (typeof document !== 'undefined' && document.fonts?.ready) {
        document.fonts.ready.then(() => repaintFonts());
    }
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', fitCanvas);
    canvas && canvas.dispose();
});

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

async function onFile(e) {
    const file = e.target.files?.[0];
    if (!file) return;
    const url = await new Promise((res) => { const r = new FileReader(); r.onload = (ev) => res(ev.target.result); r.readAsDataURL(file); });
    const img = await fabric.FabricImage.fromURL(url, { crossOrigin: 'anonymous' });
    if (props.mode === 'upload' && !uploaded.value) {
        img.scaleToWidth(W); img.set({ left: 0, top: 0, selectable: true }); uploaded.value = true;
    } else {
        img.scaleToWidth(160); img.set({ left: 560, top: 60, rmpRole: 'logo' });
    }
    canvas.add(img); canvas.setActiveObject(img); canvas.requestRenderAll();
    e.target.value = '';
}

function flip(to) {
    if (to === side.value) return;
    store[side.value] = canvas.toJSON();
    side.value = to;
    canvas.clear();
    if (store[to]) canvas.loadFromJSON(store[to]).then(() => canvas.renderAll());
    else { canvas.backgroundColor = '#f8f6ef'; canvas.renderAll(); }
}

async function applyTemplate(ref) {
    applyingTpl.value = true;
    try {
        const res = await fetch(`/design/template/${ref}/data`, { headers: { Accept: 'application/json' } });
        const { data } = await res.json();
        await canvas.loadFromJSON(data);
        offsetByBleed(); // template art is authored at trim origin; nudge it inside the bleed
        canvas.requestRenderAll();
        store[side.value] = canvas.toJSON();
        showTemplates.value = false;
        // repaint once fonts settle (non-blocking, so the design shows immediately)
        if (document.fonts?.ready) document.fonts.ready.then(() => repaintFonts());
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
            try { b.logo = o.toDataURL({ format: 'png' }); } catch (e) { /* tainted/none */ }
        }
    });
    if (!b.logo) {
        const img = objs.find((o) => o.type === 'image');
        if (img) { try { b.logo = img.toDataURL({ format: 'png' }); } catch (e) {} }
    }
    return b;
}

function goToReview() {
    saving.value = true;
    store[side.value] = canvas.toJSON();
    const preview = canvas.toDataURL({ format: 'jpeg', quality: 0.82, multiplier: 0.7 });
    router.post(`/design/${props.product.slug}/review`, {
        quantityId: props.selection?.quantityId ?? null,
        optionValueIds: props.selection?.optionValueIds ?? [],
        preview,
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
            <button class="rounded-md px-3 py-1.5 text-sm font-medium hover:bg-white/10" @click="newText">+ Text</button>
            <button class="rounded-md px-3 py-1.5 text-sm font-medium hover:bg-white/10" @click="fileInput.click()">↑ Upload image</button>
            <button v-if="templates.length" class="rounded-md px-3 py-1.5 text-sm font-medium hover:bg-white/10" @click="showTemplates = true">▦ Templates</button>
            <button class="rounded-md px-3 py-1.5 text-sm font-medium hover:bg-white/10 disabled:opacity-30" :disabled="!hasSel" @click="removeSel">🗑 Delete</button>
            <button v-if="bleed" class="rounded-md px-3 py-1.5 text-sm font-medium hover:bg-white/10" :class="showGuides ? 'bg-white/15' : ''" @click="showGuides = !showGuides" title="Show print bleed &amp; safe-area guides">▣ Guides</button>
            <input ref="fileInput" type="file" accept="image/*" class="hidden" @change="onFile" />

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
                <div v-if="(bleed || noPrint.length || fold.length) && showGuides" class="flex flex-wrap items-center justify-center gap-x-3 gap-y-1 text-[11px] text-ink/60 sm:gap-x-4 sm:text-xs">
                    <span v-if="bleed" class="flex items-center gap-1.5"><span class="inline-block h-3 w-4 bg-rose-500/15 ring-1 ring-rose-500/60"></span>Bleed<span class="hidden sm:inline"> — trimmed off</span></span>
                    <span class="flex items-center gap-1.5"><span class="inline-block h-0 w-5 border-t-2 border-rose-500"></span>Trim<span class="hidden sm:inline"> / cut line</span></span>
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
                <svg v-if="(bleed || noPrint.length || fold.length) && showGuides" class="pointer-events-none absolute inset-0 h-full w-full" :viewBox="`0 0 ${W} ${H}`" preserveAspectRatio="none">
                    <path v-if="bleed" :d="guidePath" fill="rgba(225,29,72,0.12)" fill-rule="evenodd" />
                    <rect :x="bleed" :y="bleed" :width="trimW" :height="trimH" fill="none" stroke="#e11d48" stroke-width="1.5" />
                    <rect :x="bleed + safety" :y="bleed + safety" :width="safeW" :height="safeH" fill="none" stroke="#0ea5e9" stroke-width="1.5" stroke-dasharray="7 5" />
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
    </div>
</template>
