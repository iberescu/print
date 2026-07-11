<script setup>
import { computed } from 'vue';

const props = defineProps({
    subtotal: { type: Number, default: 0 },
    threshold: { type: Number, default: 50 },
    remaining: { type: Number, default: 0 },
    qualifies: { type: Boolean, default: false },
});

const money = (n) => '$' + Number(n || 0).toFixed(2);
const pct = computed(() => Math.max(3, Math.min(100, Math.round(((props.subtotal || 0) / (props.threshold || 50)) * 100))));
</script>

<template>
    <div class="rounded-2xl border border-paper-300 bg-white p-4 shadow-sm sm:p-5">
        <div class="flex items-center gap-2.5">
            <svg class="h-5 w-5 shrink-0 text-brand-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M3 7h11v9H3zM14 10h4l3 3v3h-7" stroke-linecap="round" stroke-linejoin="round" /><circle cx="7" cy="18" r="1.6" /><circle cx="17" cy="18" r="1.6" /></svg>
            <p v-if="qualifies" class="text-sm font-semibold text-brand-700 sm:text-base">🎉 Your order qualifies for free Standard shipping!</p>
            <p v-else class="text-sm text-ink/75 sm:text-base">Your order is <strong class="text-ink">{{ money(remaining) }}</strong> away from free Standard shipping.</p>
        </div>
        <div class="mt-3 h-2.5 overflow-hidden rounded-full bg-paper-300">
            <div class="h-full rounded-full bg-brand-600 transition-all duration-500" :style="{ width: pct + '%' }"></div>
        </div>
        <p class="mt-1.5 text-xs text-ink/45">{{ money(threshold) }} minimum for free shipping</p>
    </div>
</template>
