<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';

defineProps({
    products: { type: Array, default: () => [] },
    eyebrow: { type: String, default: 'Your logo on' },
    title: { type: String, default: 'Your brand, already on our products' },
    sub: { type: String, default: 'Mockups made from your logo — add any to your order.' },
});

const adding = ref(null);
const addToCart = (slug) => {
    if (adding.value) return;
    adding.value = slug;
    // same path the home section always used — lands the line in the cart
    router.post(`/upsell/add/${slug}`, {}, {
        preserveScroll: true,
        onFinish: () => (adding.value = null),
    });
};
</script>

<template>
    <section v-if="products.length" class="mx-auto max-w-7xl px-6 py-10 sm:px-8 sm:py-14">
        <div class="mb-6">
            <p class="text-sm font-semibold uppercase tracking-widest text-brand-600">{{ eyebrow }}</p>
            <h2 class="mt-2 font-display text-2xl font-bold tracking-tight sm:text-3xl">{{ title }}</h2>
            <p class="mt-1.5 text-ink/60">{{ sub }}</p>
        </div>
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 sm:gap-5 md:grid-cols-4">
            <div v-for="(p, i) in products" :key="p.slug || i"
                 class="group flex flex-col overflow-hidden rounded-2xl border border-paper-300 bg-white shadow-sm transition duration-300"
                 :class="p.slug ? 'hover:-translate-y-1.5 hover:shadow-[0_28px_55px_-28px_rgba(43,59,85,0.55)]' : ''">
                <component :is="p.slug ? Link : 'div'" :href="p.slug ? `/product/${p.slug}` : undefined" class="block aspect-square overflow-hidden bg-white">
                    <img v-if="p.img" :src="p.img" :alt="p.label || p.name" loading="lazy" class="h-full w-full object-cover transition duration-500" :class="p.slug ? 'group-hover:scale-105' : ''" />
                </component>
                <div class="flex flex-1 flex-col p-3.5">
                    <h3 class="font-display text-sm font-semibold leading-snug text-ink sm:text-base">{{ p.name || p.label }}</h3>
                    <p v-if="p.fromPrice != null" class="mt-0.5 text-xs text-ink/60 sm:text-sm">From <span class="font-semibold text-brand-700">${{ Number(p.fromPrice).toFixed(2) }}</span></p>
                    <button v-if="p.slug" type="button" :disabled="adding === p.slug"
                            class="mt-3 w-full rounded-full bg-brand-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-700 disabled:opacity-70"
                            @click="addToCart(p.slug)">
                        {{ adding === p.slug ? 'Adding…' : '+ Add to cart' }}
                    </button>
                </div>
            </div>
        </div>
    </section>
</template>
