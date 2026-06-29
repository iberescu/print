<script setup>
import { Head, Link } from '@inertiajs/vue3';
import StoreLayout from '../Layouts/StoreLayout.vue';
import ProductCard from '../Components/ProductCard.vue';
import SmartImage from '../Components/SmartImage.vue';
import HeroSlider from '../Components/HeroSlider.vue';

const props = defineProps({
    categories: { type: Array, default: () => [] },
    featured: { type: Array, default: () => [] },
    freeShippingThreshold: { type: Number, default: 50 },
});

const slides = [
    { eyebrow: 'New customer offer', title: 'Business cards from $10', text: '500 premium cards — design online or upload your own artwork.', cta: 'Shop business cards', href: '/category/business-cards', image: '/storage/products/standard-business-cards.jpg' },
    { eyebrow: `Free shipping over $${props.freeShippingThreshold}`, title: 'Everything to launch your brand', text: 'Flyers, banners, stickers, signage and more — printed beautifully, delivered fast.', cta: 'Browse products', href: '#categories', image: '/storage/heroes/home.jpg' },
    { eyebrow: 'Design online, free', title: 'Your logo on everything', text: 'Use our online designer and 200+ ready-made templates.', cta: 'Start designing', href: '/product/standard-business-cards', image: '/storage/products/flyers.jpg' },
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
        <section class="border-y border-paper-300">
            <div class="mx-auto grid max-w-7xl grid-cols-2 gap-4 px-6 py-5 text-center text-sm md:grid-cols-4">
                <div><p class="font-semibold text-ink">★ 4.8 / 5</p><p class="text-ink/55">12,000+ reviews</p></div>
                <div><p class="font-semibold text-ink">🚚 Free shipping</p><p class="text-ink/55">on orders over ${{ freeShippingThreshold }}</p></div>
                <div><p class="font-semibold text-ink">⚡ Fast turnaround</p><p class="text-ink/55">2-day options</p></div>
                <div><p class="font-semibold text-ink">✅ 100% guarantee</p><p class="text-ink/55">love it or reprint</p></div>
            </div>
        </section>

        <!-- explore all categories -->
        <section id="categories" class="mx-auto max-w-7xl px-6 py-12">
            <h2 class="mb-6 font-display text-2xl font-bold tracking-tight">Explore all categories</h2>
            <div class="grid grid-cols-3 gap-4 sm:grid-cols-6">
                <Link v-for="c in categories" :key="c.slug" :href="`/category/${c.slug}`" class="group text-center">
                    <div class="aspect-square overflow-hidden border border-paper-300 bg-white">
                        <SmartImage :src="c.image" :alt="c.name" class="transition duration-500 group-hover:scale-105" />
                    </div>
                    <p class="mt-2 text-sm font-medium text-ink transition group-hover:text-brand-700">{{ c.name }}</p>
                </Link>
            </div>
        </section>

        <!-- bestselling products -->
        <section class="mx-auto max-w-7xl px-6 py-12">
            <div class="mb-6 flex items-end justify-between">
                <h2 class="font-display text-2xl font-bold tracking-tight">Bestselling products</h2>
                <Link href="/category/business-cards" class="text-sm font-semibold text-brand-700 hover:underline">See all →</Link>
            </div>
            <div class="grid grid-cols-2 gap-5 md:grid-cols-4">
                <ProductCard v-for="p in featured" :key="p.slug" :product="p" />
            </div>
        </section>

        <!-- samples / promo banner -->
        <section class="mx-auto max-w-7xl px-6 py-6">
            <div class="grid items-stretch overflow-hidden bg-brand-950 text-paper md:grid-cols-2">
                <div class="p-10 sm:p-14">
                    <p class="text-sm font-semibold uppercase tracking-widest text-lime-accent">Not sure yet?</p>
                    <h2 class="mt-3 font-display text-3xl font-semibold sm:text-4xl">Order a free sample pack</h2>
                    <p class="mt-3 max-w-md text-paper/70">Feel our paper stocks and finishes before you buy — quality you can hold.</p>
                    <a href="#categories" class="mt-6 inline-block bg-lime-accent px-7 py-3.5 font-semibold text-ink transition hover:brightness-95">Get free samples</a>
                </div>
                <div class="h-56 md:h-auto"><SmartImage src="/storage/products/premium-business-cards.jpg" alt="Sample pack" /></div>
            </div>
        </section>

        <!-- tools to help build your business -->
        <section class="mx-auto max-w-7xl px-6 py-12">
            <h2 class="mb-6 font-display text-2xl font-bold tracking-tight">Tools to help build your business</h2>
            <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                <div v-for="t in tools" :key="t.t" class="border border-paper-300 bg-white p-6">
                    <span class="grid h-11 w-11 place-items-center bg-brand-50 text-brand-700">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path :d="t.i" stroke-linecap="round" stroke-linejoin="round" /></svg>
                    </span>
                    <h3 class="mt-4 font-semibold text-ink">{{ t.t }}</h3>
                    <p class="mt-1 text-sm text-ink/55">{{ t.d }}</p>
                </div>
            </div>
        </section>

        <!-- social proof -->
        <section class="border-t border-paper-300 bg-paper-200">
            <div class="mx-auto max-w-7xl px-6 py-12">
                <h2 class="mb-6 text-center font-display text-2xl font-bold tracking-tight">Made by you <span class="text-brand-700">#MadeWithRunMyPrint</span></h2>
                <div class="grid grid-cols-3 gap-3 sm:grid-cols-6">
                    <div v-for="p in featured.slice(0, 6)" :key="p.slug" class="aspect-square overflow-hidden border border-paper-300 bg-white">
                        <SmartImage :src="p.image" :alt="p.name" />
                    </div>
                </div>
            </div>
        </section>
    </StoreLayout>
</template>
