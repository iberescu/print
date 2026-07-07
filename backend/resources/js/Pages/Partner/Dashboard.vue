<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import StoreLayout from '../../Layouts/StoreLayout.vue';

const props = defineProps({
    partner: { type: Object, required: true },
    daily: { type: Array, default: () => [] },
    payouts: { type: Array, default: () => [] },
});

const money = (v) => '$' + Number(v || 0).toFixed(2);
const showSnippet = ref(false);
const snippet = `<script async src="https://www.runmyprint.com/affiliate-widget.js"><\/script>
<div data-rmp-affiliate="${props.partner.key}" data-logo-url="VISITOR_LOGO_URL"></div>`;
const logout = () => router.post('/partner/logout');
</script>

<template>
    <Head title="Partner dashboard" />
    <StoreLayout>
        <div class="mx-auto max-w-5xl px-6 py-10 sm:px-8">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-widest text-brand-600">Partner dashboard</p>
                    <h1 class="mt-1 font-display text-3xl font-bold tracking-tight">{{ partner.company || partner.name }}</h1>
                    <p class="mt-1 text-sm text-ink/55">Status: <span class="font-semibold capitalize" :class="partner.status === 'active' ? 'text-emerald-700' : 'text-amber-700'">{{ partner.status }}</span> · {{ money(partner.cpm) }} CPM</p>
                </div>
                <button class="rounded-full border border-ink/20 bg-white px-5 py-2.5 text-sm font-semibold text-ink transition hover:border-ink/40" @click="logout">Sign out</button>
            </div>

            <div class="mt-8 grid grid-cols-2 gap-4 sm:grid-cols-5">
                <div class="rounded-2xl border border-paper-300 bg-white p-4 text-center"><p class="font-display text-2xl font-bold text-ink">{{ partner.impressions.toLocaleString() }}</p><p class="mt-0.5 text-xs text-ink/55">viewable impressions</p></div>
                <div class="rounded-2xl border border-paper-300 bg-white p-4 text-center"><p class="font-display text-2xl font-bold text-ink">{{ partner.clicks.toLocaleString() }}</p><p class="mt-0.5 text-xs text-ink/55">clicks</p></div>
                <div class="rounded-2xl border border-paper-300 bg-white p-4 text-center"><p class="font-display text-2xl font-bold text-ink">{{ money(partner.earned) }}</p><p class="mt-0.5 text-xs text-ink/55">earned</p></div>
                <div class="rounded-2xl border border-paper-300 bg-white p-4 text-center"><p class="font-display text-2xl font-bold text-ink">{{ money(partner.paid) }}</p><p class="mt-0.5 text-xs text-ink/55">paid out</p></div>
                <div class="rounded-2xl border border-brand-600 bg-brand-50 p-4 text-center"><p class="font-display text-2xl font-bold text-brand-700">{{ money(partner.owed) }}</p><p class="mt-0.5 text-xs text-brand-700/70">owed to you</p></div>
            </div>

            <div class="mt-8 grid gap-6 lg:grid-cols-[1fr_320px]">
                <div class="overflow-hidden rounded-2xl border border-paper-300 bg-white">
                    <p class="border-b border-paper-300 bg-paper-200 px-5 py-3 text-xs font-semibold uppercase tracking-wide text-ink/50">Last 30 days</p>
                    <table class="w-full text-sm">
                        <tbody class="divide-y divide-paper-200">
                            <tr v-for="d in daily" :key="d.date">
                                <td class="px-5 py-2.5 text-ink/70">{{ d.date }}</td>
                                <td class="px-5 py-2.5 text-right tabular-nums">{{ d.impressions.toLocaleString() }} views</td>
                                <td class="px-5 py-2.5 text-right tabular-nums text-ink/60">{{ d.clicks }} clicks</td>
                            </tr>
                            <tr v-if="!daily.length"><td class="px-5 py-10 text-center text-ink/50" colspan="3">No traffic yet — embed the widget to start earning.</td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="space-y-6">
                    <div class="rounded-2xl border border-paper-300 bg-white p-5">
                        <p class="text-xs font-semibold uppercase tracking-wide text-ink/50">Your embed snippet</p>
                        <button class="mt-3 w-full rounded-full border border-brand-600 px-4 py-2 text-sm font-semibold text-brand-700 transition hover:bg-brand-50" @click="showSnippet = !showSnippet">
                            {{ showSnippet ? 'Hide' : 'Show' }} snippet
                        </button>
                        <pre v-if="showSnippet" class="mt-3 overflow-x-auto rounded-lg bg-[#0d1523] p-3 text-[11px] leading-relaxed text-[#9cc6ff]">{{ snippet }}</pre>
                    </div>
                    <div class="overflow-hidden rounded-2xl border border-paper-300 bg-white">
                        <p class="border-b border-paper-300 bg-paper-200 px-5 py-3 text-xs font-semibold uppercase tracking-wide text-ink/50">Payouts</p>
                        <ul class="divide-y divide-paper-200 text-sm">
                            <li v-for="(p, i) in payouts" :key="i" class="flex justify-between px-5 py-2.5"><span class="text-ink/70">{{ p.date }}</span><span class="font-medium">{{ money(p.amount) }}</span></li>
                            <li v-if="!payouts.length" class="px-5 py-8 text-center text-ink/50">Paid monthly once you pass $50.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </StoreLayout>
</template>
