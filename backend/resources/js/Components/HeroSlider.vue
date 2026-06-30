<script setup>
import { Link } from '@inertiajs/vue3';

const props = defineProps({ slides: { type: Array, default: () => [] } });
const hero = props.slides[0] ?? {};
</script>

<template>
    <section class="mx-auto max-w-7xl px-6 pt-8 sm:px-8">
        <div class="relative h-[420px] overflow-hidden rounded-[28px] bg-paper-200 shadow-2xl shadow-navy/15 sm:h-[560px]">
            <img :src="hero.image" :alt="hero.title" class="h-full w-full object-cover" />
            <div class="absolute inset-0 bg-gradient-to-r from-navy/95 via-navy/65 to-navy/10"></div>
            <div class="absolute inset-0 flex items-center">
                <div class="max-w-xl px-8 text-white sm:px-16">
                    <h1 class="font-display text-4xl font-extrabold leading-[1.04] tracking-tight sm:text-6xl">{{ hero.title }}</h1>
                    <p class="mt-4 max-w-md text-base text-white/75 sm:mt-5 sm:text-lg">{{ hero.text }}</p>
                    <div class="mt-7 flex flex-wrap items-center gap-3 sm:mt-9">
                        <Link :href="hero.href" class="bg-brand-blue px-8 py-3.5 font-semibold text-white shadow-lg shadow-brand-blue/30 transition hover:brightness-110">{{ hero.cta }}</Link>
                        <Link href="/category/business-cards" class="border border-white/30 px-7 py-3.5 font-semibold text-white backdrop-blur-sm transition hover:bg-white/10">Business cards</Link>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
