<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import StoreLayout from '../Layouts/StoreLayout.vue';
import SmartImage from '../Components/SmartImage.vue';

const props = defineProps({
    product: { type: Object, required: true },
    freeShippingThreshold: { type: Number, default: 50 },
});

const money = (n) => '$' + Number(n).toFixed(2);

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
const total = computed(() => (selectedQty.value ? Number(selectedQty.value.total) + optionDeltas.value : 0));
const perUnit = computed(() =>
    selectedQty.value?.quantity ? total.value / selectedQty.value.quantity : 0
);
const remainingForFree = computed(() => Math.max(0, props.freeShippingThreshold - total.value));

function start(mode) {
    router.get(`/design/${props.product.slug}`, {
        mode,
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
                    <div class="mt-6 flex items-end gap-3 border-y border-paper-300 py-5">
                        <span class="font-display text-4xl font-semibold text-ink">{{ money(total) }}</span>
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

                        <!-- cards -->
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
                    </div>

                    <!-- quantity -->
                    <div class="mt-6">
                        <h3 class="mb-2.5 text-sm font-semibold text-ink">Quantity</h3>
                        <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-3">
                            <button
                                v-for="q in product.quantities" :key="q.id" type="button"
                                @click="selectedQtyId = q.id"
                                class="rounded-xl border px-3 py-2.5 text-center transition"
                                :class="selectedQtyId === q.id ? 'border-brand-600 bg-brand-50' : 'border-paper-300 bg-white hover:border-ink/25'"
                            >
                                <span class="block font-display text-lg font-semibold text-ink">{{ q.quantity }}</span>
                                <span class="block text-xs text-ink/50">{{ money(Number(q.total) + optionDeltas) }}</span>
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
                        <button
                            v-if="product.supportsDesign" type="button" @click="start('design')"
                            class="flex w-full items-center justify-center gap-2 rounded-full bg-brand-600 px-6 py-4 text-base font-semibold text-white shadow-lg shadow-brand-600/20 transition hover:bg-brand-700"
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
                        <button v-if="product.supportsDesign" type="button" @click="start('design')" class="w-full text-center text-sm font-medium text-brand-700 underline-offset-4 hover:underline">
                            or browse {{ product.name.toLowerCase() }} templates
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

            <!-- description -->
            <div class="mt-16 max-w-3xl">
                <h2 class="font-display text-2xl font-semibold tracking-tight">About {{ product.name }}</h2>
                <p class="mt-4 leading-relaxed text-ink/65">{{ product.description }}</p>
            </div>
        </div>
    </StoreLayout>
</template>
