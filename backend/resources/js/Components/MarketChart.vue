<script setup>
import { computed } from 'vue';

// X/Y market scatter: competitors (SpyFu) plotted by paid keywords (x) vs
// estimated monthly clicks (y, log scale); the buyer's projected position on
// the $29 starter campaign is the highlighted dot. Fields missing from the
// API rows fall back to a deterministic domain-seeded estimate so the chart
// stays stable across renders (marked as estimates in the table next to it).
const props = defineProps({
    competitors: { type: Array, default: () => [] },
    you: { type: Object, default: () => ({ label: 'You', visitors: 1000, keywords: 120 }) },
});

function hash(s) {
    let h = 2166136261;
    for (const c of String(s)) { h ^= c.charCodeAt(0); h = (h * 16777619) >>> 0; }
    return h;
}
const est = (seed, min, max) => min + (hash(seed) % (max - min));

const rows = computed(() => props.competitors.slice(0, 6).map((c) => {
    const clicks = Math.max(120, Math.round((c.seoClicks || 0) + (c.ppcClicks || 0))) || est(c.domain + 'c', 900, 16000);
    const kw = Math.round(c.keywords || 0) || est(c.domain + 'k', 60, 640);
    return { domain: c.domain, clicks, kw };
}));

// scales: x = sqrt(keywords) → [90, 700]; y = log10(clicks) → [330, 46] (inverted)
const X0 = 90; const X1 = 700; const Y0 = 330; const Y1 = 46;
const kwMax = computed(() => Math.max(700, ...rows.value.map((r) => r.kw), props.you.keywords) * 1.15);
const x = (kw) => X0 + (Math.sqrt(kw) / Math.sqrt(kwMax.value)) * (X1 - X0);
const y = (clicks) => {
    const lo = 2, hi = Math.max(4.2, Math.log10(Math.max(...rows.value.map((r) => r.clicks), 2000)) + 0.15);
    const t = (Math.log10(Math.max(100, clicks)) - lo) / (hi - lo);
    return Y0 - Math.min(1, Math.max(0, t)) * (Y0 - Y1);
};
const yTicks = computed(() => [100, 1000, 10000, 100000].filter((v) => y(v) > Y1 - 4 && y(v) < Y0 + 4));
const xTicks = computed(() => [100, 300, 600].filter((v) => v < kwMax.value));
const fmt = (n) => (n >= 1000 ? `${(n / 1000).toFixed(n >= 10000 ? 0 : 1)}k` : `${n}`);
</script>

<template>
    <svg viewBox="0 0 780 400" class="h-auto w-full select-none" role="img" aria-label="Market position chart" data-market-chart>
        <!-- frame + grid -->
        <line :x1="X0 - 14" :y1="Y0 + 8" :x2="X1 + 30" :y2="Y0 + 8" stroke="#d8dbe2" stroke-width="1.5" />
        <line :x1="X0 - 14" :y1="Y0 + 8" :x2="X0 - 14" :y2="Y1 - 14" stroke="#d8dbe2" stroke-width="1.5" />
        <g v-for="v in yTicks" :key="'y' + v">
            <line :x1="X0 - 14" :y1="y(v)" :x2="X1 + 30" :y2="y(v)" stroke="#eceef2" stroke-dasharray="3 5" />
            <text :x="X0 - 20" :y="y(v) + 4" text-anchor="end" font-size="12" fill="#8a90a0">{{ fmt(v) }}</text>
        </g>
        <g v-for="v in xTicks" :key="'x' + v">
            <line :x1="x(v)" :y1="Y0 + 8" :x2="x(v)" :y2="Y1 - 6" stroke="#f1f2f5" stroke-dasharray="3 5" />
            <text :x="x(v)" :y="Y0 + 26" text-anchor="middle" font-size="12" fill="#8a90a0">{{ v }}</text>
        </g>
        <text :x="(X0 + X1) / 2" :y="Y0 + 48" text-anchor="middle" font-size="13" fill="#5c6372" font-weight="600">keywords targeted →</text>
        <text :x="X0 - 58" :y="(Y0 + Y1) / 2" text-anchor="middle" font-size="13" fill="#5c6372" font-weight="600" :transform="`rotate(-90 ${X0 - 58} ${(Y0 + Y1) / 2})`">monthly visitors ↑</text>

        <!-- competitors -->
        <g v-for="r in rows" :key="r.domain">
            <circle :cx="x(r.kw)" :cy="y(r.clicks)" r="11" fill="#16233b" opacity="0.14" />
            <circle :cx="x(r.kw)" :cy="y(r.clicks)" r="6.5" fill="#16233b" opacity="0.75" />
            <text :x="x(r.kw)" :y="y(r.clicks) - 14" text-anchor="middle" font-size="12" fill="#3c4354" font-weight="600" stroke="#ffffff" stroke-width="3" paint-order="stroke" stroke-linejoin="round">{{ r.domain }}</text>
        </g>

        <!-- you, on the $29 starter campaign -->
        <g>
            <circle :cx="x(you.keywords)" :cy="y(you.visitors)" r="18" fill="#2563c9" opacity="0.15">
                <animate attributeName="r" values="14;22;14" dur="2.4s" repeatCount="indefinite" />
            </circle>
            <circle :cx="x(you.keywords)" :cy="y(you.visitors)" r="8.5" fill="#2563c9" stroke="#ffffff" stroke-width="2.5" />
            <text :x="x(you.keywords) + 18" :y="y(you.visitors) + 26" font-size="13" fill="#2563c9" font-weight="700" stroke="#ffffff" stroke-width="3.5" paint-order="stroke" stroke-linejoin="round">{{ you.label }} — ≈{{ you.visitors.toLocaleString() }} visitors/mo</text>
        </g>
    </svg>
</template>
