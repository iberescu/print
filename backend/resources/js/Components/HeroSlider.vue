<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({ slides: { type: Array, default: () => [] } });
const active = ref(0);
let timer = null;

const go = (n) => (active.value = (n + props.slides.length) % props.slides.length);
const next = () => go(active.value + 1);
const prev = () => go(active.value - 1);

onMounted(() => { timer = setInterval(next, 5500); });
onBeforeUnmount(() => timer && clearInterval(timer));
</script>

<template>
    <section class="mx-auto max-w-7xl px-6 pt-8 sm:px-8">
        <div class="relative h-[420px] overflow-hidden rounded-[28px] bg-paper-200 shadow-2xl shadow-navy/15 sm:h-[560px]"
             @mouseenter="timer && clearInterval(timer)" @mouseleave="timer = setInterval(next, 5500)">
            <div v-for="(s, idx) in slides" :key="idx"
                 class="absolute inset-0 transition-opacity duration-700"
                 :class="idx === active ? 'opacity-100' : 'pointer-events-none opacity-0'">
                <img :src="s.image" :alt="s.title" class="h-full w-full object-cover" />
                <div class="absolute inset-0 bg-gradient-to-r from-navy/95 via-navy/65 to-navy/10"></div>
                <div class="absolute inset-0 flex items-center">
                    <div class="max-w-xl px-8 text-white sm:px-16">
                        <p class="inline-block rounded-full bg-white/10 px-4 py-1.5 text-xs font-semibold uppercase tracking-widest text-lime-accent ring-1 ring-white/15 backdrop-blur-sm sm:text-sm">{{ s.eyebrow }}</p>
                        <h2 class="mt-5 font-display text-4xl font-extrabold leading-[1.04] tracking-tight sm:text-6xl">{{ s.title }}</h2>
                        <p class="mt-4 max-w-md text-base text-white/75 sm:mt-5 sm:text-lg">{{ s.text }}</p>
                        <div class="mt-7 flex flex-wrap items-center gap-3 sm:mt-9">
                            <Link :href="s.href" class="rounded-full bg-brand-500 px-8 py-3.5 font-semibold text-white shadow-lg shadow-brand-500/30 transition hover:bg-brand-400 hover:shadow-brand-400/40">{{ s.cta }}</Link>
                            <Link href="/category/business-cards" class="rounded-full border border-white/25 px-7 py-3.5 font-semibold text-white backdrop-blur-sm transition hover:bg-white/10">Business cards</Link>
                        </div>
                    </div>
                </div>
            </div>

            <button class="absolute left-4 top-1/2 hidden h-11 w-11 -translate-y-1/2 place-items-center rounded-full bg-white/90 text-2xl text-navy shadow-lg transition hover:bg-white sm:grid" @click="prev" aria-label="Previous">‹</button>
            <button class="absolute right-4 top-1/2 hidden h-11 w-11 -translate-y-1/2 place-items-center rounded-full bg-white/90 text-2xl text-navy shadow-lg transition hover:bg-white sm:grid" @click="next" aria-label="Next">›</button>

            <div class="absolute bottom-6 left-8 flex gap-2 sm:left-16">
                <button v-for="(s, idx) in slides" :key="idx" class="h-2 rounded-full transition-all" :class="idx === active ? 'w-8 bg-lime-accent' : 'w-2.5 bg-white/50 hover:bg-white/80'" @click="go(idx)" :aria-label="`Slide ${idx + 1}`"></button>
            </div>
        </div>
    </section>
</template>
