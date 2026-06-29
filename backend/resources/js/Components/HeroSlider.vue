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
    <section class="mx-auto max-w-7xl px-6 pt-6">
        <div class="relative h-[300px] overflow-hidden rounded-3xl bg-paper-200 sm:h-[440px]"
             @mouseenter="timer && clearInterval(timer)" @mouseleave="timer = setInterval(next, 5500)">
            <div v-for="(s, idx) in slides" :key="idx"
                 class="absolute inset-0 transition-opacity duration-700"
                 :class="idx === active ? 'opacity-100' : 'pointer-events-none opacity-0'">
                <img :src="s.image" :alt="s.title" class="h-full w-full object-cover" />
                <div class="absolute inset-0 bg-gradient-to-r from-ink/90 via-ink/55 to-transparent"></div>
                <div class="absolute inset-0 flex items-center">
                    <div class="max-w-xl px-8 text-paper sm:px-12">
                        <p class="text-xs font-semibold uppercase tracking-widest text-lime-accent sm:text-sm">{{ s.eyebrow }}</p>
                        <h2 class="mt-3 font-display text-3xl font-semibold leading-[1.05] sm:text-5xl">{{ s.title }}</h2>
                        <p class="mt-3 max-w-md text-paper/80 sm:mt-4 sm:text-lg">{{ s.text }}</p>
                        <Link :href="s.href" class="mt-5 inline-block rounded-full bg-lime-accent px-7 py-3 font-semibold text-ink transition hover:brightness-95 sm:mt-7 sm:py-3.5">{{ s.cta }}</Link>
                    </div>
                </div>
            </div>

            <button class="absolute left-4 top-1/2 hidden h-10 w-10 -translate-y-1/2 place-items-center rounded-full bg-white/85 text-xl text-ink shadow transition hover:bg-white sm:grid" @click="prev" aria-label="Previous">‹</button>
            <button class="absolute right-4 top-1/2 hidden h-10 w-10 -translate-y-1/2 place-items-center rounded-full bg-white/85 text-xl text-ink shadow transition hover:bg-white sm:grid" @click="next" aria-label="Next">›</button>

            <div class="absolute bottom-5 left-8 flex gap-2 sm:left-12">
                <button v-for="(s, idx) in slides" :key="idx" class="h-2 rounded-full transition-all" :class="idx === active ? 'w-7 bg-lime-accent' : 'w-2 bg-white/60 hover:bg-white/80'" @click="go(idx)" :aria-label="`Slide ${idx + 1}`"></button>
            </div>
        </div>
    </section>
</template>
