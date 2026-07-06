<script setup>
import { ref, computed } from 'vue';

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
].sort((a, b) => a[0].localeCompare(b[0]));
const OTHER = '__other';
const industryChoice = ref('');

const form = ref({ company: '', tagline: '', industry: '', style: 'minimal', color: 'brand-blue' });

function onIndustryChange() {
    form.value.industry = industryChoice.value === OTHER ? '' : industryChoice.value;
}
const results = ref([]);       // {path, url, variant}
const pending = ref(0);        // in-flight generations (skeleton tiles)
const error = ref('');

// every round covers four concept lanes (matches the server's variant map)
const conceptLabel = (v) => ['Industry', 'Your name', 'Abstract', form.value.tagline.trim() ? 'Tagline' : 'Name + industry'][(v ?? 0) % 4];

const colorMeta = {
    'brand-blue': { label: 'Brand blue', chips: ['#2b3b55', '#398aff'] },
    monochrome:   { label: 'Monochrome', chips: ['#16233b', '#ffffff'] },
    warm:         { label: 'Warm', chips: ['#c96f4a', '#e8b04b'] },
    nature:       { label: 'Nature', chips: ['#2f6b4f', '#8a6d3b'] },
    colorful:     { label: 'Colorful', chips: ['#398aff', '#e8554d', '#e8b04b'] },
};
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
            body: JSON.stringify({ ...form.value, variant: v }),
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

        <button type="button" :disabled="!ready" @click="generate"
                class="mt-6 inline-flex items-center gap-2 rounded-full bg-brand-blue px-8 py-3.5 font-semibold text-white shadow-lg shadow-brand-blue/30 transition hover:bg-[#2f78e0] disabled:cursor-not-allowed disabled:opacity-50">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M12 3l1.7 4.6L18 9l-4.3 1.4L12 15l-1.7-4.6L6 9l4.3-1.4z" stroke-linejoin="round"/><path d="M18.5 15l.8 2.2 2.2.8-2.2.8-.8 2.2-.8-2.2-2.2-.8 2.2-.8z" stroke-linejoin="round"/></svg>
            {{ pending ? 'Designing…' : results.length ? 'Generate more' : 'Generate my logo' }}
        </button>
        <p v-if="error" class="mt-2 text-sm text-red-600">{{ error }} — please try again.</p>

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
        <p v-if="pending" class="mt-3 text-xs text-ink/50">Drawing vector shapes — this takes about 15 seconds per concept.</p>
    </div>
</template>
