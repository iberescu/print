<script setup>
import { Link } from '@inertiajs/vue3';

const props = defineProps({ slides: { type: Array, default: () => [] } });
const hero = props.slides[0] ?? {};
</script>

<template>
    <section class="relative isolate flex min-h-[480px] items-end overflow-hidden bg-navy sm:min-h-[540px] sm:items-center">
        <!-- lifestyle product flat-lay, full-bleed. The image's empty left third
             is the desktop text area; phones crop to the products instead and
             read the copy off a navy scrim. -->
        <img :src="hero.image" :alt="hero.title" class="absolute inset-0 h-full w-full object-cover object-[72%_center] sm:object-left" />
        <div class="absolute inset-0 bg-gradient-to-t from-navy/95 via-navy/45 to-navy/5 sm:hidden" aria-hidden="true"></div>

        <div class="relative mx-auto w-full max-w-7xl px-6 py-10 sm:px-8 sm:py-24">
            <div class="max-w-xl text-navy">
                <h1 class="font-display text-4xl font-extrabold leading-[1.04] tracking-tight text-white drop-shadow-md sm:text-6xl">{{ hero.title }}</h1>
                <p class="mt-5 max-w-md text-base leading-relaxed text-white/85 sm:text-lg sm:text-ink/70">{{ hero.text }}</p>
                <div class="mt-8 flex flex-wrap items-center gap-3">
                    <Link :href="hero.href" class="bg-brand-blue px-8 py-3.5 font-semibold text-white shadow-lg shadow-black/25 transition hover:brightness-110">{{ hero.cta }}</Link>
                    <Link href="/category/business-cards" class="border border-white/45 px-7 py-3.5 font-semibold text-white transition hover:bg-white/10 sm:border-navy/30 sm:text-navy sm:hover:bg-navy/5">Business cards</Link>
                </div>
            </div>
        </div>
    </section>
</template>
