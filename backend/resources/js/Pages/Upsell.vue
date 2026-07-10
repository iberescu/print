<script setup>
import { Head, router } from '@inertiajs/vue3';
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue';
import StoreLayout from '../Layouts/StoreLayout.vue';
import BrandMockup from '../Components/BrandMockup.vue';
import FinalStep from '../Components/FinalStep.vue';
import SmartImage from '../Components/SmartImage.vue';
import FreeShippingBar from '../Components/FreeShippingBar.vue';
import { money } from '../lib/format';

const props = defineProps({
    step: { type: String, required: true },
    stepIndex: { type: Number, default: 1 },
    stepCount: { type: Number, default: 1 },
    payload: { type: Object, default: () => ({}) },
    summary: { type: Object, default: () => ({}) },
});

const added = ref({});
const busy = ref(null);
const isLast = computed(() => props.stepIndex >= props.stepCount);
const products = computed(() => props.payload.products || []);

// Every step opens with a ~1s "preparing" beat (labor illusion — these offers
// ARE personalised) before the content reveals. Inertia reuses this component
// across steps, so re-arm on every step change, not just on mount.
const stepLoading = ref(true);
let stepLoadTimer = null;
function armStepLoader() {
    stepLoading.value = true;
    if (stepLoadTimer) clearTimeout(stepLoadTimer);
    stepLoadTimer = setTimeout(() => (stepLoading.value = false), 1000);
}
const loaderText = computed(() => ({
    finalize: 'Preparing your final step…',
    brand: 'Placing your brand on matching products…',
    pqsg: 'Generating ideas with your logo…',
    ads: 'Preparing your ad offer…',
}[props.step] ?? 'Finding products that match your order…'));

const heading = computed(() => ({
    brand: 'Put your brand on more',
    pqsg: 'Your logo on more products',
    ads: '$250 in Google ads — for $29',
    finalize: 'Final step — make it exactly right',
}[props.step] ?? 'Complete your order'));
const sub = computed(() => ({
    brand: 'Add your logo, name and details to matching products — laid out automatically.',
    pqsg: 'Fresh ideas generated from your design — they appear below as they finish.',
    ads: 'Pay $29, get $250 of Google Display ads through our Layout.ai partnership. You approve the campaign before anything runs — get thousands of highly targeted visitors, or your money back.',
    finalize: 'Your design is approved and locked in. Fine-tune the quantity and material — the price updates as you go.',
}[props.step] ?? 'Customers who buy business cards often add these. Not personalised — ships ready to use.'));
const title = computed(() => ({
    finalize: 'Your final step',
    ads: 'Runmyprint × Layout.ai',
}[props.step] ?? 'Recommended for you'));

// Layout.ai "how it works" — shown under the generated concepts on the ads step
const adSteps = [
    { title: 'Layout.ai generates 100 ads', text: 'It takes your design and spins up ~100 on-brand ad variations — headlines, layouts and colours.' },
    { title: 'It tests them, keeps the winners', text: 'Every variation is scored and tested automatically, so only the highest-performing creatives survive.' },
    { title: 'You approve the best', text: 'Review the top ads and give the go-ahead. Nothing spends a cent until you approve.' },
    { title: 'You get thousands of visitors', text: 'The winners run on Google’s network and send thousands of highly targeted visitors to your shop — or your money back.' },
];

// Layout.ai ALSO writes & runs Google *Search* ads for the customer — one box
// per targeted keyword (2 per line). These start as a generic example and are
// swapped for the buyer's real brand data (4 Google keywords + company) as soon
// as the async brand-profile endpoint reports ready (see initBrandProfile).
const DEFAULT_SEARCH_BOXES = [
    { query: 'business cards near me', url: 'northwind.co/business-cards', title: 'Custom Business Cards from $19', description: 'Premium stock, matte or gloss. 500 cards with fast turnaround.' },
    { query: 'flyer printing services', url: 'northwind.co/flyers', title: 'Full-Colour Flyers, Next-Day', description: 'Same-day proofs and next-day dispatch across the US.' },
    { query: 'custom banners for events', url: 'northwind.co/signage', title: 'Banners & Signs for Any Event', description: 'Durable, weatherproof signage in custom sizes.' },
    { query: 'branded stationery online', url: 'northwind.co/stationery', title: 'Branded Stationery & Notepads', description: 'Letterheads and notepads matched to your logo and colours.' },
];
const searchBoxes = ref(DEFAULT_SEARCH_BOXES);
const searchIsReal = ref(false);

