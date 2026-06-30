<script setup>
import { Link } from '@inertiajs/vue3';
import SmartImage from './SmartImage.vue';

defineProps({ product: { type: Object, required: true } });

const money = (n) => '$' + Number(n).toFixed(2);
</script>

<template>
    <Link
        :href="`/product/${product.slug}`"
        class="group relative flex flex-col overflow-hidden rounded-2xl border border-paper-300 bg-white shadow-sm transition duration-300 hover:-translate-y-1.5 hover:shadow-[0_28px_55px_-28px_rgba(43,59,85,0.55)]"
    >
        <div class="relative aspect-square overflow-hidden bg-paper-200">
            <SmartImage
                :src="product.image"
                :alt="product.name"
                class="transition duration-500 group-hover:scale-105"
            />
            <span
                v-if="product.badge"
                class="absolute left-3 top-3 rounded-full bg-brand-600 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wider text-white shadow-sm"
            >
                {{ product.badge }}
            </span>
        </div>
        <div class="flex flex-1 flex-col p-5">
            <p v-if="product.category" class="text-[11px] font-semibold uppercase tracking-widest text-brand-700/70">
                {{ product.category }}
            </p>
            <h3 class="mt-0.5 font-display text-lg font-semibold leading-snug text-ink">{{ product.name }}</h3>
            <p class="mt-1 line-clamp-2 text-sm text-ink/55">{{ product.tagline }}</p>
            <p class="mt-3 text-sm font-medium text-ink/70">
                From <span class="font-semibold text-brand-700">{{ money(product.fromPrice) }}</span>
            </p>
        </div>
    </Link>
</template>
