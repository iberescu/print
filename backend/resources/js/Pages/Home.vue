<script setup>
import { Head, Link } from '@inertiajs/vue3';
import StoreLayout from '../Layouts/StoreLayout.vue';
import ProductCard from '../Components/ProductCard.vue';
import SmartImage from '../Components/SmartImage.vue';
import HeroSlider from '../Components/HeroSlider.vue';

const props = defineProps({
    categories: { type: Array, default: () => [] },
    featured: { type: Array, default: () => [] },
    heroImage: { type: String, default: null },
    freeShippingThreshold: { type: Number, default: 50 },
});

const slides = [
    { eyebrow: 'Premium custom printing', title: 'Everything to launch your brand', text: 'Business cards, flyers, signage, stickers, apparel and more — designed online, printed beautifully, delivered fast.', cta: 'Browse products', href: '#categories', image: props.heroImage },
    { eyebrow: 'New customer offer', title: 'Business cards from $10', text: '500 premium cards — design online in minutes or upload your own artwork.', cta: 'Shop business cards', href: '/category/business-cards', image: props.heroImage },
    { eyebrow: `Free shipping over $${props.freeShippingThreshold}`, title: 'Your logo, on everything', text: 'Use our free online designer and 200+ ready-made templates.', cta: 'Start designing', href: '/product/standard-business-cards', image: props.heroImage },
];

const tools = [
    { t: 'Design services', d: 'Let our experts design it for you.', i: 'm12 19 7-7-4-4-7 7-1 5zM15 5l4 4' },
    { t: 'Logo maker', d: 'Create a logo in minutes.', i: 'M4 4h16v16H4zM4 9h16' },
    { t: 'Free templates', d: '200+ professional designs.', i: 'M4 5h16M4 12h16M4 19h10' },
    { t: 'Order samples', d: 'Feel the quality before you buy.', i: 'M5 7h14v12H5zM5 7l7 5 7-5' },
];
</script>

<template>
    <Head title="Custom Printing for Business" />
    <StoreLayout>
        <HeroSlider :slides="slides" />

        <!-- trust strip -->
        <section class="border-y border-paper-300 bg-paper-200">
            <div class="mx-auto grid max-w-7xl grid-cols-2 gap-6 px-6 py-7 text-center text-sm md:grid-cols-4 sm:px-8">
                <div><p class="font-semibold text-ink">★ 4.8 / 5</p><p class="text-ink/55">12,000+ reviews</p></div>
                <div><p class="font-semibold text-ink">🚚 Free shipping</p><p class="text-ink/55">on orders over ${{ freeShippingThreshold }}</p></div>
                <div><p class="font-semibold text-ink">⚡ Fast turnaround</p><p class="text-ink/55">2-day options</p></div>
                <div><p class="font-semibold text-ink">✅ 100% guarantee</p><p class="text-ink/55">love it or reprint</p></div>
            </div>
        </section>

        <!-- explore all categories -->
        <section id="categories" class="mx-auto max-w-7xl px-6 py-16 sm:px-8 sm:py-20">
            <div class="mb-9 flex items-end justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-widest text-brand-600">Shop by category</p>
                    <h2 class="mt-2 font-display text-3xl font-bold tracking-tight sm:text-4xl">Explore the catalog</h2>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-5 sm:grid-cols-3 lg:grid-cols-6">
                <Link v-for="c in categories" :key="c.slug" :href="`/category/${c.slug}`" class="group text-center">
                    <div class="aspect-square overflow-hidden rounded-2xl border border-paper-300 bg-paper-200 shadow-sm transition duration-300 group-hover:-translate-y-1 group-hover:shadow-xl">
                        <SmartImage :src="c.image" :alt="c.name" class="transition duration-500 group-hover:scale-105" />
                    </div>
                    <p class="mt-3 text-sm font-semibold text-ink transition group-hover:text-brand-700">{{ c.name }}</p>
                </Link>
            </div>
        </section>

        <!-- bestselling products -->
        <section class="bg-paper-200">
            <div class="mx-auto max-w-7xl px-6 py-16 sm:px-8 sm:py-20">
                <div class="mb-9 flex items-end justify-between">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-widest text-brand-600">Most popular</p>
                        <h2 class="mt-2 font-display text-3xl font-bold tracking-tight sm:text-4xl">Bestselling products</h2>
                    </div>
                    <Link href="/category/business-cards" class="hidden text-sm font-semibold text-brand-700 hover:underline sm:block">See all →</Link>
                </div>
                <div class="grid grid-cols-2 gap-6 md:grid-cols-4">
                    <ProductCard v-for="p in featured" :key="p.slug" :product="p" />
                </div>
            </div>
        </section>

        <!-- samples / promo banner -->
        <section class="mx-auto max-w-7xl px-6 py-16 sm:px-8 sm:py-20">
            <div class="grid items-stretch overflow-hidden rounded-3xl bg-navy text-white shadow-2xl shadow-navy/20 md:grid-cols-2">
                <div class="p-10 sm:p-16">
                    <p class="text-sm font-semibold uppercase tracking-widest text-lime-accent">Not sure yet?</p>
                    <h2 class="mt-4 font-display text-3xl font-bold leading-tight sm:text-4xl">Order a free sample pack</h2>
                    <p class="mt-4 max-w-md text-white/70">Feel our paper stocks and finishes before you buy — premium quality you can hold.</p>
                    <a href="#categories" class="mt-8 inline-block rounded-full bg-lime-accent px-8 py-4 font-semibold text-navy transition hover:brightness-95">Get free samples</a>
                </div>
                <div class="min-h-56 md:min-h-0"><SmartImage :src="heroImage" alt="Sample pack" /></div>
            </div>
        </section>

        <!-- tools to help build your business -->
        <section class="mx-auto max-w-7xl px-6 pb-4 sm:px-8">
            <h2 class="mb-9 font-display text-3xl font-bold tracking-tight sm:text-4xl">Tools to grow your business</h2>
            <div class="grid grid-cols-2 gap-6 md:grid-cols-4">
                <div v-for="t in tools" :key="t.t" class="rounded-2xl border border-paper-300 bg-white p-7 shadow-sm transition duration-300 hover:-translate-y-1 hover:shadow-xl">
                    <span class="grid h-12 w-12 place-items-center rounded-xl bg-brand-50 text-brand-700">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path :d="t.i" stroke-linecap="round" stroke-linejoin="round" /></svg>
                    </span>
                    <h3 class="mt-5 font-semibold text-ink">{{ t.t }}</h3>
                    <p class="mt-1.5 text-sm text-ink/55">{{ t.d }}</p>
                </div>
            </div>
        </section>

        <!-- social proof -->
        <section class="mt-16 border-t border-paper-300 bg-paper-200 sm:mt-20">
            <div class="mx-auto max-w-7xl px-6 py-16 sm:px-8 sm:py-20">
                <h2 class="mb-9 text-center font-display text-3xl font-bold tracking-tight sm:text-4xl">Made by you <span class="text-brand-600">#MadeWithRunMyPrint</span></h2>
                <div class="grid grid-cols-3 gap-4 sm:grid-cols-6">
                    <div v-for="p in featured.slice(0, 6)" :key="p.slug" class="aspect-square overflow-hidden rounded-2xl border border-paper-300 bg-white shadow-sm">
                        <SmartImage :src="p.image" :alt="p.name" />
                    </div>
                </div>
            </div>
        </section>
    </StoreLayout>
</template>
