<script setup>
import { Head, Link } from '@inertiajs/vue3';
import StoreLayout from '../Layouts/StoreLayout.vue';
import ProductCard from '../Components/ProductCard.vue';

defineProps({
    category: { type: Object, required: true },
    products: { type: Array, default: () => [] },
});
</script>

<template>
    <Head :title="category.name" />
    <StoreLayout>
        <section class="border-b border-paper-300 bg-white/50">
            <div class="mx-auto max-w-7xl px-6 py-12">
                <nav class="text-sm text-ink/50">
                    <Link href="/" class="hover:text-ink">Home</Link>
                    <span class="mx-1.5">/</span>
                    <span class="text-ink/80">{{ category.name }}</span>
                </nav>
                <h1 class="mt-4 font-display text-4xl font-semibold tracking-tight sm:text-5xl">{{ category.name }}</h1>
                <p class="mt-3 max-w-2xl text-lg text-ink/60">{{ category.description || category.tagline }}</p>
            </div>
        </section>

        <section class="mx-auto max-w-7xl px-6 py-12">
            <div class="mb-6 flex items-center justify-between">
                <span class="text-sm text-ink/50">{{ products.length }} products</span>
            </div>
            <div class="grid grid-cols-2 gap-5 md:grid-cols-3 lg:grid-cols-4">
                <ProductCard v-for="p in products" :key="p.slug" :product="p" />
            </div>
        </section>
    </StoreLayout>
</template>