// fun step names for the progress line — nicer than "Step 2 of 4"
const stepName = computed(() => ({
    finalize: 'The finishing touch',
    related: 'An impressive accessories selection',
    pqsg: "Your logo's world tour",
    ads: 'The ad studio',
    brand: 'Your brand on more good stuff',
}[props.step] ?? 'A little something extra'));

// ---- pqSmartGenerator gallery steps ----------------------------------------
// Two steps share the engine capture (registered back at Review) and both
// render NATIVELY from our feed proxy (/pqsg/feed?set=…): 'pqsg' shows the
// buyer's logo on the merch set as accessory-style cards, 'ads' shows the
// Layout.ai facebook-ad canvases. The third-party widget is gone — it never
// displayed the preview-less ad creatives (template_preview type) at all.
const pqsgWaiting = ref(true);
const pqsgEmpty = ref(false);   // capture finished without a displayable image
const pqsgItems = ref([]);      // [{key, img, label, product}] streamed from /pqsg/feed
const pqsgDone = ref(false);    // engine settled — stop the "generating live" pulse
let pqsgTimer = null;
let pqsgInitFor = null;

// NOTE: advancing between steps re-renders this SAME component with new props
// (Inertia reuses the page instance), so onMounted alone never fires past the
// first step. Init on mount AND on the step changing.
function initPqsg() {
    if (props.step === 'ads') initBrandProfile();
    if (pqsgInitFor === props.step || !props.payload?.key) return;
    if (props.step === 'pqsg' || props.step === 'ads') initFeed(props.step);
}

// ---- Layout.ai search-ads: real keywords from the async brand-profile API -----
let brandTimer = null;
let brandInit = false;

function domainFromCompany(name) {
    const base = String(name || '').toLowerCase().replace(/&/g, ' and ').replace(/[^a-z0-9]+/g, '');
    return base ? `${base.slice(0, 30)}.com` : 'yourbrand.com';
}
function titleCase(s) {
    return String(s || '').replace(/\b\w/g, (c) => c.toUpperCase());
}
function boxesFromBrand(data) {
    const kws = (data.keywords || []).filter(Boolean).slice(0, 4);
    if (kws.length < 1) return null;
    const domain = domainFromCompany(data.company);
    const desc = String(data.description || '').replace(/\s+/g, ' ').trim();
    const shortDesc = desc
        ? (desc.length > 95 ? `${desc.slice(0, 92).trimEnd()}…` : desc)
        : 'Quality print and promo, designed and delivered for your business.';
    return kws.map((kw) => {
        const path = String(kw).toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').split('-').slice(-2).join('-');
        return { query: kw, url: path ? `${domain}/${path}` : domain, title: titleCase(kw), description: shortDesc };
    });
}
// Poll the brand-profile proxy every ~4s; swap in the buyer's real keywords when
// ready. Until then (or if it never completes) the generic example stays.
function initBrandProfile() {
    if (brandInit || !props.payload?.key) return;
    brandInit = true;
    const deadline = Date.now() + 3 * 60 * 1000;
    const poll = async () => {
        try {
            const r = await fetch(`/pqsg/brand-profile/${props.payload.key}${props.payload.uuid ? `?uuid=${props.payload.uuid}` : ''}`, { headers: { Accept: 'application/json' } });
            const boxes = boxesFromBrand(await r.json());
            if (boxes) { searchBoxes.value = boxes; searchIsReal.value = true; return; }
        } catch (e) { /* keep the example */ }
        if (Date.now() < deadline) brandTimer = setTimeout(poll, 4000);
    };
    poll();
}

