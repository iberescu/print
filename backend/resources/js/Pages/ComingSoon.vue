<script setup>
import { Head, Link } from '@inertiajs/vue3';
import StoreLayout from '../Layouts/StoreLayout.vue';

defineProps({
    title: { type: String, default: 'Coming soon' },
    message: { type: String, default: '' },
    product: { type: Object, default: null },
    mode: { type: String, default: null },
    pendingDesign: { type: Object, default: null },
});
</script>

<template>
    <Head :title="title" />
    <StoreLayout>
        <section class="mx-auto max-w-3xl px-6 py-20 text-center">
            <p class="text-xs font-semibold uppercase tracking-widest text-brand-700/70">Next phase</p>
            <h1 class="mt-3 font-display text-4xl font-semibold tracking-tight">{{ title }}</h1>
            <p class="mx-auto mt-4 max-w-lg text-ink/60">{{ message }}</p>

            <div v-if="pendingDesign" class="mx-auto mt-8 max-w-md overflow-hidden rounded-2xl border border-paper-300 bg-white text-left shadow-sm">
                <img v-if="pendingDesign.preview" :src="pendingDesign.preview" :alt="pendingDesign.product?.name" class="w-full border-b border-paper-300" />
                <div class="p-5">
                    <p class="text-sm text-ink/50">Saved design</p>
                    <p class="font-display text-lg font-semibold text-ink">{{ pendingDesign.product?.name }}</p>
                    <p class="mt-1 text-sm text-brand-700">✓ Ready for checkout (cart coming soon)</p>
                </div>
            </div>

            <div v-else-if="product" class="mx-auto mt-8 max-w-sm rounded-2xl border border-paper-300 bg-white p-5 text-left">
                <p class="text-sm text-ink/50">Selected product</p>
                <p class="font-display text-lg font-semibold text-ink">{{ product.name }}</p>
                <p v-if="mode" class="mt-1 text-sm capitalize text-brand-700">Mode: {{ mode }}</p>
            </div>

            <Link href="/" class="mt-10 inline-block rounded-full border border-ink/15 px-6 py-3 font-semibold text-ink transition hover:border-ink/30">← Back to shop</Link>
        </section>
    </StoreLayout>
</template>
