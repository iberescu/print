<script setup>
import { Head, Link } from '@inertiajs/vue3';
import StoreLayout from '../Layouts/StoreLayout.vue';
import ProductCard from '../Components/ProductCard.vue';

const props = defineProps({
    category: { type: Object, required: true },
    sections: { type: Array, default: () => [] },
    ungrouped: { type: Array, default: () => [] },
    products: { type: Array, default: () => [] },
});

function scrollToSection(slug) {
    document.getElementById(`sub-${slug}`)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}
</script>

<template>
    <Head :title="category.name" />
    <StoreLayout>
        <!-- limited-time offer banner (business cards) -->
        <a
            v-if="category.slug === 'business-cards'"
            href="/product/standard-business-cards"
            class="block bg-navy transition hover:bg-navy-950"
        >
            <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-center gap-x-3 gap-y-1 px-6 py-3 text-center text-white">
                <span class="rounded-full bg-lime-accent px-2.5 py-0.5 text-[11px] font-bold uppercase tracking-wide text-navy">Deal</span>
                <span class="text-sm font-semibold sm:text-base">Try 50 standard business cards for just <span class="text-lime-accent">$7.50</span></span>
                <span class="text-sm text-white/70 underline underline-offset-2">Shop now →</span>
            </div>
        </a>

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

        <!-- Shop by type: subcategory tiles that jump to their section -->
        <section v-if="sections.length" class="mx-auto max-w-7xl px-6 pt-10">
            <h2 class="mb-5 font-display text-xl font-semibold text-ink">Shop by type</h2>
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6">
                <a
                    v-for="s in sections" :key="s.slug"
                    :href="`#sub-${s.slug}`" @click.prevent="scrollToSection(s.slug)"
                    class="group flex flex-col overflow-hidden rounded-2xl border border-paper-300 bg-white transition hover:border-brand-400 hover:shadow-md"
                >
                    <div class="aspect-square overflow-hidden bg-paper-200">
                        <img v-if="s.image" :src="s.image" :alt="s.name" loading="lazy" class="h-full w-full object-cover transition duration-300 group-hover:scale-105" />
                    </div>
                    <div class="px-3 py-2.5">
                        <p class="font-display text-sm font-semibold leading-tight text-ink">{{ s.name }}</p>
                        <p class="mt-0.5 text-xs text-ink/50">{{ s.count }} {{ s.count === 1 ? 'product' : 'products' }}</p>
                    </div>
                </a>
            </div>
        </section>

        <!-- grouped product sections -->
        <template v-if="sections.length">
            <section
                v-for="s in sections" :key="s.slug"
                :id="`sub-${s.slug}`"
                class="mx-auto max-w-7xl scroll-mt-24 px-6 py-8"
            >
                <div class="mb-5 flex items-baseline justify-between border-b border-paper-300 pb-3">
                    <h2 class="font-display text-2xl font-semibold text-ink">{{ s.name }}</h2>
                    <span class="text-sm text-ink/50">{{ s.count }} {{ s.count === 1 ? 'product' : 'products' }}</span>
                </div>
                <div class="grid grid-cols-2 gap-5 md:grid-cols-3 lg:grid-cols-4">
                    <ProductCard v-for="p in s.products" :key="p.slug" :product="p" />
                </div>
            </section>

            <section v-if="ungrouped.length" class="mx-auto max-w-7xl px-6 py-8">
                <div class="mb-5 flex items-baseline justify-between border-b border-paper-300 pb-3">
                    <h2 class="font-display text-2xl font-semibold text-ink">More in {{ category.name }}</h2>
                    <span class="text-sm text-ink/50">{{ ungrouped.length }} products</span>
                </div>
                <div class="grid grid-cols-2 gap-5 md:grid-cols-3 lg:grid-cols-4">
                    <ProductCard v-for="p in ungrouped" :key="p.slug" :product="p" />
                </div>
            </section>
        </template>

        <!-- fallback: no subcategories → flat grid -->
        <section v-else class="mx-auto max-w-7xl px-6 py-12">
            <div class="mb-6 flex items-center justify-between">
                <span class="text-sm text-ink/50">{{ products.length }} products</span>
            </div>
            <div class="grid grid-cols-2 gap-5 md:grid-cols-3 lg:grid-cols-4">
                <ProductCard v-for="p in products" :key="p.slug" :product="p" />
            </div>
        </section>
    </StoreLayout>
</template>
