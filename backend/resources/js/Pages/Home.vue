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
</script>

<template>
    <Head title="Custom Printing for Business" />
    <StoreLayout>
        <HeroSlider :slides="slides" :threshold="freeShippingThreshold" />

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

        <!-- tools to grow your business -->
        <section class="mx-auto max-w-7xl px-6 py-14 sm:px-8 sm:py-16">
            <div class="mb-9">
                <p class="text-sm font-semibold uppercase tracking-widest text-brand-600">Everything you need</p>
                <h2 class="mt-2 font-display text-3xl font-bold tracking-tight sm:text-4xl">Tools to grow your business</h2>
            </div>
            <div class="grid grid-cols-2 gap-6 md:grid-cols-4">
                <!-- Design services -->
                <div class="group rounded-2xl border border-paper-300 bg-white p-7 shadow-sm transition duration-300 hover:-translate-y-1.5 hover:shadow-xl">
                    <svg viewBox="0 0 64 64" class="h-16 w-16 transition duration-300 group-hover:scale-105" aria-hidden="true">
                        <rect x="3" y="3" width="58" height="58" rx="16" fill="#ecfdf3" />
                        <path d="M32 16c-9.5 0-17 6.7-17 15 0 8.4 6 12.6 11.6 12.6 2.3 0 3.3-1.7 3.3-3.4 0-1.9 1.3-3 3.2-3H37c5.6 0 11-3.5 11-9.9C48 22.4 41 16 32 16z" fill="#2b3b55" />
                        <circle cx="23" cy="31" r="2.7" fill="#ffffff" />
                        <circle cx="29" cy="25" r="2.7" fill="#c7f23d" />
                        <circle cx="38" cy="26" r="2.7" fill="#647ba0" />
                        <circle cx="42" cy="34" r="2.7" fill="#ffffff" />
                    </svg>
                    <h3 class="mt-5 font-semibold text-ink">Design services</h3>
                    <p class="mt-1.5 text-sm text-ink/55">Let our experts design it for you.</p>
                </div>
                <!-- Logo maker -->
                <div class="group rounded-2xl border border-paper-300 bg-white p-7 shadow-sm transition duration-300 hover:-translate-y-1.5 hover:shadow-xl">
                    <svg viewBox="0 0 64 64" class="h-16 w-16 transition duration-300 group-hover:scale-105" aria-hidden="true">
                        <rect x="3" y="3" width="58" height="58" rx="16" fill="#eef2f9" />
                        <path d="M32 14l15 5.2v9.6c0 9.7-6.4 16.2-15 19.2-8.6-3-15-9.5-15-19.2v-9.6z" fill="#2b3b55" />
                        <path d="M32 23l3.2 6.6 7.3.6-5.5 4.8 1.7 7.1L32 44.4l-6.7 3.7 1.7-7.1-5.5-4.8 7.3-.6z" fill="#c7f23d" />
                    </svg>
                    <h3 class="mt-5 font-semibold text-ink">Logo maker</h3>
                    <p class="mt-1.5 text-sm text-ink/55">Create a logo in minutes.</p>
                </div>
                <!-- Free templates -->
                <div class="group rounded-2xl border border-paper-300 bg-white p-7 shadow-sm transition duration-300 hover:-translate-y-1.5 hover:shadow-xl">
                    <svg viewBox="0 0 64 64" class="h-16 w-16 transition duration-300 group-hover:scale-105" aria-hidden="true">
                        <rect x="3" y="3" width="58" height="58" rx="16" fill="#ecfdf3" />
                        <rect x="27" y="17" width="20" height="28" rx="3" fill="#647ba0" />
                        <rect x="19" y="21" width="20" height="28" rx="3" fill="#2b3b55" />
                        <rect x="23" y="27" width="12" height="2.6" rx="1.3" fill="#ffffff" />
                        <rect x="23" y="32.5" width="12" height="2.6" rx="1.3" fill="#ffffff" opacity="0.65" />
                        <rect x="23" y="38" width="8" height="2.6" rx="1.3" fill="#c7f23d" />
                    </svg>
                    <h3 class="mt-5 font-semibold text-ink">Free templates</h3>
                    <p class="mt-1.5 text-sm text-ink/55">200+ professional designs.</p>
                </div>
                <!-- Order samples -->
                <div class="group rounded-2xl border border-paper-300 bg-white p-7 shadow-sm transition duration-300 hover:-translate-y-1.5 hover:shadow-xl">
                    <svg viewBox="0 0 64 64" class="h-16 w-16 transition duration-300 group-hover:scale-105" aria-hidden="true">
                        <rect x="3" y="3" width="58" height="58" rx="16" fill="#eef2f9" />
                        <g transform="rotate(-22 31 47)"><rect x="26" y="20" width="10" height="28" rx="3" fill="#2b3b55" /></g>
                        <g transform="rotate(-1 31 47)"><rect x="26" y="20" width="10" height="28" rx="3" fill="#647ba0" /></g>
                        <g transform="rotate(20 31 47)"><rect x="26" y="20" width="10" height="28" rx="3" fill="#c7f23d" /></g>
                        <circle cx="31" cy="47" r="2.5" fill="#2b3b55" />
                    </svg>
                    <h3 class="mt-5 font-semibold text-ink">Order samples</h3>
                    <p class="mt-1.5 text-sm text-ink/55">Feel the quality before you buy.</p>
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
