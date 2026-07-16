<script setup>
// Horizontal keyword-traffic bars: the market's monthly searches per keyword
// (log-scaled track) with the buyer's projected slice of the $29 campaign
// overlaid at the left edge. rows: [{keyword, volume, yours, estimated}]
const props = defineProps({
    rows: { type: Array, default: () => [] },
});

const W = 780;
const X0 = 14; const X1 = 620;
const rowH = 58; const top = 46;
const height = () => top + props.rows.length * rowH + 8;

const maxV = () => Math.max(...props.rows.map((r) => r.volume), 1000);
const bar = (v) => {
    const t = Math.log10(Math.max(80, v)) / Math.log10(maxV() * 1.1);
    return Math.max(26, Math.min(1, t) * (X1 - X0));
};
const slice = (r) => Math.max(6, bar(r.volume) * Math.min(1, r.yours / Math.max(r.volume, 1))); // true share, floored to stay visible
const fmt = (n) => (n >= 1000 ? `${(n / 1000).toFixed(n >= 10000 ? 0 : 1)}k` : `${Math.round(n)}`);
</script>

<template>
    <svg :viewBox="`0 0 ${W} ${height()}`" class="h-auto w-full select-none" role="img" aria-label="Keyword traffic chart" data-keyword-chart>
        <!-- legend -->
        <rect :x="X0" y="12" width="12" height="12" rx="3" fill="#16233b" opacity="0.8" />
        <text :x="X0 + 18" y="22" font-size="12" fill="#5c6372">est. US searches / month</text>
        <rect :x="X0 + 190" y="12" width="12" height="12" rx="3" fill="#2563c9" />
        <text :x="X0 + 208" y="22" font-size="12" fill="#5c6372">your visitors on the $29 campaign</text>

        <g v-for="(r, i) in rows" :key="r.keyword" :transform="`translate(0 ${top + i * rowH})`">
            <text :x="X0" y="14" font-size="13.5" font-weight="600" fill="#232a38">{{ r.keyword }}</text>
            <!-- market track -->
            <rect :x="X0" y="22" :width="X1 - X0" height="18" rx="6" fill="#eceef2" />
            <rect :x="X0" y="22" :width="bar(r.volume)" height="18" rx="6" fill="#16233b" opacity="0.82" />
            <!-- your slice -->
            <rect :x="X0" y="22" :width="slice(r)" height="18" rx="6" fill="#2563c9" />
            <!-- numbers: volume inside wide bars (white), after narrow ones; share in its own column -->
            <text v-if="bar(r.volume) >= 130" :x="X0 + bar(r.volume) - 8" y="36" font-size="12.5" fill="#ffffff" text-anchor="end">{{ fmt(r.volume) }}{{ r.estimated ? ' est.' : '' }}/mo</text>
            <text v-else :x="X0 + bar(r.volume) + 10" y="36" font-size="12.5" fill="#5c6372">{{ fmt(r.volume) }}{{ r.estimated ? ' est.' : '' }}/mo</text>
            <text :x="X1 + 40" y="36" font-size="12.5" font-weight="700" fill="#2563c9" text-anchor="start">+{{ fmt(r.yours) }} yours</text>
        </g>
    </svg>
</template>
