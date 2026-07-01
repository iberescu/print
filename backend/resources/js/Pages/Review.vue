<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import StoreLayout from '../Layouts/StoreLayout.vue';

const props = defineProps({
    product: { type: Object, required: true },
    category: { type: Object, default: () => ({}) },
    preview: { type: String, default: null },
    mode: { type: String, default: 'design' },
    design: { type: Object, default: () => ({}) },
    quote: { type: Object, default: () => ({}) },
});

const money = (n) => '$' + Number(n || 0).toFixed(2);
const approved = ref(false);
const busy = ref(false);

const backHref = computed(() => {
    const p = new URLSearchParams();
    p.set('mode', props.mode || 'design');
    if (props.design.quantityId) p.set('qty', props.design.quantityId);
    (props.design.optionValueIds || []).forEach((id) => p.append('opts[]', id));
    return `/design/${props.product.slug}?${p.toString()}`;
});

function addToCart() {
    if (!approved.value || busy.value) return;
    busy.value = true;
    router.post(`/cart/add/${props.product.slug}`, {
        quantityId: props.design.quantityId ?? null,
        optionValueIds: props.design.optionValueIds ?? [],
        preview: props.preview,
        brand: props.design.brand ?? null,
        mode: props.mode,
    }, { onFinish: () => (busy.value = false) });
}
</script>

<template>
    <Head title="Review your design" />
    <StoreLayout>
        <div class="mx-auto max-w-5xl px-6 py-8 sm:py-10">
            <!-- steps -->
            <div class="mb-6 flex items-center gap-2 text-sm font-medium">
                <span class="text-brand-700">✓ Design</span>
                <span class="text-ink/30">›</span>
                <span class="rounded-full bg-brand-50 px-3 py-1 text-brand-700">Review</span>
                <span class="text-ink/30">›</span>
                <span class="text-ink/40">Cart</span>
            </div>

            <h1 class="font-display text-3xl font-bold tracking-tight sm:text-4xl">Review your design</h1>
            <p class="mt-2 text-ink/60">Double-check the following details before you continue.</p>

            <div class="mt-8 grid gap-8 lg:grid-cols-[1fr_360px]">
                <!-- preview -->
                <div class="overflow-hidden rounded-2xl border border-paper-300 bg-paper-200 p-4 shadow-sm">
                    <p class="mb-3 text-xs font-semibold uppercase tracking-widest text-ink/45">Your design</p>
                    <div class="grid place-items-center rounded-xl bg-white p-4 shadow-inner">
                        <img v-if="preview" :src="preview" :alt="`${product.name} preview`" class="max-h-[420px] w-auto max-w-full rounded-md ring-1 ring-paper-300" />
                        <p v-else class="py-16 text-ink/40">No preview available.</p>
                    </div>
                </div>

                <!-- details + approve -->
                <div class="h-max rounded-2xl border border-paper-300 bg-white p-5 shadow-sm">
                    <h2 class="font-display text-base font-semibold text-ink">Details</h2>
                    <dl class="mt-3 space-y-2.5 text-sm">
                        <div class="flex justify-between gap-4"><dt class="text-ink/55">Product</dt><dd class="text-right font-medium text-ink">{{ product.name }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-ink/55">Type</dt><dd class="text-right font-medium text-ink">{{ mode === 'upload' ? 'Uploaded artwork' : 'Custom design' }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-ink/55">Quantity</dt><dd class="text-right font-medium text-ink">{{ quote.quantity }} units</dd></div>
                        <div v-for="(val, key) in quote.options" :key="key" class="flex justify-between gap-4"><dt class="text-ink/55">{{ key }}</dt><dd class="text-right font-medium text-ink">{{ val }}</dd></div>
                        <div class="flex justify-between gap-4 border-t border-paper-300 pt-2.5 text-base"><dt class="font-semibold">Total</dt><dd class="font-display font-bold text-ink">{{ money(quote.line_total) }}</dd></div>
                    </dl>

                    <label class="mt-5 flex cursor-pointer items-start gap-2.5 rounded-xl bg-paper-200 p-3 text-sm">
                        <input v-model="approved" type="checkbox" class="mt-0.5 h-4 w-4 shrink-0" />
                        <span class="text-ink/75">I have reviewed and approve my design.</span>
                    </label>

                    <button
                        :disabled="!approved || busy"
                        class="mt-4 w-full rounded-full bg-brand-600 px-6 py-3.5 font-semibold text-white shadow-lg shadow-brand-600/20 transition hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-50"
                        @click="addToCart"
                    >
                        {{ busy ? 'Adding…' : 'Add to cart →' }}
                    </button>
                    <Link :href="backHref" class="mt-3 block text-center text-sm text-ink/55 transition hover:text-ink">← Back to editor</Link>
                </div>
            </div>
        </div>
    </StoreLayout>
</template>
