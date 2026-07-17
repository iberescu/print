<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import StoreLayout from '../Layouts/StoreLayout.vue';
import SmartImage from '../Components/SmartImage.vue';
import { money } from '../lib/format';
import { adsEvent } from '../lib/gads';
import { rtbOffer } from '../lib/rtb';
import { usePage } from '@inertiajs/vue3';

const props = defineProps({
    product: { type: Object, required: true },
    related: { type: Array, default: () => [] },
    freeShippingThreshold: { type: Number, default: 100 },
});


// SEO copy (generated, original) — description paragraphs, spec details, FAQ.
const seo = computed(() => props.product.seo || {});
const descParagraphs = computed(() =>
    String(seo.value.description || props.product.description || '')
        .split(/\n+/).map((s) => s.trim()).filter(Boolean)
);
const details = computed(() => seo.value.details || []);
const faq = computed(() => (seo.value.faq || []).filter((f) => f.q && f.a));
const metaDescription = computed(() =>
    (seo.value.description || props.product.tagline || props.product.name)
        .replace(/\s+/g, ' ').trim().slice(0, 155)
);

// Product + FAQ structured data (JSON-LD) + a product-specific meta description.
let ldEl = null;
let prevDesc = null;
onMounted(() => {
    // dynamic-remarketing signal — item id matches the merchant feed's g:id
    adsEvent('view_item', {
        value: Number(props.product.fromPrice || 0),
        items: [{ id: String(props.product.id), google_business_vertical: 'retail' }],
    });
    // RTB House offer view (brand-store context: alias feed id)
    rtbOffer(usePage().props.rtbAlias ?? null, props.product.slug);
    const meta = document.querySelector('meta[name="description"]');
    if (meta) { prevDesc = meta.getAttribute('content'); meta.setAttribute('content', metaDescription.value); }
    const p = props.product;
    const abs = (u) => (!u ? undefined : u.startsWith('http') ? u : window.location.origin + u);
    const fromPrice = Number(p.fromPrice || 0);
    // Google drops the ENTIRE Product snippet when the Offer price is 0/invalid, so
    // only attach offers when there's a real starting price. (undefined keys are
    // stripped by JSON.stringify, so a missing image/category just falls away.)
    const offers = fromPrice > 0 ? {
        '@type': 'Offer', priceCurrency: 'USD',
        price: fromPrice.toFixed(2),
        availability: 'https://schema.org/InStock',
        itemCondition: 'https://schema.org/NewCondition',
        priceValidUntil: new Date(Date.now() + 365 * 864e5).toISOString().slice(0, 10),
        url: window.location.href,
        seller: { '@type': 'Organization', name: 'RunMyPrint' },
        // Merchant-listing recommended fields (fixes Search Console warnings).
        hasMerchantReturnPolicy: {
            '@type': 'MerchantReturnPolicy',
            applicableCountry: 'US',
            returnPolicyCategory: 'https://schema.org/MerchantReturnFiniteReturnWindow',
            merchantReturnDays: 30,
            returnMethod: 'https://schema.org/ReturnByMail',
            returnFees: 'https://schema.org/FreeReturn',
        },
        shippingDetails: {
            '@type': 'OfferShippingDetails',
            shippingRate: { '@type': 'MonetaryAmount', value: '7.99', currency: 'USD' },
            shippingDestination: { '@type': 'DefinedRegion', addressCountry: 'US' },
            deliveryTime: {
                '@type': 'ShippingDeliveryTime',
                handlingTime: { '@type': 'QuantitativeValue', minValue: 1, maxValue: 3, unitCode: 'DAY' },
                transitTime: { '@type': 'QuantitativeValue', minValue: 3, maxValue: 8, unitCode: 'DAY' },
            },
        },
    } : undefined;
    const graph = [{
        '@context': 'https://schema.org', '@type': 'Product',
        name: p.name,
        description: (seo.value.description || p.tagline || `${p.name} — custom printed by RunMyPrint.`).replace(/\s+/g, ' ').trim().slice(0, 500),
        category: p.category?.name,
        image: p.image ? [abs(p.image)] : undefined,
        sku: String(p.id),          // matches the merchant feed g:id
        brand: { '@type': 'Brand', name: 'RunMyPrint' },
        url: window.location.href,
        offers,
    }];
    if (faq.value.length) {
        graph.push({
            '@context': 'https://schema.org', '@type': 'FAQPage',
            mainEntity: faq.value.map((f) => ({
                '@type': 'Question', name: f.q,
                acceptedAnswer: { '@type': 'Answer', text: f.a },
            })),
        });
    }
    ldEl = document.createElement('script');
    ldEl.type = 'application/ld+json';
    ldEl.text = JSON.stringify(graph);
    document.head.appendChild(ldEl);
});
onBeforeUnmount(() => {
    if (ldEl) { ldEl.remove(); ldEl = null; }
    const meta = document.querySelector('meta[name="description"]');
    if (meta && prevDesc !== null) meta.setAttribute('content', prevDesc);
});

