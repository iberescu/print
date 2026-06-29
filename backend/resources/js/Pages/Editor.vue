<script setup>
import { ref, reactive, onMounted, onBeforeUnmount } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import * as fabric from 'fabric';
import AppLogo from '../Components/AppLogo.vue';

const props = defineProps({
    product: { type: Object, required: true },
    category: { type: Object, default: () => ({}) },
    mode: { type: String, default: 'design' },
});

// Working resolution (3.5×2" card ratio); exported at higher multiplier for print.
const W = 760;
const H = 434;

const canvasEl = ref(null);
let canvas = null;
const side = ref('front');
const store = { front: null, back: null };
const uploaded = ref(false);
const saving = ref(false);
const fileInput = ref(null);

// Google Fonts only (per project rule) — loaded via fonts.googleapis.com below.
const fonts = ['Fraunces', 'Playfair Display', 'Poppins', 'Montserrat', 'Lora', 'Oswald', 'Roboto Slab', 'DM Sans', 'Bebas Neue', 'Space Mono'];
const sizes = [12, 14, 16, 18, 20, 24, 28, 32, 40, 48, 56, 64, 72];
const palette = ['#0c1f17', '#0e9355', '#c7f23d', '#ffffff', '#111111', '#c0392b', '#1f2a44', '#b0703a'];
const bgPalette = ['#ffffff', '#f8f6ef', '#0c1f17', '#0e9355', '#1f2a44', '#e7dcc4', '#c0392b'];

const GOOGLE_FONTS_HREF =
    'https://fonts.googleapis.com/css2?family=Fraunces:wght@400;600;900&family=Playfair+Display:wght@400;700&family=Poppins:wght@400;600&family=Montserrat:wght@400;600&family=Lora:wght@400;600&family=Oswald:wght@400;600&family=Roboto+Slab:wght@400;700&family=DM+Sans:wght@400;500;700&family=Bebas+Neue&family=Space+Mono&display=swap';

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
const sel = reactive({ fontFamily: 'Fraunces', fontSize: 40, fill: '#0c1f17', bold: false, italic: false, align: 'left' });

function addText(text, opts = {}) {
    const t = new fabric.IText(text, {
        left: 48, top: 60, originX: 'left', originY: 'top', fontFamily: 'DM Sans', fill: '#0c1f17', fontSize: 22, ...opts,
    });
    canvas.add(t);
    return t;
}

function seedTemplate() {
    canvas.backgroundColor = '#f8f6ef';
    addText('Company Name', { left: 56, top: 64, fontSize: 34, fontWeight: 'bold', fontFamily: 'Fraunces' });
    addText('Your Name', { left: 56, top: 120, fontSize: 24, fontFamily: 'DM Sans' });
    addText('Title / Role', { left: 56, top: 152, fontSize: 18, fill: '#0e9355' });
    addText('hello@company.com', { left: 56, top: 300, fontSize: 18 });
    addText('+1 (555) 123-4567', { left: 56, top: 328, fontSize: 18 });
    const logo = new fabric.Rect({
        left: 560, top: 60, width: 150, height: 150, rx: 14, ry: 14,
        fill: '#e3ddcc', stroke: '#0e9355', strokeDashArray: [6, 6], strokeWidth: 2,
    });
    canvas.add(logo);
    const hint = new fabric.IText('LOGO', { left: 600, top: 125, originX: 'left', originY: 'top', fontSize: 18, fill: '#0e935580', fontFamily: 'DM Sans', selectable: false, evented: false });
    canvas.add(hint);
    canvas.renderAll();
}

function syncSelection() {
    const o = canvas.getActiveObject();
    hasSel.value = !!o;
    isText.value = !!o && (o.type === 'i-text' || o.type === 'textbox');
    if (o && isText.value) {
        sel.fontFamily = o.fontFamily || sel.fontFamily;
        sel.fontSize = o.fontSize || sel.fontSize;
        sel.fill = typeof o.fill === 'string' ? o.fill : sel.fill;
        sel.bold = o.fontWeight === 'bold' || Number(o.fontWeight) >= 700;
        sel.italic = o.fontStyle === 'italic';
        sel.align = o.textAlign || 'left';
    }
}

onMounted(() => {
    ensureFonts();
    canvas = new fabric.Canvas(canvasEl.value, { backgroundColor: '#ffffff', preserveObjectStacking: true });
    canvas.setDimensions({ width: W, height: H });
    canvas.on('selection:created', syncSelection);
    canvas.on('selection:updated', syncSelection);
    canvas.on('selection:cleared', () => { hasSel.value = false; isText.value = false; });

    if (props.mode === 'design') {
        seedTemplate();
    } else {
        canvas.backgroundColor = '#ffffff';
        canvas.renderAll();
    }

    // Re-render once web fonts finish loading (fabric measures glyph widths at draw time)
    if (typeof document !== 'undefined' && document.fonts?.ready) {
        document.fonts.ready.then(() => canvas && canvas.requestRenderAll());
    }
});

