<script setup>
import { ref, computed, watch, onBeforeUnmount } from 'vue';

// Shared AI logo builder: used by the standalone /logo-maker page and by the
// online designer (modal). Each round generates four concepts via Replicate
// (recraft SVG) and emits the chosen logo upward — the host decides what
// "use" means (download + upsell, or insert onto the canvas).
const props = defineProps({
    styles: { type: Array, default: () => ['minimal', 'modern', 'classic', 'playful', 'elegant', 'bold'] },
    colors: { type: Array, default: () => ['brand-blue', 'monochrome', 'warm', 'nature', 'colorful'] },
    useLabel: { type: String, default: 'Use this logo' },
    compact: { type: Boolean, default: false },
});
const emit = defineEmits(['use']);

// label shown in the select → descriptive phrase fed to the prompt
const INDUSTRIES = [
    ['Accounting & finance', 'accounting and finance firm'],
    ['Automotive', 'automotive repair shop'],
    ['Bakery', 'artisan bakery'],
    ['Barbershop / hair salon', 'barbershop and hair salon'],
    ['Beauty & cosmetics', 'beauty and cosmetics brand'],
    ['Brewery / distillery', 'craft brewery'],
    ['Cleaning services', 'cleaning services company'],
    ['Coffee shop / café', 'coffee shop'],
    ['Construction', 'construction company'],
    ['Consulting', 'consulting firm'],
    ['Dental practice', 'dental practice'],
    ['Education & tutoring', 'tutoring and education service'],
    ['Electrician', 'electrician business'],
    ['Event planning', 'event planning studio'],
    ['Fashion & apparel', 'fashion and apparel brand'],
    ['Fitness & gym', 'fitness studio'],
    ['Florist', 'florist boutique'],
    ['Food truck / catering', 'food truck and catering business'],
    ['Landscaping & gardening', 'landscaping and gardening company'],
    ['Law firm', 'law firm'],
    ['Marketing agency', 'marketing agency'],
    ['Medical practice', 'medical practice'],
    ['Music & entertainment', 'music and entertainment business'],
    ['Nonprofit / NGO', 'nonprofit organisation'],
    ['Pet care & grooming', 'pet care and grooming salon'],
    ['Photography', 'photography studio'],
    ['Plumbing & HVAC', 'plumbing and HVAC company'],
    ['Real estate', 'real estate agency'],
    ['Restaurant & bar', 'restaurant'],
    ['Retail store', 'retail store'],
    ['Software & tech', 'software and technology company'],
    ['Transport & logistics', 'transport and logistics company'],
    ['Travel & tourism', 'travel and tourism agency'],
    ['Wellness & spa', 'wellness spa'],
    // extended set
    ['Architecture', 'architecture firm'],
    ['Artist / art studio', 'art studio'],
    ['Bar / pub', 'neighbourhood pub'],
    ['Bookstore', 'independent bookstore'],
    ['Butcher shop', 'butcher shop'],
    ['Car wash & detailing', 'car wash and auto detailing service'],
    ['Carpentry & woodworking', 'carpentry and woodworking shop'],
    ['Childcare / daycare', 'childcare and daycare centre'],
    ['Chiropractic & physio', 'chiropractic and physiotherapy clinic'],
    ['Courier & delivery', 'courier and delivery service'],
    ['Dance studio', 'dance studio'],
    ['Dry cleaning & laundry', 'dry cleaning and laundry service'],
    ['E-commerce brand', 'e-commerce brand'],
    ['Farm & agriculture', 'farm and agriculture business'],
    ['Handyman services', 'handyman services company'],
    ['Ice cream & desserts', 'ice cream and dessert shop'],
    ['Insurance agency', 'insurance agency'],
    ['Interior design', 'interior design studio'],
    ['Jewelry', 'jewellery brand'],
    ['Juice & smoothie bar', 'juice and smoothie bar'],
    ['Locksmith', 'locksmith service'],
    ['Martial arts', 'martial arts academy'],
    ['Massage therapy', 'massage therapy practice'],
    ['Moving company', 'moving company'],
    ['Nail salon', 'nail salon'],
    ['Painting & decorating', 'painting and decorating company'],
    ['Pest control', 'pest control service'],
    ['Pizza shop', 'pizzeria'],
    ['Pool services', 'pool cleaning and maintenance service'],
    ['Property management', 'property management company'],
    ['Psychology & therapy', 'psychology and therapy practice'],
    ['Roofing', 'roofing company'],
    ['Security services', 'security services company'],
    ['Solar & renewables', 'solar and renewable energy company'],
    ['Tattoo studio', 'tattoo studio'],
    ['Veterinary clinic', 'veterinary clinic'],
    ['Videography', 'videography studio'],
    ['Web design', 'web design studio'],
    ['Wedding services', 'wedding planning service'],
    ['Wine & vineyard', 'winery and vineyard'],
    ['Yoga studio', 'yoga studio'],
    // third set
    ['Antiques & vintage', 'antiques and vintage shop'],
    ['Bike shop', 'bicycle sales and repair shop'],
    ['Boat & marine', 'boat and marine services company'],
    ['Bubble tea & tea house', 'bubble tea shop'],
    ['Candles & home fragrance', 'candle and home fragrance brand'],
    ['Chocolatier', 'artisan chocolate maker'],
    ['Concrete & excavation', 'concrete and excavation contractor'],
    ['Cybersecurity', 'cybersecurity firm'],
    ['Deli & sandwich shop', 'deli and sandwich shop'],
    ['Driving school', 'driving school'],
    ['Drone services', 'drone photography and inspection service'],
    ['Electronics repair', 'phone and electronics repair shop'],
    ['Funeral services', 'funeral home'],
    ['Furniture & home decor', 'furniture and home decor store'],
    ['Gaming & esports', 'gaming and esports brand'],
    ['Home health care', 'home health care agency'],
    ['Hotel / bed & breakfast', 'boutique hotel and bed and breakfast'],
    ['IT services', 'managed IT services company'],
    ['Kitchen & bath remodeling', 'kitchen and bathroom remodelling company'],
    ['Life coaching', 'life coaching practice'],
    ['Optometry & eyewear', 'optometry and eyewear practice'],
    ['Pharmacy', 'pharmacy'],
    ['Podcast & media', 'podcast and media studio'],
    ['Pottery & ceramics', 'pottery and ceramics studio'],
    ['Pressure washing', 'pressure washing service'],
    ['Recruitment & HR', 'recruitment and HR agency'],
    ['Tailoring & alterations', 'tailoring and alterations studio'],
    ['Towing & roadside', 'towing and roadside assistance service'],
    ['Tree services', 'tree care and removal service'],
    ['Welding & metal fabrication', 'welding and metal fabrication shop'],
].sort((a, b) => a[0].localeCompare(b[0]));
const OTHER = '__other';
const industryChoice = ref('');