// Poll our feed proxy and render native cards/tiles as the engine finishes them.
function initFeed(kind) {
    pqsgInitFor = kind;
    pqsgWaiting.value = true;
    pqsgEmpty.value = false;
    pqsgDone.value = false;
    pqsgItems.value = [];
    if (pqsgTimer) { clearTimeout(pqsgTimer); pqsgTimer = null; }

    const set = kind === 'ads' ? 'ads' : 'merch';
    const deadline = Date.now() + 4 * 60 * 1000; // results stream ~1 min; give up quietly after 4
    const poll = async () => {
        if (pqsgInitFor !== kind) return;
        let done = false;
        try {
            const r = await fetch(`/pqsg/feed/${props.payload.key}?set=${set}${props.payload.uuid ? `&uuid=${props.payload.uuid}` : ''}`, { headers: { Accept: 'application/json' } });
            const data = await r.json();
            done = !!data.done;
            if (data.images?.length) {
                pqsgItems.value = data.images;
                pqsgWaiting.value = false;
            }
        } catch (e) { /* best-effort */ }

        if (done || Date.now() > deadline) {
            pqsgDone.value = true;
            pqsgWaiting.value = false;
            if (!pqsgItems.value.length) pqsgEmpty.value = true;
            return;
        }
        // 1s flat — the shopper should see every result the moment it exists
        // (the 1s server cache keeps the engine traffic bounded)
        pqsgTimer = setTimeout(poll, 1000);
    };
    poll();
}

onMounted(() => { armStepLoader(); initPqsg(); });
watch(() => props.step, () => { armStepLoader(); initPqsg(); }, { flush: 'post' });

onBeforeUnmount(() => {
    if (pqsgTimer) clearTimeout(pqsgTimer);
    if (brandTimer) clearTimeout(brandTimer);
    if (stepLoadTimer) clearTimeout(stepLoadTimer);
});
// ----------------------------------------------------------------------------

function addItem(p) {
    if (added.value[p.slug] || busy.value) return;
    busy.value = p.slug;
    added.value = { ...added.value, [p.slug]: true }; // optimistic
    router.post(`/upsell/add/${p.slug}`,
        { brand: props.step === 'brand' ? (props.payload.brand || null) : null },
        { preserveScroll: true, preserveState: true, onFinish: () => (busy.value = null) });
}
function next() {
    router.post('/upsell/next');
}
</script>