const initial = {};
props.product.options.forEach((o) => {
    const def = o.values.find((v) => v.isDefault) ?? o.values[0];
    if (def) initial[o.id] = def.id;
});
const selectedValues = ref(initial);
const selectedQtyId = ref(
    (props.product.quantities.find((q) => q.isDefault) ?? props.product.quantities[0])?.id
);

const selectedQty = computed(() => props.product.quantities.find((q) => q.id === selectedQtyId.value));
const optionDeltas = computed(() => {
    let sum = 0;
    props.product.options.forEach((o) => {
        const v = o.values.find((x) => x.id === selectedValues.value[o.id]);
        if (v) sum += Number(v.priceDelta);
    });
    return sum;
});
// specs (weight, thickness, width/height…) for the currently-selected value of an option
const specsFor = (o) => {
    const v = o.values.find((x) => x.id === selectedValues.value[o.id]);
    return (v?.attributes ?? []).filter((a) => a.name || a.value);
};

const total = computed(() => (selectedQty.value ? Number(selectedQty.value.total) + optionDeltas.value : 0));
const perUnit = computed(() =>
    selectedQty.value?.quantity ? total.value / selectedQty.value.quantity : 0
);
const remainingForFree = computed(() => Math.max(0, props.freeShippingThreshold - total.value));

// bulk / promo pricing: a tier may carry a pre-discount "compare at" price
const tierDiscount = (q) => (q.compareAtTotal && q.compareAtTotal > q.total ? Math.round((1 - q.total / q.compareAtTotal) * 100) : 0);
const compareAtTotal = computed(() => {
    const c = selectedQty.value?.compareAtTotal;
    return c && c > selectedQty.value.total ? Number(c) + optionDeltas.value : null;
});
const discountPct = computed(() => (selectedQty.value ? tierDiscount(selectedQty.value) : 0));

function start(mode) {
    router.get(`/design/${props.product.slug}`, {
        mode,
        qty: selectedQtyId.value,
        opts: Object.values(selectedValues.value),
    });
}

// Browse templates first (gallery), carrying the chosen quantity + options.
function browseTemplates() {
    router.get(`/design/${props.product.slug}/templates`, {
        qty: selectedQtyId.value,
        opts: Object.values(selectedValues.value),
    });
}

// Non-personalised products (accessories) skip the designer and add straight to cart.
function addDirect() {
    router.post(`/cart/add/${props.product.slug}`, {
        quantityId: selectedQtyId.value,
        optionValueIds: Object.values(selectedValues.value),
    });
}
</script>