const form = ref({ company: '', tagline: '', industry: '', style: 'minimal', color: 'brand-blue', colors: ['#2b3b55', '#398aff'] });

function onIndustryChange() {
    form.value.industry = industryChoice.value === OTHER ? '' : industryChoice.value;
}
const results = ref([]);       // {path, url, variant}
const pending = ref(0);        // in-flight generations (skeleton tiles)
const error = ref('');

// Time-based progress bar for AI generation. Replicate gives no real percentage,
// so we ease a bar from ~1s toward the ~45s mark while concepts are in flight,
// hold near the end, then snap to 100% when they all land.
const genProgress = ref(0);
let genTimer = null;
let genStart = 0;

// every round covers four concept lanes (matches the server's variant map)
const conceptLabel = (v) => ['Industry', 'Your name', 'Abstract', form.value.tagline.trim() ? 'Tagline' : 'Name + industry'][(v ?? 0) % 4];

// "Your colours" is the customisable slot (defaults to the brand blues) —
// its chips mirror the pickers live; the other palettes are fixed presets.
const colorMeta = computed(() => ({
    'brand-blue': { label: 'Your colours', chips: form.value.colors },
    monochrome:   { label: 'Monochrome', chips: ['#16233b', '#ffffff'] },
    warm:         { label: 'Warm', chips: ['#c96f4a', '#e8b04b'] },
    nature:       { label: 'Nature', chips: ['#2f6b4f', '#8a6d3b'] },
    colorful:     { label: 'Colorful', chips: ['#398aff', '#e8554d', '#e8b04b'] },
}));
const ready = computed(() => form.value.company.trim() && form.value.industry.trim() && pending.value === 0);

const xsrf = () => decodeURIComponent((document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/) || [])[1] || '');

const sleep = (ms) => new Promise((res) => setTimeout(res, ms));

