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

const heading = computed(() => ({
    brand: 'Put your brand on more',
    pqsg: 'Your logo on more products',
    ads: 'Get 3,000% the traffic for $50',
    finalize: 'Final step — make it exactly right',
}[props.step] ?? 'Complete your order'));
const sub = computed(() => ({
    brand: 'Add your logo, name and details to matching products — laid out automatically.',
    pqsg: 'Fresh ideas generated from your design — they appear below as they finish.',
    ads: 'Pay $50 and get $250 of Google Display ads through our Layout.ai partnership — your first campaign, already designed.',
    finalize: 'Your design is approved and locked in. Fine-tune the quantity and material — the price updates as you go.',
}[props.step] ?? 'Customers who buy business cards often add these. Not personalised — ships ready to use.'));
const title = computed(() => ({
    finalize: 'Your final step',
    ads: 'Runmyprint × Layout.ai',
}[props.step] ?? 'Recommended for you'));

// ---- pqSmartGenerator gallery steps ----------------------------------------
// Two steps share the engine capture (registered back at Review): 'pqsg' shows
// the buyer's logo on the merch set, 'ads' shows only the generated facebook-ad
// creative for the Layout.ai offer. The updated widget hides every product
// without a link, so each step passes its allow-list via `display-products`.
const PQSG_DISPLAY = {
    pqsg: [
        'business_card_qr_logo', 'roll_stickers', 'canvas', 'bottle', 'tshirt_words',
        'bags', 'cloudlab_sortv2', 'glass_logo', 'sticker', 'cloudlab_pix',
        'cloudlab_umbrela', 'cloudlab_usb', 'chocolate_bar', 'google_v2', 'office', 'hoodie',
    ],
    ads: ['pipeline_facebook_ad'],
};
const pqsgWaiting = ref(true);
const pqsgEmpty = ref(false);   // capture finished without a displayable image
let pqsgUuid = null;
let pqsgTimer = null;
let pqsgInitFor = null;

// NOTE: advancing between steps re-renders this SAME component with new props
// (Inertia reuses the page instance), so onMounted alone never fires past the
// first step. Init on mount AND on the step changing; the post-flush watcher
// runs after the step's own widget element exists in the DOM.
function initPqsg() {
    const kind = props.step;
    if (!PQSG_DISPLAY[kind] || pqsgInitFor === kind || !props.payload?.key) return;
    pqsgInitFor = kind;
    pqsgWaiting.value = true;
    pqsgEmpty.value = false;
    if (pqsgTimer) { clearTimeout(pqsgTimer); pqsgTimer = null; }

    if (!document.querySelector('script[data-pqsg]')) {
        const s = document.createElement('script');
        s.src = props.payload.widgetSrc;
        s.defer = true;
        s.dataset.pqsg = '1';
        document.head.appendChild(s);
    }

    const el = document.getElementById('pqsg-widget');
    if (!el) return;
    // attribute (not property) — safe whether or not the element has upgraded yet
    el.setAttribute('display-products', JSON.stringify(Object.fromEntries(PQSG_DISPLAY[kind].map((k) => [k, true]))));
    el.addEventListener('pqsg:ready', () => { pqsgWaiting.value = false; });
    // some captures (e.g. website-only) never produce this step's products —
    // when the engine settles without one, drop the placeholder instead of
    // spinning forever. `detail.images` is the widget's current artifact list.
    const settled = (e) => {
        if (pqsgInitFor !== kind) return;
        const wanted = new Set(PQSG_DISPLAY[kind]);
        const has = (e?.detail?.images || []).some((i) => wanted.has(i.product_key) || wanted.has(i.special_product_key));
        if (!has) pqsgEmpty.value = true;
        pqsgWaiting.value = false;
    };
    el.addEventListener('pqsg:complete', settled);
    el.addEventListener('pqsg:timeout', settled);
    el.addEventListener('pqsg:update', (e) => { if (e?.detail?.isComplete) settled(e); });

    const begin = (uuid) => {
        el.setAttribute('uuid', uuid);
        if (typeof el.start === 'function') el.start(uuid);
    };
    if (pqsgUuid) { begin(pqsgUuid); return; } // ads step reuses the uuid pqsg resolved

    let tries = 0;
    const poll = async () => {
        try {
            const r = await fetch(`/pqsg/status/${props.payload.key}`, { headers: { Accept: 'application/json' } });
            const { uuid } = await r.json();
            if (uuid) {
                pqsgUuid = uuid;
                begin(uuid);
                return; // the widget polls their API from here on
            }
        } catch (e) { /* best-effort */ }
        if (++tries < 15) {
            pqsgTimer = setTimeout(poll, 2000);
        } else if (pqsgInitFor === kind) {
            // capture never registered (engine rejected it) — show the graceful
            // empty state instead of spinning forever
            pqsgEmpty.value = true;
            pqsgWaiting.value = false;
        }
    };
    poll();
}