onBeforeUnmount(() => canvas && canvas.dispose());

// ---- toolbar actions ----
function apply(prop, val) {
    const o = canvas.getActiveObject();
    if (!o) return;
    o.set(prop, val);
    canvas.requestRenderAll();
}
const setFont = (f) => { sel.fontFamily = f; apply('fontFamily', f); };
const setSize = (s) => { sel.fontSize = Number(s); apply('fontSize', Number(s)); };
const setColor = (c) => { sel.fill = c; apply('fill', c); };
const toggleBold = () => { sel.bold = !sel.bold; apply('fontWeight', sel.bold ? 'bold' : 'normal'); };
const toggleItalic = () => { sel.italic = !sel.italic; apply('fontStyle', sel.italic ? 'italic' : 'normal'); };
const setAlign = (a) => { sel.align = a; apply('textAlign', a); };
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
        img.scaleToWidth(W);
        img.set({ left: 0, top: 0, selectable: true });
        uploaded.value = true;
    } else {
        img.scaleToWidth(160);
        img.set({ left: 560, top: 60 });
    }
    canvas.add(img);
    canvas.setActiveObject(img);
    canvas.requestRenderAll();
    e.target.value = '';
}

function flip(to) {
    if (to === side.value) return;
    store[side.value] = canvas.toJSON();
    side.value = to;
    canvas.clear();
    if (store[to]) {
        canvas.loadFromJSON(store[to]).then(() => canvas.renderAll());
    } else {
        canvas.backgroundColor = '#f8f6ef';
        canvas.renderAll();
    }
}

function addToCart() {
    saving.value = true;
    store[side.value] = canvas.toJSON();
    const preview = canvas.toDataURL({ format: 'jpeg', quality: 0.72, multiplier: 0.55 });
    router.post(`/design/${props.product.slug}`, { design: store, preview }, {
        onFinish: () => (saving.value = false),
    });
}
</script>

<template>
    <Head :title="`Design — ${product.name}`" />
    <div class="flex min-h-screen flex-col bg-paper-200">
        <!-- Top bar -->
        <header class="flex items-center justify-between gap-4 border-b border-paper-300 bg-paper px-5 py-3">
            <div class="flex items-center gap-4">
                <Link href="/"><AppLogo /></Link>
                <div class="hidden h-6 w-px bg-paper-300 sm:block"></div>
                <div class="hidden sm:block">
                    <p class="text-sm font-semibold text-ink">{{ product.name }}</p>
                    <p class="text-xs text-ink/50">{{ category.name }}</p>
                </div>
            </div>
            <div class="flex items-center gap-2 text-sm font-medium">
                <span class="rounded-full bg-brand-50 px-3 py-1 text-brand-700">1 · Design</span>
                <span class="text-ink/30">2 · Review</span>
            </div>
            <button
                :disabled="saving"
                class="rounded-full bg-brand-600 px-6 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-700 disabled:opacity-60"
                @click="addToCart"
            >
                {{ saving ? 'Saving…' : 'Add to cart →' }}
            </button>
        </header>

        <!-- Toolbar -->
        <div class="flex flex-wrap items-center gap-2 bg-ink px-4 py-2 text-paper">
            <button class="rounded-md px-3 py-1.5 text-sm font-medium hover:bg-white/10" @click="newText">+ Text</button>
            <button class="rounded-md px-3 py-1.5 text-sm font-medium hover:bg-white/10" @click="fileInput.click()">↑ Upload image</button>
            <button class="rounded-md px-3 py-1.5 text-sm font-medium hover:bg-white/10 disabled:opacity-30" :disabled="!hasSel" @click="removeSel">🗑 Delete</button>
            <input ref="fileInput" type="file" accept="image/*" class="hidden" @change="onFile" />

            <div class="mx-1 h-6 w-px bg-white/15"></div>

            <template v-if="isText">
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

        <!-- Stage -->
        <div class="relative flex flex-1 flex-col items-center justify-center gap-6 overflow-auto p-8">
            <p class="text-sm font-medium text-ink/50">
                {{ side === 'front' ? 'Front' : 'Back' }} design · <span class="text-ink/40">all of your cards will look like this</span>
            </p>

            <div class="relative">
                <div class="overflow-hidden rounded-2xl bg-white shadow-[0_30px_60px_-25px_rgba(12,31,23,0.5)] ring-1 ring-paper-300">
                    <canvas ref="canvasEl"></canvas>
                </div>

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

        <!-- Bottom bar -->
        <div class="flex items-center justify-center gap-3 border-t border-paper-300 bg-paper px-5 py-3">
            <span class="text-sm font-medium text-ink/60">Background</span>
            <div class="flex gap-1.5">
                <button v-for="c in bgPalette" :key="c" class="h-7 w-7 rounded-full border border-paper-300 shadow-sm transition hover:scale-110" :style="{ backgroundColor: c }" @click="setBg(c)"></button>
            </div>
        </div>
    </div>
</template>