// Async generate: the POST returns a prediction id instantly, then we poll.
// Long 15–60 s requests get killed by mobile Safari ("Load failed"); short
// polls survive backgrounding — a dropped poll simply retries.
async function generateOne(v) {
    pending.value++;
    try {
        const r = await fetch('/logo-maker/generate', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': xsrf() },
            // the custom palette only applies to the "Your colours" choice
            body: JSON.stringify({ ...form.value, colors: form.value.color === 'brand-blue' ? form.value.colors : undefined, variant: v }),
        });
        if (!r.ok) throw new Error((await r.json().catch(() => ({})))?.message || `generation failed (${r.status})`);
        const { id } = await r.json();

        for (let i = 0; i < 60; i++) {
            await sleep(2500);
            let s;
            try {
                s = await fetch(`/logo-maker/status/${id}`, { headers: { Accept: 'application/json' } });
            } catch (e) {
                continue; // transient network blip (tab backgrounded, …) — poll again
            }
            if (s.status === 422) throw new Error((await s.json().catch(() => ({})))?.message || 'generation failed');
            if (!s.ok) continue;
            const d = await s.json();
            if (d.done) {
                results.value.unshift({ path: d.path, url: d.url, variant: v });
                return;
            }
        }
        throw new Error('generation timed out');
    } catch (e) {
        error.value = String(e.message || e);
    } finally {
        pending.value--;
    }
}

function generate() {
    if (!form.value.company.trim() || !form.value.industry.trim() || pending.value) return;
    error.value = '';
    // one logo per concept lane: industry, name-literal, abstract, tagline/fusion
    [0, 1, 2, 3].forEach((v) => generateOne(v));
}

// iterate on a concept: same lane, two fresh takes
function moreLike(r) {
    if (pending.value) return;
    error.value = '';
    generateOne(r.variant ?? 0);
    generateOne(r.variant ?? 0);
}

// Drive the progress bar off the in-flight count: fill over ~45s, then complete.
watch(pending, (n, prev) => {
    if (n > 0 && prev === 0) {
        genStart = Date.now();
        genProgress.value = 2;
        clearInterval(genTimer);
        genTimer = setInterval(() => {
            const elapsed = Date.now() - genStart;
            genProgress.value = Math.min(97, 2 + (elapsed / 45000) * 95); // ~1s -> 45s, then hold near the end
        }, 200);
    } else if (n === 0 && prev > 0) {
        clearInterval(genTimer);
        genTimer = null;
        genProgress.value = 100;                 // all concepts landed
        setTimeout(() => { if (pending.value === 0) genProgress.value = 0; }, 700);
    }
});

onBeforeUnmount(() => clearInterval(genTimer));
</script>