onMounted(initPqsg);
watch(() => props.step, () => initPqsg(), { flush: 'post' });

onBeforeUnmount(() => { if (pqsgTimer) clearTimeout(pqsgTimer); });
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
            <p class="text-sm font-medium text-ink/55">Step {{ stepIndex }} of {{ stepCount }} · before checkout</p>
            <div class="mt-2 flex gap-1.5">
                <div v-for="i in stepCount" :key="i" class="h-1.5 flex-1 rounded-full transition-colors" :class="i <= stepIndex ? 'bg-brand-600' : 'bg-paper-300'"></div>
            </div>

            <FreeShippingBar class="mt-5" :subtotal="summary.subtotal" :threshold="summary.threshold" :remaining="summary.remaining" :qualifies="summary.qualifies" />
            <div class="mt-3 flex justify-end">
                <button class="inline-flex items-center gap-1.5 rounded-full border border-brand-600 px-5 py-2 text-sm font-semibold text-brand-700 transition hover:bg-brand-50" @click="next">{{ isLast ? 'Continue to cart →' : 'Continue →' }}</button>
            </div>

            <h1 class="mt-7 font-display text-3xl font-semibold tracking-tight sm:text-4xl">{{ heading }}</h1>
            <p class="mt-2 max-w-2xl text-ink/60">{{ sub }}</p>

            <!-- pqSmartGenerator gallery (third-party): hidden until images arrive -->
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
                <!-- gallery frame: the widget itself is shadow-DOM sealed, so the
                     polish lives on the container + layout attributes. v-show keeps
                     the element in the DOM while hidden, so polling never stops. -->
                <div v-show="!pqsgWaiting && !pqsgEmpty" class="overflow-hidden rounded-2xl border border-paper-300 bg-white shadow-sm">
                    <div class="flex items-center justify-between gap-3 border-b border-paper-300 bg-paper-200/60 px-4 py-2.5">
                        <p class="text-xs font-semibold uppercase tracking-widest text-ink/50">Fresh from your design</p>
                        <span class="inline-flex items-center gap-1.5 text-[11px] font-medium text-brand-700">
                            <span class="inline-block h-1.5 w-1.5 animate-pulse rounded-full bg-brand-blue"></span>
                            generating live
                        </span>
                    </div>
                    <div class="p-3 sm:p-4">
                        <pq-smart-generator-widget
                            id="pqsg-widget"
                            :api-base="payload.apiBase"
                            grid="justified"
                            insert-mode="append"
                            gap="14"
                            justified-row-height="210"
                            class="block w-full"
                        ></pq-smart-generator-widget>
                    </div>
                </div>
            </div>

            <!-- Layout.ai ad-credit offer: promo visual + the buyer's own generated ad -->
            <div v-else-if="step === 'ads'" class="mt-7 space-y-6">
                <div class="relative grid overflow-hidden rounded-3xl bg-gradient-to-br from-navy via-navy to-navy-950 text-white shadow-2xl shadow-navy/20 lg:grid-cols-[1.1fr_1fr]">
                    <!-- generated promo visual -->
                    <div class="relative min-h-64 lg:min-h-[380px]">
                        <img v-if="payload.promoImage" :src="payload.promoImage" alt="$250 of Google Display ads for $50" class="absolute inset-0 h-full w-full object-cover" />
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
                        <span class="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-3.5 py-1.5 text-xs font-semibold uppercase tracking-widest text-[#9cc6ff]">
                            Runmyprint × Layout.ai
                        </span>
                        <h2 class="mt-5 font-display text-2xl font-bold leading-tight sm:text-3xl">
                            Pay $50, get <span class="text-lime-accent">$250</span> in Google Display ads
                        </h2>
                        <p class="mt-3 max-w-md text-white/70">Launch your new brand with 3,000% the traffic — your campaign runs on Google's network, managed by our partner Layout.ai.</p>
                        <ul class="mt-5 space-y-2.5 text-sm text-white/85">
                            <li v-for="g in ['1,000 visitors — guaranteed', '100,000 impressions — guaranteed', '8 ready-to-run ad designs (below)']" :key="g" class="flex items-center gap-2.5">
                                <svg class="h-4.5 w-4.5 shrink-0" viewBox="0 0 16 16" aria-hidden="true">
                                    <circle cx="8" cy="8" r="7" fill="none" stroke="#398aff" stroke-width="1.5" />
                                    <path d="m5 8.2 2 2L11 6" fill="none" stroke="#9cc6ff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                {{ g }}
                            </li>
                        </ul>
                        <p class="mt-5 text-xs text-white/45">One-time offer for new Runmyprint customers, applied through Layout.ai after checkout.</p>
                    </div>
                </div>

                <!-- the buyer's own ad from the same engine capture; some captures
                     never yield the ad creative — then the offer stands alone -->
                <div v-show="!pqsgEmpty">
                    <div v-if="pqsgWaiting" class="grid place-items-center rounded-2xl border border-dashed border-paper-300 bg-paper-200/50 py-10 text-center">
                        <div>
                            <div class="mx-auto h-8 w-8 animate-spin rounded-full border-2 border-brand-600 border-t-transparent"></div>
                            <p class="mt-3 text-sm text-ink/55">Designing your ad creatives — the first ones appear here in a moment.</p>
                        </div>
                    </div>
                    <!-- ad studio frame: gradient hairline, navy header, sealed widget inside -->
                    <div v-show="!pqsgWaiting" class="rounded-2xl bg-gradient-to-br from-brand-blue/50 via-paper-300 to-lime-accent/40 p-[1.5px]">
                        <div class="overflow-hidden rounded-2xl bg-white">
                            <div class="flex flex-wrap items-center justify-between gap-3 bg-navy px-4 py-3 text-white">
                                <p class="flex items-center gap-2 text-xs font-semibold uppercase tracking-widest text-[#9cc6ff]">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M12 3l1.7 4.6L18 9l-4.3 1.4L12 15l-1.7-4.6L6 9l4.3-1.4z" stroke-linejoin="round"/></svg>
                                    Ad studio · your designs
                                </p>
                                <span class="rounded-full bg-white/10 px-2.5 py-1 text-[11px] font-medium text-white/75">8 concepts · ready for Google Display</span>
                            </div>
                            <div class="bg-paper-200/40 p-3 sm:p-4">
                                <pq-smart-generator-widget
                                    id="pqsg-widget"
                                    :api-base="payload.apiBase"
                                    grid="justified"
                                    insert-mode="append"
                                    gap="14"
                                    justified-row-height="240"
                                    class="block w-full"
                                ></pq-smart-generator-widget>
                            </div>
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
        </div>
    </StoreLayout>
</template>
