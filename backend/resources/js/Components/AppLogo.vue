<script setup>
// `featured` = the prominent storefront-header logo (larger + slight tilt).
// Default keeps the compact logo used in the editor toolbar / mobile drawer.
defineProps({ featured: { type: Boolean, default: false } });
</script>

<template>
    <img
        src="/storage/brand/logo.svg"
        alt="runmyprint"
        :class="featured ? 'h-[94px] w-auto sm:h-[109px]' : 'h-[72px] w-auto sm:h-[84px]'"
        style="transform: rotate(-5deg)"
    />
</template>