<template>
    <Head :title="title" />
    <StoreLayout>
        <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 sm:py-10">
            <!-- progress -->
            <p class="text-sm font-medium text-ink/70">
                <span class="font-semibold text-brand-700">{{ stepName }}</span>
                <span class="text-ink/45"> · {{ stepIndex }} of {{ stepCount }} · before checkout</span>
            </p>
            <div class="mt-2 flex gap-1.5">
                <div v-for="i in stepCount" :key="i" class="h-1.5 flex-1 rounded-full transition-colors" :class="i <= stepIndex ? 'bg-brand-600' : 'bg-paper-300'"></div>
            </div>

            <!-- 1s "preparing" beat before each step reveals — everything below the
                 progress dots (shipping bar + Continue included) waits behind it -->
            <div v-if="stepLoading" class="flex flex-col items-center justify-center py-24 text-center sm:py-32">
                <div class="relative h-14 w-14">
                    <div class="absolute inset-0 animate-spin rounded-full border-[3px] border-paper-300 border-t-brand-600"></div>
                    <svg class="absolute inset-0 m-auto h-6 w-6 text-brand-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                        <path d="M12 3l1.7 4.6L18 9l-4.3 1.4L12 15l-1.7-4.6L6 9l4.3-1.4z" stroke-linejoin="round" />
                    </svg>
                </div>
                <p class="mt-5 font-display text-lg font-semibold text-ink">{{ loaderText }}</p>
                <p class="mt-1 text-sm text-ink/55">Personalising this step for your order</p>
                <div class="mt-5 h-1.5 w-64 max-w-full overflow-hidden rounded-full bg-paper-300">
                    <div class="steploadbar h-full rounded-full bg-brand-600"></div>
                </div>
            </div>

            <template v-else>
            <FreeShippingBar class="mt-5" :subtotal="summary.subtotal" :threshold="summary.threshold" :remaining="summary.remaining" :qualifies="summary.qualifies" />
            <div class="mt-3 flex justify-end">
                <button class="inline-flex items-center gap-1.5 rounded-full border border-brand-600 px-5 py-2 text-sm font-semibold text-brand-700 transition hover:bg-brand-50" @click="next">{{ isLast ? 'Continue to cart →' : 'Continue →' }}</button>
            </div>

            <h1 class="mt-7 font-display text-3xl font-semibold tracking-tight sm:text-4xl">{{ heading }}</h1>
            <p class="mt-2 max-w-2xl text-ink/60">{{ sub }}</p>

            <!-- pqSmartGenerator merch gallery: engine mockups rendered as OUR cards
                 (same grid + card system as the accessories step below) -->
            <div v-if="step === 'pqsg'" class="mt-7">
                <div v-if="pqsgWaiting" class="grid place-items-center rounded-2xl border border-dashed border-paper-300 bg-paper-200/50 py-14 text-center">
                    <div>
                        <div class="mx-auto h-8 w-8 animate-spin rounded-full border-2 border-brand-600 border-t-transparent"></div>
                        <p class="mt-3 text-sm text-ink/55">Generating ideas with your logo — this takes a moment.<br />You can continue to your cart any time.</p>
                    </div>
                </div>
                <p v-else-if="pqsgEmpty" class="rounded-2xl border border-paper-300 bg-paper-200/50 px-5 py-8 text-center text-sm text-ink/55">
                    We couldn't generate previews from your design this time — your order isn't affected.
                </p>
                <template v-else>
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <p class="text-xs font-semibold uppercase tracking-widest text-ink/50">Fresh from your design</p>
                        <span v-if="!pqsgDone" class="inline-flex items-center gap-1.5 text-[11px] font-medium text-brand-700">
                            <span class="inline-block h-1.5 w-1.5 animate-pulse rounded-full bg-brand-blue"></span>
                            generating live
                        </span>
                        <span v-else class="text-[11px] font-medium text-ink/45">{{ pqsgItems.length }} ideas · made with your logo</span>
                    </div>
                    <TransitionGroup name="pqsgcard" tag="div" class="grid grid-cols-2 gap-4 sm:gap-5 md:grid-cols-3 lg:grid-cols-4">
                        <div v-for="it in pqsgItems" :key="it.key" class="flex flex-col overflow-hidden rounded-2xl border border-paper-300 bg-white">
                            <div class="aspect-square overflow-hidden bg-paper-200">
                                <SmartImage :src="it.img" :alt="it.label || 'Your logo, mocked up'" />
                            </div>
                            <div class="flex flex-1 flex-col p-3">
                                <p class="font-display text-sm font-semibold text-ink">{{ it.label || 'Your brand, mocked up' }}</p>
                                <p v-if="it.product" class="text-xs text-ink/55">From {{ money(it.product.fromPrice) }}</p>
                                <p v-else class="text-xs text-ink/55">Made with your logo</p>
                                <button
                                    v-if="it.product"
                                    class="mt-3 w-full rounded-full px-4 py-2.5 text-sm font-semibold transition disabled:opacity-70"
                                    :class="added[it.product.slug] ? 'bg-brand-50 text-brand-700' : 'bg-brand-600 text-white hover:bg-brand-700'"
                                    :disabled="busy === it.product.slug || added[it.product.slug]"
                                    @click="addItem({ slug: it.product.slug })"
                                >
                                    {{ added[it.product.slug] ? '✓ Added' : busy === it.product.slug ? 'Adding…' : '+ Add to order' }}
                                </button>
                            </div>
                        </div>
                        <!-- streaming placeholder keeps the grid feeling alive while the set finishes -->
                        <div v-if="!pqsgDone" key="pqsg-more" class="flex flex-col overflow-hidden rounded-2xl border border-dashed border-paper-300 bg-paper-200/40">
                            <div class="grid aspect-square place-items-center">
                                <div class="h-6 w-6 animate-spin rounded-full border-2 border-brand-600/60 border-t-transparent"></div>
                            </div>
                            <div class="p-3">
                                <p class="font-display text-sm font-semibold text-ink/45">More on the way…</p>
                                <p class="text-xs text-ink/40">New ideas appear as they finish</p>
                            </div>
                        </div>
                    </TransitionGroup>
                </template>
            </div>

            <!-- Layout.ai ad-credit offer: promo visual + the buyer's own generated ad -->
            <div v-else-if="step === 'ads'" class="mt-7 space-y-6">
                <div class="relative grid overflow-hidden rounded-3xl bg-gradient-to-br from-navy via-navy to-navy-950 text-white shadow-2xl shadow-navy/20 lg:grid-cols-[1.1fr_1fr]">
                    <!-- generated promo visual -->
                    <div class="relative min-h-64 lg:min-h-[380px]">
                        <img v-if="payload.promoImage" :src="payload.promoImage" alt="$250 of Google Display ads for $29" class="absolute inset-0 h-full w-full object-cover" />
                        <div v-else class="absolute inset-0 bg-gradient-to-br from-brand-blue/50 to-navy"></div>
                        <div class="absolute inset-0 hidden bg-gradient-to-r from-transparent via-transparent to-navy lg:block"></div>
                        <div class="absolute inset-0 bg-gradient-to-t from-navy via-transparent to-transparent lg:hidden"></div>
                    </div>
                    <!-- offer copy -->
                    <div class="relative p-8 sm:p-10 lg:-ml-10">
                        <svg class="pointer-events-none absolute right-4 top-4 h-20 w-20 text-brand-blue opacity-20" viewBox="0 0 96 96" fill="none" aria-hidden="true">
                            <circle cx="48" cy="48" r="29" stroke="currentColor" stroke-width="1.5" />
                            <circle cx="48" cy="48" r="41" stroke="currentColor" stroke-dasharray="3 5" />
                            <path d="M48 12v12M48 84V72M12 48h12M84 48H72" stroke="currentColor" stroke-width="1.5" />
                        </svg>
                        <a href="https://layout.ai" target="_blank" rel="noopener" class="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-3.5 py-1.5 text-xs font-semibold uppercase tracking-widest text-[#9cc6ff] transition hover:bg-white/15">
                            Runmyprint × Layout.ai ↗
                        </a>
                        <h2 class="mt-5 font-display text-2xl font-bold leading-tight sm:text-3xl">
                            Pay $29, get <span class="text-lime-accent">$250</span> in Google Display ads
                        </h2>
                        <p class="mt-3 max-w-md text-white/70">Your first campaign, already designed. It runs on Google's network, managed by our partner <a href="https://layout.ai" target="_blank" rel="noopener" class="underline decoration-white/40 underline-offset-2 hover:text-white">Layout.ai</a> — and nothing goes live until you approve it.</p>
                        <ul class="mt-5 space-y-2.5 text-sm text-white/85">
                            <li v-for="g in ['Thousands of highly targeted visitors — or your money back', 'You approve the campaign before anything runs', 'Unused credit refunded', '8 ready-to-run ad designs (below)']" :key="g" class="flex items-center gap-2.5">
                                <svg class="h-4.5 w-4.5 shrink-0" viewBox="0 0 16 16" aria-hidden="true">
                                    <circle cx="8" cy="8" r="7" fill="none" stroke="#398aff" stroke-width="1.5" />
                                    <path d="m5 8.2 2 2L11 6" fill="none" stroke="#9cc6ff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                {{ g }}
                            </li>
                        </ul>
                        <!-- price anchor: make the 8.6× value obvious at a glance -->
                        <div class="mt-6 flex items-end gap-3">
                            <span class="font-display text-2xl font-semibold text-white/35 line-through decoration-2">$250</span>
                            <span class="font-display text-4xl font-extrabold leading-none text-lime-accent">$29</span>
                            <span class="mb-1 rounded-full bg-lime-accent/15 px-2.5 py-1 text-xs font-semibold text-lime-accent ring-1 ring-lime-accent/30">8.6× value · save $221</span>
                        </div>
                        <!-- real, honest urgency: the offer is scoped to this order -->
                        <p class="mt-2 flex items-center gap-1.5 text-sm font-medium text-[#9cc6ff]">
                            <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            Only available with this order — it won't be offered again at checkout.
                        </p>
                        <button type="button" :disabled="busy === 'ad-credit-250' || added['ad-credit-250']"
                                class="mt-4 rounded-full px-7 py-3 font-semibold transition disabled:opacity-80"
                                :class="added['ad-credit-250'] ? 'bg-white/15 text-lime-accent' : 'bg-brand-blue text-white shadow-lg shadow-brand-blue/30 hover:bg-[#2f78e0]'"
                                @click="addItem({ slug: 'ad-credit-250' })">
                            {{ added['ad-credit-250'] ? '✓ Added to your order — $29' : 'Add to my order — $29' }}
                        </button>
                        <!-- social proof -->
                        <p class="mt-3 flex items-center gap-2 text-sm text-white/75">
                            <span class="text-base tracking-tight text-lime-accent">★★★★★</span>
                            Trusted by <span class="font-semibold text-white">23,000+</span> small businesses
                        </p>
                        <!-- money-back guarantee: unmissable -->
                        <div class="mt-5 flex items-center gap-3 rounded-xl border border-lime-accent/40 bg-lime-accent/10 px-4 py-3">
                            <svg class="h-7 w-7 shrink-0 text-lime-accent" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                <path d="M12 3l7 3v5c0 4.4-3 8-7 10-4-2-7-5.6-7-10V6z" stroke-linejoin="round" />
                                <path d="m8.5 12 2.2 2.2L15.5 9.5" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            <p class="text-sm font-semibold text-white">Money-back guarantee: get <span class="text-lime-accent">thousands of highly targeted visitors</span> or your $29 is refunded in full.</p>
                        </div>
                        <p class="mt-4 text-xs text-white/45">One-time offer for new Runmyprint customers. The $29 is charged with your order; your campaign is fulfilled by our partner Layout.ai and nothing runs until you approve it. If it doesn't deliver, we refund the $29 — no questions asked.</p>
                    </div>
                </div>

                <!-- generated GOOGLE DISPLAY ads (facebook_ads_nano canvases) -->
                <div v-show="!pqsgEmpty">
                    <div class="mb-3">
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                            <h3 class="font-display text-xl font-bold text-navy sm:text-2xl">Your Google Display ads</h3>
                            <span class="rounded-full bg-brand-50 px-2.5 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-brand-700">Examples</span>
                        </div>
                        <p class="mt-1.5 max-w-2xl text-sm text-ink/60"><strong class="font-semibold text-ink/75">Example ads our engine just generated from your brand</strong> — a live sample of the kind of creatives we'll run for you across the Google Display Network (millions of websites, apps and YouTube). You approve the final campaign before anything goes live.</p>
                    </div>
                    <div v-if="pqsgWaiting" class="grid place-items-center rounded-2xl border border-dashed border-paper-300 bg-paper-200/50 py-10 text-center">
                        <div>
                            <div class="mx-auto h-8 w-8 animate-spin rounded-full border-2 border-brand-600 border-t-transparent"></div>
                            <p class="mt-3 text-sm text-ink/55">Designing your ad creatives — the first ones appear here in a moment.</p>
                        </div>
                    </div>
                    <!-- ad studio frame: gradient hairline, navy header, OUR ad tiles inside -->
                    <div v-show="!pqsgWaiting" class="rounded-2xl bg-gradient-to-br from-brand-blue/50 via-paper-300 to-lime-accent/40 p-[1.5px]">
                        <div class="overflow-hidden rounded-2xl bg-white">
                            <div class="flex flex-wrap items-center justify-between gap-3 bg-navy px-4 py-3 text-white">
                                <p class="flex items-center gap-2 text-xs font-semibold uppercase tracking-widest text-[#9cc6ff]">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M12 3l1.7 4.6L18 9l-4.3 1.4L12 15l-1.7-4.6L6 9l4.3-1.4z" stroke-linejoin="round"/></svg>
                                    Ad studio · your designs
                                </p>
                                <span class="rounded-full bg-white/10 px-2.5 py-1 text-[11px] font-medium text-white/75">
                                    {{ pqsgDone ? `${pqsgItems.length} concepts · ready for Google Display` : 'designing your campaign live…' }}
                                </span>
                            </div>
                            <div class="bg-paper-200/40 p-3 sm:p-4">
                                <TransitionGroup name="pqsgcard" tag="div" class="grid grid-cols-1 gap-3 sm:grid-cols-2 sm:gap-4">
                                    <div v-for="it in pqsgItems" :key="it.key" class="overflow-hidden rounded-xl border border-paper-300 bg-white shadow-sm">
                                        <img :src="it.img" :alt="it.label" loading="lazy" class="aspect-[1000/523] w-full object-cover" />
                                    </div>
                                    <div v-if="!pqsgDone" key="ads-more" class="grid aspect-[1000/523] w-full place-items-center rounded-xl border border-dashed border-paper-300 bg-white/60">
                                        <div class="text-center">
                                            <div class="mx-auto h-6 w-6 animate-spin rounded-full border-2 border-brand-blue/70 border-t-transparent"></div>
                                            <p class="mt-2 text-xs font-medium text-ink/45">More concepts rendering…</p>
                                        </div>
                                    </div>
                                </TransitionGroup>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- generated GOOGLE SEARCH ads (keywords from the brand-profile API) -->
                <div>
                    <div class="mb-3">
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                            <h3 class="font-display text-xl font-bold text-navy sm:text-2xl">Your Google Search ads</h3>
                            <span class="rounded-full bg-brand-50 px-2.5 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-brand-700">Examples</span>
                        </div>
                        <p class="mt-1.5 max-w-2xl text-sm text-ink/60"><strong class="font-semibold text-ink/75">Example keywords and ads we'll run for your business</strong> — these are the kinds of Google searches we'll target and bid on so people actively looking for what you offer land on your website and bring you traffic.</p>
                    </div>
                    <div class="rounded-2xl bg-gradient-to-br from-brand-blue/50 via-paper-300 to-lime-accent/40 p-[1.5px]">
                    <div class="overflow-hidden rounded-2xl bg-white">
                        <div class="flex flex-wrap items-center justify-between gap-3 bg-navy px-4 py-3 text-white">
                            <p class="flex items-center gap-2 text-xs font-semibold uppercase tracking-widest text-[#9cc6ff]">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="11" cy="11" r="7" /><path d="m21 21-4.3-4.3" stroke-linecap="round" /></svg>
                                Search ads · your keywords
                            </p>
                            <span class="rounded-full bg-white/10 px-2.5 py-1 text-[11px] font-medium text-white/75">also included · {{ searchIsReal ? 'your keywords' : 'example preview' }}</span>
                        </div>
                        <div class="p-3 sm:p-4">
                            <!-- 4 search-ad boxes, 2 per line — each a mini Google search -->
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 sm:gap-4">
                                <div v-for="(box, i) in searchBoxes" :key="i" class="overflow-hidden rounded-xl border border-paper-300 bg-white shadow-sm">
                                    <div class="flex items-center gap-2.5 border-b border-paper-100 bg-paper-100/50 px-3.5 py-3">
                                        <span class="select-none font-display text-base font-medium tracking-tight">
                                            <span style="color:#4285F4">G</span><span style="color:#EA4335">o</span><span style="color:#FBBC05">o</span><span style="color:#4285F4">g</span><span style="color:#34A853">l</span><span style="color:#EA4335">e</span>
                                        </span>
                                        <div class="flex flex-1 items-center gap-2 rounded-full border border-paper-300 bg-white px-3 py-1.5">
                                            <span class="flex-1 truncate text-sm text-ink">{{ box.query }}</span>
                                            <svg class="h-3.5 w-3.5 shrink-0 text-[#4285F4]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7" /><path d="m21 21-4.3-4.3" stroke-linecap="round" /></svg>
                                        </div>
                                    </div>
                                    <div class="px-4 py-3.5">
                                        <div class="flex items-center gap-1.5 text-[11px]">
                                            <span class="font-bold text-ink">Ad</span>
                                            <span class="text-ink/30">·</span>
                                            <span class="truncate text-ink/70">{{ box.url }}</span>
                                        </div>
                                        <h4 class="mt-0.5 text-base leading-snug text-[#1a0dab] hover:underline">{{ box.title }}</h4>
                                        <p class="mt-0.5 text-sm leading-relaxed text-ink/60">{{ box.description }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    </div>
                </div>

                <!-- how the campaign is built: sets expectations after they've seen the concepts -->
                <div class="overflow-hidden rounded-3xl bg-gradient-to-br from-navy via-navy to-navy-950 p-8 text-white shadow-xl shadow-navy/20 sm:p-10">
                    <p class="text-center text-xs font-semibold uppercase tracking-widest text-[#9cc6ff]">How your campaign is built</p>
                    <h3 class="mx-auto mt-2 max-w-xl text-center font-display text-2xl font-bold leading-tight sm:text-3xl">From your design to real visitors — in four steps</h3>
                    <div class="mt-9 grid gap-x-6 gap-y-8 sm:grid-cols-2 lg:grid-cols-4">
                        <div v-for="(s, i) in adSteps" :key="s.title" class="relative">
                            <!-- connector line between steps on desktop -->
                            <span v-if="i < adSteps.length - 1" class="pointer-events-none absolute left-14 top-5 hidden h-px w-full bg-gradient-to-r from-brand-blue/50 to-transparent lg:block"></span>
                            <span class="relative grid h-11 w-11 place-items-center rounded-full bg-lime-accent font-display text-lg font-bold text-navy">{{ i + 1 }}</span>
                            <h4 class="mt-4 font-display text-base font-semibold">{{ s.title }}</h4>
                            <p class="mt-1.5 text-sm leading-relaxed text-white/65">{{ s.text }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- final step: adjust quantity + non-surface options of the just-added design -->
            <FinalStep v-else-if="step === 'finalize'" :payload="payload" :summary="summary" />

            <!-- products -->
            <div v-else class="mt-7 grid grid-cols-2 gap-4 sm:gap-5 md:grid-cols-3 lg:grid-cols-4">
                <div v-for="p in products" :key="p.slug" class="flex flex-col overflow-hidden rounded-2xl border border-paper-300 bg-white">
                    <div class="aspect-square overflow-hidden bg-paper-200">
                        <BrandMockup v-if="step === 'brand'" :brand="payload.brand || {}" :variant="p.mockup" />
                        <SmartImage v-else :src="p.image" :alt="p.name" />
                    </div>
                    <div class="flex flex-1 flex-col p-3">
                        <p class="font-display text-sm font-semibold text-ink">{{ p.name }}</p>
                        <p class="text-xs text-ink/55">From {{ money(p.fromPrice) }}</p>
                        <button
                            class="mt-3 w-full rounded-full px-4 py-2.5 text-sm font-semibold transition disabled:opacity-70"
                            :class="added[p.slug] ? 'bg-brand-50 text-brand-700' : 'bg-brand-600 text-white hover:bg-brand-700'"
                            :disabled="busy === p.slug || added[p.slug]"
                            @click="addItem(p)"
                        >
                            {{ added[p.slug] ? '✓ Added' : busy === p.slug ? 'Adding…' : '+ Add to order' }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- continue (the final step carries its own CTA in the summary card) -->
            <div v-if="step !== 'finalize'" class="mt-10 flex flex-col-reverse items-center justify-between gap-4 border-t border-paper-300 pt-6 sm:flex-row">
                <button class="text-sm font-medium text-ink/55 transition hover:text-ink" @click="next">
                    {{ isLast ? 'No thanks, go to cart' : 'No thanks' }}
                </button>
                <button class="w-full rounded-full bg-brand-600 px-8 py-3.5 font-semibold text-white shadow-lg shadow-brand-600/20 transition hover:bg-brand-700 sm:w-auto" @click="next">
                    {{ isLast ? 'Continue to cart →' : 'Continue →' }}
                </button>
            </div>
            </template>
        </div>
    </StoreLayout>
</template>

<style scoped>
/* merch gallery cards stream in as the engine finishes them */
.pqsgcard-enter-from { opacity: 0; transform: translateY(10px) scale(0.98); }
.pqsgcard-enter-active { transition: opacity 0.45s ease, transform 0.45s ease; }
.pqsgcard-move { transition: transform 0.45s ease; }
.pqsgcard-leave-active { display: none; }
/* the step loader's 1s fill */
.steploadbar { width: 0; animation: steploadbar 1s ease-out forwards; }
@keyframes steploadbar { to { width: 100%; } }
</style>
