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
    finalize: 'Final step — make it exactly right',
}[props.step] ?? 'Complete your order'));
const sub = computed(() => ({
    brand: 'Add your logo, name and details to matching products — laid out automatically.',
    pqsg: 'Fresh ideas generated from your design — they appear below as they finish.',
    finalize: 'Your design is approved and locked in. Fine-tune the quantity and material — the price updates as you go.',
}[props.step] ?? 'Customers who buy business cards often add these. Not personalised — ships ready to use.'));
const title = computed(() => (props.step === 'finalize' ? 'Your final step' : 'Recommended for you'));

// ---- pqSmartGenerator gallery step ----------------------------------------
// The capture was registered asynchronously back at the Review step; here we
// poll our status endpoint until the third-party UUID exists, then let the
// widget take over. The widget stays invisible until its first images arrive.
const pqsgWaiting = ref(true);
let pqsgTimer = null;
let pqsgStarted = false;

// NOTE: advancing related → pqsg re-renders this SAME component with new props
// (Inertia reuses the page instance), so onMounted alone never fires on the
// gallery step unless it happens to be the first one. Init on mount AND on the
// step changing; the post-flush watcher runs after the widget element exists.
function initPqsg() {
    if (pqsgStarted || props.step !== 'pqsg' || !props.payload?.key) return;
    pqsgStarted = true;

    if (!document.querySelector('script[data-pqsg]')) {
        const s = document.createElement('script');
        s.src = props.payload.widgetSrc;
        s.defer = true;
        s.dataset.pqsg = '1';
        document.head.appendChild(s);
    }

    document.getElementById('pqsg-widget')
        ?.addEventListener('pqsg:ready', () => { pqsgWaiting.value = false; });

    let tries = 0;
    const poll = async () => {
        try {
            const r = await fetch(`/pqsg/status/${props.payload.key}`, { headers: { Accept: 'application/json' } });
            const { uuid } = await r.json();
            if (uuid) {
                const el = document.getElementById('pqsg-widget');
                el?.setAttribute('uuid', uuid);
                if (el && typeof el.start === 'function') el.start(uuid);
                return; // the widget polls their API from here on
            }
        } catch (e) { /* best-effort */ }
        if (++tries < 15) pqsgTimer = setTimeout(poll, 2000);
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
                <pq-smart-generator-widget
                    id="pqsg-widget"
                    :api-base="payload.apiBase"
                    grid="justified"
                    insert-mode="append"
                    class="block w-full"
                ></pq-smart-generator-widget>
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
