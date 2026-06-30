<script setup>
import { Link } from '@inertiajs/vue3';

const props = defineProps({ slides: { type: Array, default: () => [] } });
const hero = props.slides[0] ?? {};
</script>

<template>
    <section class="relative isolate flex min-h-[420px] items-center overflow-hidden bg-navy sm:min-h-[540px]">
        <!-- lifestyle product flat-lay, full-bleed -->
        <img :src="hero.image" :alt="hero.title" class="absolute inset-0 h-full w-full object-cover object-center" />
        <!-- navy wash for headline contrast (lighter on the right at desktop) -->
        <div class="absolute inset-0 bg-gradient-to-r from-navy/95 via-navy/75 to-navy/25 sm:via-navy/60 sm:to-navy/5"></div>

        <div class="relative mx-auto w-full max-w-7xl px-6 py-16 sm:px-8 sm:py-24">
            <div class="max-w-xl text-white">
                <h1 class="font-display text-4xl font-extrabold leading-[1.04] tracking-tight drop-shadow-sm sm:text-6xl">{{ hero.title }}</h1>
                <p class="mt-5 max-w-md text-base leading-relaxed text-white/85 sm:text-lg">{{ hero.text }}</p>
                <div class="mt-8 flex flex-wrap items-center gap-3">
                    <Link :href="hero.href" class="bg-brand-blue px-8 py-3.5 font-semibold text-white shadow-lg shadow-black/25 transition hover:brightness-110">{{ hero.cta }}</Link>
                    <Link href="/category/business-cards" class="border border-white/40 px-7 py-3.5 font-semibold text-white backdrop-blur-sm transition hover:bg-white/10">Business cards</Link>
                </div>
            </div>
        </div>
    </section>
</template>
