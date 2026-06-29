<script setup>
import { computed } from 'vue';

const props = defineProps({
    brand: { type: Object, default: () => ({}) },
    variant: { type: String, default: 'flyer' },
});

// per-format canvas + header-band proportion
const VARIANTS = {
    flyer: { w: 600, h: 800, band: 0.30 },
    poster: { w: 560, h: 840, band: 0.34 },
    postcard: { w: 800, h: 560, band: 0.42 },
    notepad: { w: 600, h: 760, band: 0.22 },
    tote: { w: 700, h: 720, band: 0 },
};
const c = computed(() => VARIANTS[props.variant] || VARIANTS.flyer);
const initials = computed(() =>
    (props.brand.companyName || 'L').replace(/[^A-Za-z ]/g, '').split(/\s+/).filter(Boolean).map((s) => s[0]).join('').slice(0, 2).toUpperCase() || 'L'
);
const nameY = computed(() => (c.value.band > 0 ? c.value.h * (c.value.band + 0.16) : c.value.h * 0.46));
// auto-fit the company name to the available width (no SVG text wrapping)
const nameFont = computed(() => {
    const avail = c.value.w * 0.84;
    const len = Math.max(6, (props.brand.companyName || 'Company Name').length);
    return Math.min(c.value.w * 0.11, avail / (len * 0.56));
});
</script>

<template>
    <svg :viewBox="`0 0 ${c.w} ${c.h}`" class="h-full w-full" preserveAspectRatio="xMidYMid meet" font-family="'Instrument Sans', system-ui, sans-serif">
        <rect :width="c.w" :height="c.h" fill="#f8f6ef" />
        <rect v-if="c.band > 0" :width="c.w" :height="c.h * c.band" fill="#0e9355" />

        <!-- logo: uploaded image, else a lime monogram chip -->
        <image v-if="brand.logo" :href="brand.logo" :x="c.w * 0.08" :y="c.h * 0.05" :width="c.w * 0.2" :height="c.w * 0.2" preserveAspectRatio="xMidYMid meet" />
        <template v-else>
            <circle :cx="c.w * 0.08 + c.w * 0.1" :cy="c.h * 0.05 + c.w * 0.1" :r="c.w * 0.1" fill="#c7f23d" />
            <text :x="c.w * 0.08 + c.w * 0.1" :y="c.h * 0.05 + c.w * 0.1 + c.w * 0.038" text-anchor="middle" font-weight="700" :font-size="c.w * 0.1" fill="#0c1f17">{{ initials }}</text>
        </template>

        <!-- company name -->
        <text :x="c.w * 0.08" :y="nameY" font-family="'Fraunces', Georgia, serif" font-weight="700" :font-size="nameFont" fill="#0c1f17">{{ brand.companyName || 'Company Name' }}</text>
        <!-- url / tagline -->
        <text :x="c.w * 0.08" :y="nameY + c.w * 0.075" :font-size="c.w * 0.05" fill="#0e9355">{{ brand.url || brand.title || 'yourcompany.com' }}</text>

        <!-- contact block -->
        <text :x="c.w * 0.08" :y="c.h * 0.88" :font-size="c.w * 0.045" fill="#0c1f17">{{ brand.email || 'hello@company.com' }}</text>
        <text :x="c.w * 0.08" :y="c.h * 0.93" :font-size="c.w * 0.045" fill="#0c1f17">{{ brand.phone || '+1 (555) 123-4567' }}</text>
        <rect :x="c.w * 0.08" :y="c.h * 0.955" :width="c.w * 0.84" height="5" fill="#0e9355" />
    </svg>
</template>
