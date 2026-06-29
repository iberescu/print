<script setup>
import { Head, Link } from '@inertiajs/vue3';
import StoreLayout from '../Layouts/StoreLayout.vue';
import ProductCard from '../Components/ProductCard.vue';
import SmartImage from '../Components/SmartImage.vue';

const props = defineProps({
    categories: { type: Array, default: () => [] },
    featured: { type: Array, default: () => [] },
    freeShippingThreshold: { type: Number, default: 50 },
});

const steps = [
    { n: '01', t: 'Pick a product', d: 'Business cards, flyers, banners, stickers and more — all customisable.' },
    { n: '02', t: 'Design or upload', d: 'Use our online designer with ready-made templates, or upload your own artwork.' },
    { n: '03', t: 'We print & ship', d: `Premium printing, delivered fast. Free shipping over $${props.freeShippingThreshold}.` },
];
</script>

<template>
    <Head title="Custom Printing for Business" />
    <StoreLayout>
        <!-- HERO -->
        <section class="relative overflow-hidden">
            <div class="mx-auto grid max-w-7xl items-center gap-10 px-6 py-16 lg:grid-cols-2 lg:py-24">
                <div class="relative z-10">
                    <p class="inline-flex items-center gap-2 rounded-full border border-brand-200 bg-brand-50 px-3 py-1 text-xs font-semibold uppercase tracking-widest text-brand-700">
                        <span class="h-1.5 w-1.5 rounded-full bg-lime-accent"></span> Web-to-print, beautifully done
                    </p>
                    <h1 class="mt-5 font-display text-5xl font-semibold leading-[1.04] tracking-tight text-balance sm:text-6xl">
                        Printing that makes your business look its best.
                    </h1>
                    <p class="mt-5 max-w-md text-lg leading-relaxed text-ink/60">
                        From business cards to banners — design online with hundreds of templates, or upload your own. Premium stock, fast turnaround.
                    </p>
                    <div class="mt-8 flex flex-wrap items-center gap-3">
                        <a href="#catalog" class="rounded-full bg-brand-600 px-7 py-3.5 font-semibold text-white shadow-lg shadow-brand-600/20 transition hover:bg-brand-700">Browse products</a>
                        <a href="#how" class="rounded-full border border-ink/15 px-7 py-3.5 font-semibold text-ink transition hover:border-ink/30">How it works</a>
                    </div>
                    <div class="mt-8 flex flex-wrap items-center gap-x-5 gap-y-2 text-sm text-ink/55">
                        <span class="flex items-center gap-1.5"><span class="tracking-tight text-lime-accent">★★★★★</span> 4.8 / 5</span>
                        <span class="hidden sm:inline">·</span>
                        <span>Free shipping over ${{ freeShippingThreshold }}</span>
                        <span class="hidden sm:inline">·</span>
                        <span>2-day delivery options</span>
                    </div>
                </div>

                <div class="relative">
                    <div class="crop-corners aspect-[4/3] overflow-hidden rounded-3xl border border-paper-300 bg-paper-200 text-ink shadow-2xl shadow-ink/10">
                        <SmartImage src="/storage/heroes/home.jpg" alt="RunMyPrint custom printing" />
                    </div>
                    <div class="absolute -bottom-5 -left-5 hidden rounded-2xl border border-paper-300 bg-white p-4 shadow-xl sm:block">
                        <p class="text-xs font-medium uppercase tracking-wide text-ink/50">Business cards from</p>
                        <p class="font-display text-2xl font-semibold text-brand-700">$10.00</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- CATEGORIES -->
        <section id="catalog" class="mx-auto max-w-7xl px-6 py-12">
            <div class="flex items-end justify-between">
                <h2 class="font-display text-3xl font-semibold tracking-tight">Shop by category</h2>
                <span class="text-sm text-ink/50">{{ categories.length }} categories</span>
            </div>
            <div class="mt-8 grid grid-cols-2 gap-5 md:grid-cols-3 lg:grid-cols-6">
                <Link
                    v-for="c in categories" :key="c.slug" :href="`/category/${c.slug}`"
                    class="group flex flex-col overflow-hidden rounded-2xl border border-paper-300 bg-white transition hover:-translate-y-1 hover:shadow-lg"
                >
                    <div class="aspect-square overflow-hidden bg-paper-200">
                        <SmartImage :src="c.image" :alt="c.name" class="transition duration-500 group-hover:scale-105" />
                    </div>
                    <div class="p-3 text-center">
                        <p class="font-display text-sm font-semibold leading-tight text-ink">{{ c.name }}</p>
                    </div>
                </Link>
            </div>
        </section>

        <!-- FEATURED -->
        <section class="mx-auto max-w-7xl px-6 py-12">
            <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-brand-700/70">Popular right now</p>
                <h2 class="mt-1 font-display text-3xl font-semibold tracking-tight">Bestselling products</h2>
            </div>
            <div class="mt-8 grid grid-cols-2 gap-5 md:grid-cols-3 lg:grid-cols-4">
                <ProductCard v-for="p in featured" :key="p.slug" :product="p" />
            </div>
        </section>

        <!-- HOW IT WORKS -->
        <section id="how" class="border-y border-paper-300 bg-white/60">
            <div class="mx-auto max-w-7xl px-6 py-16">
                <h2 class="font-display text-3xl font-semibold tracking-tight">How it works</h2>
                <div class="mt-10 grid gap-8 md:grid-cols-3">
                    <div v-for="s in steps" :key="s.n">
                        <span class="font-display text-5xl font-semibold text-brand-600/15">{{ s.n }}</span>
                        <h3 class="mt-2 font-display text-xl font-semibold text-ink">{{ s.t }}</h3>
                        <p class="mt-2 text-ink/60">{{ s.d }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA -->
        <section class="mx-auto max-w-7xl px-6 py-16">
            <div class="crop-corners relative overflow-hidden rounded-3xl bg-brand-950 px-8 py-14 text-center text-paper">
                <h2 class="font-display text-3xl font-semibold sm:text-4xl">Ready to make something great?</h2>
                <p class="mx-auto mt-3 max-w-lg text-paper/60">Start your design in minutes. Free shipping on every order over ${{ freeShippingThreshold }}.</p>
                <a href="#catalog" class="mt-7 inline-block rounded-full bg-lime-accent px-8 py-3.5 font-semibold text-ink transition hover:brightness-95">Start designing</a>
            </div>
        </section>
    </StoreLayout>
</template>
