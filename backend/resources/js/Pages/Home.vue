<script setup>
import { Head } from '@inertiajs/vue3';

defineProps({
    freeShippingThreshold: { type: Number, default: 50 },
});
</script>

<template>
    <Head title="Custom Printing, Made Simple" />

    <div class="min-h-screen bg-white text-slate-900">
        <header class="border-b border-slate-100">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-4">
                <div class="flex items-center gap-2">
                    <div class="grid h-9 w-9 place-items-center rounded-lg bg-green-600 font-bold text-white">R</div>
                    <span class="text-xl font-semibold tracking-tight">RunMyPrint</span>
                </div>
                <nav class="hidden gap-8 text-sm font-medium text-slate-600 md:flex">
                    <a href="#" class="hover:text-slate-900">Business Cards</a>
                    <a href="#" class="hover:text-slate-900">Flyers</a>
                    <a href="#" class="hover:text-slate-900">Banners</a>
                    <a href="#" class="hover:text-slate-900">Stationery</a>
                </nav>
                <button class="rounded-full bg-green-600 px-5 py-2 text-sm font-semibold text-white transition hover:bg-green-700">
                    Start designing
                </button>
            </div>
        </header>

        <main>
            <section class="mx-auto max-w-7xl px-6 py-20 text-center">
                <p class="mb-3 text-sm font-semibold uppercase tracking-wider text-green-600">Web-to-print, done right</p>
                <h1 class="mx-auto max-w-3xl text-5xl font-bold tracking-tight sm:text-6xl">
                    Custom printing for your business, made simple.
                </h1>
                <p class="mx-auto mt-6 max-w-2xl text-lg text-slate-600">
                    Design online or upload your artwork. Business cards, flyers, banners and more —
                    printed beautifully and delivered fast.
                </p>
                <div class="mt-10 flex items-center justify-center gap-4">
                    <button class="rounded-full bg-green-600 px-7 py-3 font-semibold text-white shadow-sm transition hover:bg-green-700">
                        Browse products
                    </button>
                    <button class="rounded-full border border-slate-300 px-7 py-3 font-semibold text-slate-700 transition hover:border-slate-400">
                        How it works
                    </button>
                </div>
                <p class="mt-6 text-sm text-slate-500">🚚 Free shipping on orders over ${{ freeShippingThreshold }}</p>
            </section>

            <section class="border-t border-slate-100 bg-slate-50 py-10">
                <div class="mx-auto max-w-7xl px-6 text-center text-sm font-medium text-slate-500">
                    ✅ Inertia + Vue 3 + Tailwind v4 pipeline is live. Placeholder page — the real storefront is next.
                </div>
            </section>
        </main>
    </div>
</template>
