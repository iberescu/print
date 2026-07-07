<script setup>
import { Head, Link } from '@inertiajs/vue3';
import StoreLayout from '../Layouts/StoreLayout.vue';
import SmartImage from '../Components/SmartImage.vue';
import { money } from '../lib/format';

defineProps({
    q: { type: String, default: '' },
    products: { type: Array, default: () => [] },
});
</script>

<template>
    <Head :title="q ? `Search — ${q}` : 'Search'" />
    <StoreLayout>
        <div class="mx-auto max-w-7xl px-6 py-10 sm:px-8">
            <p class="text-sm font-semibold uppercase tracking-widest text-brand-600">Search</p>
            <h1 class="mt-1 font-display text-3xl font-semibold tracking-tight">
                {{ products.length }} result{{ products.length === 1 ? '' : 's' }} for “{{ q }}”
            </h1>

            <div v-if="products.length" class="mt-8 grid grid-cols-2 gap-5 md:grid-cols-3 lg:grid-cols-4">
                <Link v-for="p in products" :key="p.slug" :href="`/product/${p.slug}`" class="group overflow-hidden rounded-2xl border border-paper-300 bg-white transition hover:-translate-y-1 hover:shadow-lg">
                    <div class="aspect-square overflow-hidden bg-paper-200">
                        <SmartImage :src="p.image" :alt="p.name" class="h-full w-full object-cover transition duration-500 group-hover:scale-105" />
                    </div>
                    <div class="p-3.5">
                        <p class="text-[11px] font-semibold uppercase tracking-widest text-brand-700/70">{{ p.category }}</p>
                        <p class="font-display font-semibold text-ink">{{ p.name }}</p>
                        <p class="text-xs text-ink/55">From {{ money(p.fromPrice) }}</p>
                    </div>
                </Link>
            </div>

            <div v-else class="mt-10 rounded-2xl border border-paper-300 bg-white p-12 text-center">
                <p class="text-ink/60">Nothing matched “{{ q }}”. Try a product type — “business cards”, “stickers”, “banner”…</p>
                <Link href="/" class="mt-5 inline-block rounded-full bg-brand-600 px-6 py-3 font-semibold text-white transition hover:bg-brand-700">Browse all products</Link>
            </div>
        </div>
    </StoreLayout>
</template>