<template>
    <Head :title="product.name" />
    <StoreLayout>
        <div class="mx-auto max-w-7xl px-6 py-8">
            <nav class="text-sm text-ink/50">
                <Link href="/" class="hover:text-ink">Home</Link>
                <span class="mx-1.5">/</span>
                <Link :href="`/category/${product.category.slug}`" class="hover:text-ink">{{ product.category.name }}</Link>
                <span class="mx-1.5">/</span>
                <span class="text-ink/80">{{ product.name }}</span>
            </nav>

            <div class="mt-6 grid gap-10 lg:grid-cols-2 lg:gap-14">
                <!-- GALLERY -->
                <div>
                    <div class="crop-corners aspect-square overflow-hidden rounded-3xl border border-paper-300 bg-paper-200 text-ink shadow-xl shadow-ink/5">
                        <SmartImage :src="product.image" :alt="product.name" />
                    </div>
                    <div class="mt-4 grid grid-cols-4 gap-3">
                        <div v-for="i in 4" :key="i" class="aspect-square overflow-hidden rounded-xl border border-paper-300 bg-paper-200" :class="i === 1 ? 'ring-2 ring-brand-600' : ''">
                            <SmartImage :src="product.image" :alt="product.name" />
                        </div>
                    </div>
                </div>

                <!-- BUY PANEL -->
                <div>
                    <p class="text-xs font-semibold uppercase tracking-widest text-brand-700/70">{{ product.category.name }}</p>
                    <h1 class="mt-1.5 font-display text-4xl font-semibold tracking-tight text-ink">{{ product.name }}</h1>
                    <p class="mt-2 text-lg text-ink/60">{{ product.tagline }}</p>

                    <!-- price -->
                    <div class="mt-6 flex flex-wrap items-end gap-x-3 gap-y-1 border-y border-paper-300 py-5">
                        <span class="font-display text-4xl font-semibold text-ink">{{ money(total) }}</span>
                        <span v-if="compareAtTotal" class="pb-1 text-lg text-ink/40 line-through">{{ money(compareAtTotal) }}</span>
                        <span v-if="discountPct" class="mb-1 rounded-full bg-lime-accent px-2 py-0.5 text-xs font-bold uppercase tracking-wide text-navy">Save {{ discountPct }}%</span>
                        <span class="pb-1 text-sm text-ink/55">{{ money(perUnit) }} each · {{ selectedQty?.quantity }} units</span>
                    </div>

                    <!-- options -->
                    <div v-for="o in product.options" :key="o.id" class="mt-6">
                        <div class="mb-2.5 flex items-baseline justify-between">
                            <h3 class="text-sm font-semibold text-ink">{{ o.name }}</h3>
                        </div>

                        <!-- swatches -->
                        <div v-if="o.type === 'swatch'" class="flex flex-wrap gap-2.5">
                            <button
                                v-for="v in o.values" :key="v.id" type="button"
                                :title="v.label" @click="selectedValues[o.id] = v.id"
                                class="h-9 w-9 rounded-full border shadow-sm transition"
                                :class="selectedValues[o.id] === v.id ? 'ring-2 ring-brand-600 ring-offset-2 ring-offset-paper border-transparent' : 'border-paper-300 hover:scale-105'"
                                :style="{ backgroundColor: v.swatch || '#ccc' }"
                            ></button>
                        </div>

                        <!-- select (5+ values — like vistaprint) -->
                        <div v-else-if="o.values.length > 4" class="relative">
                            <select
                                v-model="selectedValues[o.id]"
                                class="w-full appearance-none rounded-xl border border-paper-300 bg-white py-3 pl-4 pr-11 text-sm font-medium text-ink shadow-sm transition hover:border-ink/25 focus:border-brand-600 focus:outline-none focus:ring-2 focus:ring-brand-600/20"
                            >
                                <option v-for="v in o.values" :key="v.id" :value="v.id">{{ v.label }}{{ Number(v.priceDelta) > 0 ? ` (+${money(v.priceDelta)})` : '' }}{{ v.badge ? ` · ${v.badge}` : '' }}</option>
                            </select>
                            <svg class="pointer-events-none absolute right-4 top-1/2 h-4 w-4 -translate-y-1/2 text-ink/45" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6" stroke-linecap="round" stroke-linejoin="round" /></svg>
                        </div>

                        <!-- cards (≤4 values) -->
                        <div v-else class="flex flex-wrap gap-2.5">
                            <button
                                v-for="v in o.values" :key="v.id" type="button"
                                @click="selectedValues[o.id] = v.id"
                                class="relative rounded-xl border px-4 py-2.5 text-left text-sm transition"
                                :class="selectedValues[o.id] === v.id ? 'border-brand-600 bg-brand-50 text-ink' : 'border-paper-300 bg-white text-ink/75 hover:border-ink/25'"
                            >
                                <span class="font-medium">{{ v.label }}</span>
                                <span v-if="Number(v.priceDelta) > 0" class="ml-1 text-ink/45">+{{ money(v.priceDelta) }}</span>
                                <span v-if="v.badge" class="ml-2 rounded-full bg-lime-accent px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-ink">{{ v.badge }}</span>
                            </button>
                        </div>

                        <!-- specs for the selected value (weight, thickness, dimensions…) -->
                        <div v-if="specsFor(o).length" class="mt-2.5 flex flex-wrap gap-x-4 gap-y-1 text-xs text-ink/55">
                            <span v-for="a in specsFor(o)" :key="a.name"><span class="font-medium text-ink/75">{{ a.name }}:</span> {{ a.value }}</span>
                        </div>
                    </div>

                    <!-- quantity -->
                    <div class="mt-6">
                        <h3 class="mb-2.5 text-sm font-semibold text-ink">Quantity</h3>
                        <!-- select (5+ tiers — like vistaprint) -->
                        <div v-if="product.quantities.length > 4" class="relative">
                            <select
                                v-model="selectedQtyId"
                                class="w-full appearance-none rounded-xl border border-paper-300 bg-white py-3 pl-4 pr-11 text-sm font-medium text-ink shadow-sm transition hover:border-ink/25 focus:border-brand-600 focus:outline-none focus:ring-2 focus:ring-brand-600/20"
                            >
                                <option v-for="q in product.quantities" :key="q.id" :value="q.id">{{ q.quantity }} units — {{ money(Number(q.total) + optionDeltas) }} · {{ money((Number(q.total) + optionDeltas) / q.quantity) }} / unit</option>
                            </select>
                            <svg class="pointer-events-none absolute right-4 top-1/2 h-4 w-4 -translate-y-1/2 text-ink/45" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6" stroke-linecap="round" stroke-linejoin="round" /></svg>
                        </div>
                        <!-- tiles (≤4 tiers) -->
                        <div v-else class="grid grid-cols-2 gap-2.5 sm:grid-cols-3">
                            <button
                                v-for="q in product.quantities" :key="q.id" type="button"
                                @click="selectedQtyId = q.id"
                                class="rounded-xl border px-3 py-2.5 text-center transition"
                                :class="selectedQtyId === q.id ? 'border-brand-600 bg-brand-50' : 'border-paper-300 bg-white hover:border-ink/25'"
                            >
                                <span class="block font-display text-lg font-semibold text-ink">{{ q.quantity }}</span>
                                <span class="block text-xs text-ink/50">{{ money(Number(q.total) + optionDeltas) }}</span>
                                <span class="block text-[11px] text-ink/40">{{ money((Number(q.total) + optionDeltas) / q.quantity) }} / unit</span>
                            </button>
                        </div>
                    </div>

                    <!-- free shipping nudge -->
                    <p class="mt-5 flex items-center gap-2 text-sm">
                        <svg class="h-4 w-4 text-brand-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M3 7h11v9H3zM14 10h4l3 3v3h-7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        <span v-if="remainingForFree <= 0" class="font-medium text-brand-700">This order qualifies for free shipping!</span>
                        <span v-else class="text-ink/60">Add <strong class="text-ink">{{ money(remainingForFree) }}</strong> more for free shipping.</span>
                    </p>

                    <!-- CTAs -->
                    <div class="mt-6 space-y-3">
                        <!-- when templates exist they lead (blue), and Design online
                             drops to a white secondary; otherwise Design online is the
                             blue primary. -->
                        <button
                            v-if="product.supportsDesign && product.templateCount" type="button" @click="browseTemplates"
                            class="flex w-full items-center justify-center gap-2 rounded-full bg-brand-600 px-6 py-4 text-base font-semibold text-white shadow-lg shadow-brand-600/20 transition hover:bg-brand-700"
                        >
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>
                            Browse our templates
                            <span class="rounded-full bg-white/20 px-2 py-0.5 text-xs font-bold">{{ product.templateCount }}</span>
                        </button>
                        <button
                            v-if="product.supportsDesign" type="button" @click="start('design')"
                            class="flex w-full items-center justify-center gap-2 rounded-full px-6 py-4 text-base font-semibold transition"
                            :class="product.templateCount ? 'border border-brand-600/30 bg-white text-brand-700 hover:border-brand-600' : 'bg-brand-600 text-white shadow-lg shadow-brand-600/20 hover:bg-brand-700'"
                        >
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="m12 19 7-7-4-4-7 7-1 5zM15 5l4 4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            Design online
                        </button>
                        <button
                            v-if="product.supportsUpload" type="button" @click="start('upload')"
                            class="flex w-full items-center justify-center gap-2 rounded-full border border-ink/20 bg-white px-6 py-4 text-base font-semibold text-ink transition hover:border-ink/40"
                        >
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M12 16V4m0 0L8 8m4-4 4 4M5 20h14" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            Upload your design
                        </button>
                        <button
                            v-if="!product.supportsDesign && !product.supportsUpload" type="button" @click="addDirect"
                            class="flex w-full items-center justify-center gap-2 rounded-full bg-brand-600 px-6 py-4 text-base font-semibold text-white shadow-lg shadow-brand-600/20 transition hover:bg-brand-700"
                        >
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M5 7h15l-1.5 9.5a2 2 0 0 1-2 1.5H8.5a2 2 0 0 1-2-1.7L5 4H3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            Add to cart
                        </button>
                    </div>

                    <!-- trust -->
                    <div class="mt-8 grid grid-cols-3 gap-3 border-t border-paper-300 pt-6 text-center text-xs text-ink/55">
                        <div><p class="font-semibold text-ink">Free design help</p><p class="mt-0.5">Expert support</p></div>
                        <div><p class="font-semibold text-ink">Fast turnaround</p><p class="mt-0.5">2-day options</p></div>
                        <div><p class="font-semibold text-ink">100% guarantee</p><p class="mt-0.5">Love it or reprint</p></div>
                    </div>
                </div>
            </div>

            <!-- SEO content: description + details + FAQ -->
            <div class="mt-16 grid gap-10 lg:grid-cols-3 lg:gap-14">
                <div class="lg:col-span-2">
                    <h2 class="font-display text-2xl font-semibold tracking-tight">About {{ product.name }}</h2>
                    <div class="mt-4 space-y-4 leading-relaxed text-ink/65">
                        <p v-for="(para, i) in descParagraphs" :key="i">{{ para }}</p>
                    </div>

                    <template v-if="faq.length">
                        <h2 class="mt-12 font-display text-2xl font-semibold tracking-tight">Frequently asked questions</h2>
                        <div class="mt-4 divide-y divide-paper-300 border-y border-paper-300">
                            <details v-for="(f, i) in faq" :key="i" class="group py-4">
                                <summary class="flex cursor-pointer list-none items-center justify-between gap-4 text-[15px] font-medium text-ink">
                                    {{ f.q }}
                                    <svg class="h-5 w-5 shrink-0 text-ink/40 transition group-open:rotate-45" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M12 5v14M5 12h14" stroke-linecap="round" /></svg>
                                </summary>
                                <p class="mt-2.5 pr-8 text-sm leading-relaxed text-ink/60">{{ f.a }}</p>
                            </details>
                        </div>
                    </template>
                </div>

                <aside v-if="details.length">
                    <div class="rounded-2xl border border-paper-300 bg-paper-200/60 p-6 lg:sticky lg:top-24">
                        <h3 class="font-display text-lg font-semibold text-ink">Product details</h3>
                        <ul class="mt-4 space-y-2.5 text-sm text-ink/70">
                            <li v-for="(d, i) in details" :key="i" class="flex gap-2.5">
                                <svg class="mt-0.5 h-4 w-4 shrink-0 text-brand-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m5 13 4 4L19 7" stroke-linecap="round" stroke-linejoin="round" /></svg>
                                <span>{{ d }}</span>
                            </li>
                        </ul>
                    </div>
                </aside>
            </div>

            <!-- related products -->
            <div v-if="related.length" class="mt-16 border-t border-paper-300 pt-10">
                <h2 class="font-display text-2xl font-semibold tracking-tight">You might also like</h2>
                <div class="mt-6 grid grid-cols-2 gap-5 sm:grid-cols-4">
                    <Link v-for="r in related" :key="r.slug" :href="`/product/${r.slug}`" class="group">
                        <div class="crop-corners aspect-square overflow-hidden rounded-2xl border border-paper-300 bg-paper-200 transition group-hover:shadow-lg group-hover:shadow-ink/5">
                            <SmartImage :src="r.image" :alt="r.name" />
                        </div>
                        <p class="mt-3 text-sm font-medium text-ink transition group-hover:text-brand-700">{{ r.name }}</p>
                        <p v-if="r.tagline" class="truncate text-xs text-ink/50">{{ r.tagline }}</p>
                        <p class="mt-0.5 text-sm text-ink/70">from {{ money(r.fromPrice) }}</p>
                    </Link>
                </div>
            </div>
        </div>
    </StoreLayout>
</template>
