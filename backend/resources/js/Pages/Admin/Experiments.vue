<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import AdminLayout from '../../Layouts/AdminLayout.vue';

const props = defineProps({
    rows: { type: Array, default: () => [] },
    since: { type: String, default: null },
    test: { type: Object, default: () => ({}) },
});

const money = (v) => (v == null ? '—' : `$${Number(v).toFixed(2)}`);
const pct = (v) => (v == null ? '—' : `${v}%`);
const segLabel = { all: 'All traffic', url: 'With website URL', no_url: 'No website URL', unknown: 'No brand capture' };

// one table per segment, variants side by side
const segments = ['all', 'url', 'no_url', 'unknown'];
const bySegment = computed(() => segments.map((s) => ({
    key: s,
    label: segLabel[s],
    variants: props.rows.filter((r) => r.segment === s),
})).filter((s) => s.variants.some((v) => v.assigned || v.orders)));

const winner = computed(() => {
    const all = props.rows.filter((r) => r.segment === 'all' && r.convRate != null);
    if (all.length < 2 || all.every((r) => !r.converted)) return null;
    return [...all].sort((a, b) => (b.convRate ?? 0) - (a.convRate ?? 0))[0];
});
</script>

<template>
    <Head title="Experiments" />
    <AdminLayout title="Experiments">
        <div class="mb-6 rounded-2xl border border-paper-300 bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-widest text-ink/45">Ads-step offer · 50/50 split{{ since ? ` · since ${since}` : '' }}</p>
            <div class="mt-2 grid gap-2 sm:grid-cols-2">
                <p class="text-sm"><span class="mr-1.5 rounded-full bg-brand-50 px-2 py-0.5 text-xs font-bold text-brand-700">A · paid29</span>{{ test.paid29 }}</p>
                <p class="text-sm"><span class="mr-1.5 rounded-full bg-lime-accent/30 px-2 py-0.5 text-xs font-bold text-navy">B · free500</span>{{ test.free500 }}</p>
            </div>
            <p class="mt-3 text-xs text-ink/50">
                Assigned = buyers who reached the ads step. Converted = paid orders that took the offer
                (A: the $29 line is in the order · B: order ≥ $100, credit granted). Forced previews (?ab_ads=…) are excluded.
            </p>
            <p v-if="winner" class="mt-2 text-sm font-semibold text-brand-700">Leading on conversion: {{ winner.variant === 'paid29' ? 'A — paid29' : 'B — free500' }} ({{ pct(winner.convRate) }})</p>
        </div>

        <p v-if="!bySegment.length" class="rounded-2xl border border-paper-300 bg-white px-5 py-12 text-center text-ink/50 shadow-sm">No experiment traffic yet — data appears as buyers reach the ads step.</p>

        <div v-for="seg in bySegment" :key="seg.key" class="mb-6 overflow-hidden rounded-2xl border border-paper-300 bg-white shadow-sm">
            <p class="border-b border-paper-300 bg-paper-200/70 px-5 py-2.5 text-xs font-semibold uppercase tracking-widest text-ink/55">{{ seg.label }}</p>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-paper-300 text-left text-[11px] font-semibold uppercase tracking-wider text-ink/45">
                            <th class="px-5 py-2.5">Variant</th>
                            <th class="px-3 py-2.5 text-right">Assigned</th>
                            <th class="px-3 py-2.5 text-right">Offer clicks</th>
                            <th class="px-3 py-2.5 text-right">Paid orders</th>
                            <th class="px-3 py-2.5 text-right">Order rate</th>
                            <th class="px-3 py-2.5 text-right">Converted</th>
                            <th class="px-3 py-2.5 text-right">Conv rate</th>
                            <th class="px-3 py-2.5 text-right">AOV</th>
                            <th class="px-5 py-2.5 text-right">Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="r in seg.variants" :key="r.variant" class="border-b border-paper-200 last:border-0">
                            <td class="px-5 py-3 font-semibold" :class="r.variant === 'paid29' ? 'text-brand-700' : 'text-navy'">
                                {{ r.variant === 'paid29' ? 'A · $29 for $250 ads' : 'B · free $500 credit ($100+)' }}
                            </td>
                            <td class="px-3 py-3 text-right text-ink/80">{{ r.assigned }}</td>
                            <td class="px-3 py-3 text-right text-ink/60">{{ r.variant === 'paid29' ? r.engaged : '—' }}</td>
                            <td class="px-3 py-3 text-right text-ink/80">{{ r.orders }}</td>
                            <td class="px-3 py-3 text-right text-ink/60">{{ pct(r.orderRate) }}</td>
                            <td class="px-3 py-3 text-right font-semibold text-ink">{{ r.converted }}</td>
                            <td class="px-3 py-3 text-right font-semibold" :class="r.convRate ? 'text-brand-700' : 'text-ink/40'">{{ pct(r.convRate) }}</td>
                            <td class="px-3 py-3 text-right text-ink/80">{{ money(r.aov) }}</td>
                            <td class="px-5 py-3 text-right font-semibold text-ink">{{ money(r.revenue) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AdminLayout>
</template>
