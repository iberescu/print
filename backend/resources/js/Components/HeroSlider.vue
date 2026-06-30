<script setup>
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    slides: { type: Array, default: () => [] },
    threshold: { type: Number, default: 50 },
});
const hero = props.slides[0] ?? {};
</script>

<template>
    <section class="relative isolate flex min-h-[440px] items-center overflow-hidden bg-[#085aa4] sm:min-h-[560px]">
        <!-- product flat-lay (blue blueprint surface), full-bleed, anchored to the right -->
        <img :src="hero.image" alt="" aria-hidden="true"
             class="pointer-events-none absolute inset-0 h-full w-full object-cover object-right" />
        <!-- blue wash: deepens the left for headline contrast (stronger on mobile) -->
        <div class="absolute inset-0 bg-gradient-to-r from-[#04305c] via-[#0a4a85]/80 to-[#085aa4]/20 sm:via-[#0a4a85]/35 sm:to-transparent"></div>
        <!-- subtle decorative sparkles in the open blue -->
        <svg class="pointer-events-none absolute left-6 top-12 h-16 w-16 text-white/10 sm:left-14" viewBox="0 0 100 100" fill="currentColor" aria-hidden="true"><path d="M50 0l6 44 44 6-44 6-6 44-6-44-44-6 44-6z" /></svg>
        <svg class="pointer-events-none absolute bottom-16 left-1/3 hidden h-10 w-10 text-white/10 sm:block" viewBox="0 0 100 100" fill="currentColor" aria-hidden="true"><path d="M50 0l6 44 44 6-44 6-6 44-6-44-44-6 44-6z" /></svg>

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

    <!-- trust cards — float over the bottom edge of the hero -->
    <div class="relative z-10 mx-auto -mt-9 max-w-7xl px-6 sm:-mt-12 sm:px-8">
        <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
            <div class="flex items-center gap-3.5 rounded-xl border border-paper-300 bg-white px-4 py-4 shadow-xl shadow-navy/10 sm:px-5">
                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-lg bg-brand-50 text-brand-600">
                    <svg viewBox="0 0 24 24" class="h-6 w-6" fill="currentColor" aria-hidden="true"><path d="M12 2.5l2.95 5.98 6.6.96-4.78 4.66 1.13 6.57L12 17.56l-5.9 3.1 1.13-6.57L2.45 9.44l6.6-.96z" /></svg>
                </span>
                <div><p class="font-semibold leading-tight text-ink">4.8 / 5</p><p class="text-xs leading-tight text-ink/55 sm:text-[13px]">Based on 12,000+ Verified Reviews</p></div>
            </div>
            <div class="flex items-center gap-3.5 rounded-xl border border-paper-300 bg-white px-4 py-4 shadow-xl shadow-navy/10 sm:px-5">
                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-lg bg-brand-50 text-brand-600">
                    <svg viewBox="0 0 24 24" class="h-6 w-6" fill="currentColor" aria-hidden="true"><rect x="2" y="6.5" width="11" height="8.5" rx="1.5" /><path d="M13.5 9.5h3.3c.34 0 .66.17.85.46L20.6 13v2h-7.1z" /><circle cx="6.7" cy="17" r="2" /><circle cx="16.8" cy="17" r="2" /></svg>
                </span>
                <div><p class="font-semibold leading-tight text-ink">Free Shipping</p><p class="text-xs leading-tight text-ink/55 sm:text-[13px]">On Orders Over ${{ threshold }}</p></div>
            </div>
            <div class="flex items-center gap-3.5 rounded-xl border border-paper-300 bg-white px-4 py-4 shadow-xl shadow-navy/10 sm:px-5">
                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-lg bg-brand-50 text-brand-600">
                    <svg viewBox="0 0 24 24" class="h-6 w-6" fill="currentColor" aria-hidden="true"><path d="M13 2L4.5 13.2c-.4.5 0 1.3.7 1.3H10l-1 7.5 8.8-11.6c.4-.5 0-1.3-.7-1.3H12z" /></svg>
                </span>
                <div><p class="font-semibold leading-tight text-ink">Fast Turnaround</p><p class="text-xs leading-tight text-ink/55 sm:text-[13px]">2-day production available</p></div>
            </div>
            <div class="flex items-center gap-3.5 rounded-xl border border-paper-300 bg-white px-4 py-4 shadow-xl shadow-navy/10 sm:px-5">
                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-lg bg-brand-50 text-brand-600">
                    <svg viewBox="0 0 24 24" class="h-6 w-6" aria-hidden="true"><path d="M12 2l8 3v6c0 5-3.4 8.5-8 10-4.6-1.5-8-5-8-10V5z" fill="currentColor" /><path d="M8.4 12.2l2.5 2.5 4.7-5" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /></svg>
                </span>
                <div><p class="font-semibold leading-tight text-ink">100% Quality Guarantee</p><p class="text-xs leading-tight text-ink/55 sm:text-[13px]">Love It or It's Reprinted</p></div>
            </div>
        </div>
    </div>
</template>