<template>
    <div>
        <!-- brief -->
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="text-xs font-semibold uppercase tracking-wide text-ink/55">Company name *</label>
                <input v-model="form.company" type="text" maxlength="80" placeholder="e.g. Harbor & Co"
                       class="mt-1.5 w-full rounded-xl border border-paper-300 bg-white px-3.5 py-2.5 text-sm focus:border-brand-400 focus:outline-none" />
            </div>
            <div>
                <label class="text-xs font-semibold uppercase tracking-wide text-ink/55">Industry *</label>
                <select v-model="industryChoice" @change="onIndustryChange"
                        class="mt-1.5 w-full rounded-xl border border-paper-300 bg-white px-3 py-2.5 text-sm focus:border-brand-400 focus:outline-none"
                        :class="industryChoice ? 'text-ink' : 'text-ink/40'">
                    <option value="" disabled>Choose your industry…</option>
                    <option v-for="[label, value] in INDUSTRIES" :key="value" :value="value">{{ label }}</option>
                    <option :value="OTHER">Other…</option>
                </select>
                <input v-if="industryChoice === OTHER" v-model="form.industry" type="text" maxlength="80"
                       placeholder="describe your industry, e.g. drone repair"
                       class="mt-2 w-full rounded-xl border border-paper-300 bg-white px-3.5 py-2.5 text-sm focus:border-brand-400 focus:outline-none" />
            </div>
            <div class="sm:col-span-2">
                <label class="text-xs font-semibold uppercase tracking-wide text-ink/55">Tagline <span class="normal-case text-ink/35">(optional)</span></label>
                <input v-model="form.tagline" type="text" maxlength="120" placeholder="e.g. Roasted with care since 2020"
                       class="mt-1.5 w-full rounded-xl border border-paper-300 bg-white px-3.5 py-2.5 text-sm focus:border-brand-400 focus:outline-none" />
            </div>
        </div>

        <!-- style -->
        <p class="mt-5 text-xs font-semibold uppercase tracking-wide text-ink/55">Style</p>
        <div class="mt-2 flex flex-wrap gap-2">
            <button v-for="s in styles" :key="s" type="button" @click="form.style = s"
                    class="rounded-full border px-4 py-1.5 text-sm capitalize transition"
                    :class="form.style === s ? 'border-brand-600 bg-brand-600 text-white' : 'border-paper-300 bg-white text-ink/70 hover:border-ink/30'">
                {{ s }}
            </button>
        </div>

        <!-- colours -->
        <p class="mt-5 text-xs font-semibold uppercase tracking-wide text-ink/55">Colours</p>
        <div class="mt-2 flex flex-wrap gap-2">
            <button v-for="c in colors" :key="c" type="button" @click="form.color = c"
                    class="flex items-center gap-2 rounded-full border px-4 py-1.5 text-sm transition"
                    :class="form.color === c ? 'border-brand-600 bg-brand-50 text-brand-700' : 'border-paper-300 bg-white text-ink/70 hover:border-ink/30'">
                <span class="flex -space-x-1">
                    <span v-for="hex in (colorMeta[c]?.chips || [])" :key="hex" class="inline-block h-3.5 w-3.5 rounded-full ring-1 ring-ink/10" :style="{ backgroundColor: hex }"></span>
                </span>
                {{ colorMeta[c]?.label || c }}
            </button>
        </div>
        <div v-if="form.color === 'brand-blue'" class="mt-3 flex flex-wrap items-center gap-4 rounded-xl border border-paper-300 bg-paper-200/40 px-4 py-2.5">
            <p class="text-xs font-semibold uppercase tracking-wide text-ink/55">Pick your two colours</p>
            <label class="flex items-center gap-2 text-sm text-ink/70">Primary
                <input v-model="form.colors[0]" type="color" class="h-8 w-11 cursor-pointer rounded-md border border-paper-300 bg-white p-0.5" />
            </label>
            <label class="flex items-center gap-2 text-sm text-ink/70">Secondary
                <input v-model="form.colors[1]" type="color" class="h-8 w-11 cursor-pointer rounded-md border border-paper-300 bg-white p-0.5" />
            </label>
        </div>

        <button type="button" :disabled="!ready" @click="generate"
                class="mt-6 inline-flex items-center gap-2 rounded-full bg-brand-blue px-8 py-3.5 font-semibold text-white shadow-lg shadow-brand-blue/30 transition hover:bg-[#2f78e0] disabled:cursor-not-allowed disabled:opacity-50">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M12 3l1.7 4.6L18 9l-4.3 1.4L12 15l-1.7-4.6L6 9l4.3-1.4z" stroke-linejoin="round"/><path d="M18.5 15l.8 2.2 2.2.8-2.2.8-.8 2.2-.8-2.2-2.2-.8 2.2-.8z" stroke-linejoin="round"/></svg>
            {{ pending ? 'Designing…' : results.length ? 'Generate more' : 'Generate my logo' }}
        </button>
        <p v-if="error" class="mt-2 text-sm text-red-600">{{ error }} — please try again.</p>

        <!-- AI generation progress (time-based estimate, ~45s) -->
        <div v-if="genProgress > 0" class="mt-4">
            <div class="h-2 w-full overflow-hidden rounded-full bg-paper-200">
                <div class="h-full rounded-full bg-brand-blue transition-[width] duration-200 ease-linear" :style="{ width: genProgress + '%' }"></div>
            </div>
            <p class="mt-1.5 text-xs text-ink/50">Designing your logo concepts… {{ Math.round(genProgress) }}%</p>
        </div>

        <!-- results -->
        <div v-if="results.length || pending" class="mt-7 grid gap-4" :class="compact ? 'grid-cols-2' : 'grid-cols-2 md:grid-cols-3 lg:grid-cols-4'">
            <div v-for="i in pending" :key="`skeleton-${i}`" class="aspect-square animate-pulse rounded-2xl border border-paper-300 bg-paper-200"></div>
            <div v-for="r in results" :key="r.path" class="group relative overflow-hidden rounded-2xl border border-paper-300 bg-white shadow-sm transition hover:shadow-lg">
                <div class="aspect-square bg-white p-4">
                    <img :src="r.url" alt="Generated logo option" class="h-full w-full object-contain" />
                </div>
                <span class="absolute left-2 top-2 rounded-full bg-brand-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-brand-700">{{ conceptLabel(r.variant) }}</span>
                <button type="button" class="absolute right-2 top-2 rounded-full border border-paper-300 bg-white/95 px-2.5 py-1 text-[11px] font-semibold text-ink/60 opacity-0 transition hover:border-brand-400 hover:text-brand-700 group-hover:opacity-100"
                        title="Generate two more takes on this concept" @click="moreLike(r)">
                    ↻ More like this
                </button>
                <button type="button" class="w-full border-t border-paper-300 bg-paper-200/50 py-2.5 text-sm font-semibold text-brand-700 transition hover:bg-brand-600 hover:text-white" @click="emit('use', r)">
                    {{ useLabel }}
                </button>
            </div>
        </div>
    </div>
</template>
