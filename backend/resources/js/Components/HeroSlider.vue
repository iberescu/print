<script setup>
import { Link } from '@inertiajs/vue3';

const props = defineProps({ slides: { type: Array, default: () => [] } });
const hero = props.slides[0] ?? {};
</script>

<template>
    <section class="relative isolate flex min-h-[420px] items-center overflow-hidden bg-navy sm:min-h-[540px]">
        <!-- lifestyle product flat-lay, full-bleed -->
        <img :src="hero.image" :alt="hero.title" class="absolute inset-0 h-full w-full object-cover object-left" />

        <div class="relative mx-auto w-full max-w-7xl px-6 py-16 sm:px-8 sm:py-24">
            <div class="max-w-xl text-navy">
                <h1 class="font-display text-4xl font-extrabold leading-[1.04] tracking-tight text-white drop-shadow-md sm:text-6xl">{{ hero.title }}</h1>
                <p class="mt-5 max-w-md text-base leading-relaxed text-ink/70 sm:text-lg">{{ hero.text }}</p>
                <div class="mt-8 flex flex-wrap items-center gap-3">
                    <Link :href="hero.href" class="bg-brand-blue px-8 py-3.5 font-semibold text-white shadow-lg shadow-black/25 transition hover:brightness-110">{{ hero.cta }}</Link>
                    <Link href="/category/business-cards" class="border border-navy/30 px-7 py-3.5 font-semibold text-navy transition hover:bg-navy/5">Business cards</Link>
                </div>
            </div>
        </div>
    </section>
</template>
