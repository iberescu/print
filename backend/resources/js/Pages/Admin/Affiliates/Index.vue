<script setup>
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';

defineProps({ affiliates: { type: Array, default: () => [] } });

const creating = ref(false);
const form = useForm({ name: '', company: '', email: '', website: '' });
const create = () => form.post('/admin/affiliates', { onSuccess: () => { creating.value = false; form.reset(); } });

const setStatus = (a, status) => router.patch(`/admin/affiliates/${a.id}`, { status }, { preserveScroll: true });
const setCpm = (a) => {
    const cpm = prompt(`CPM for ${a.name} (dollars per 1000 impressions, program range 15–20):`, a.cpm);
    if (cpm !== null && cpm !== '') router.patch(`/admin/affiliates/${a.id}`, { cpm: Number(cpm) }, { preserveScroll: true });
};
const recordPayout = (a) => {
    const amount = prompt(`Record payout for ${a.name} — owed $${a.owed.toFixed(2)}. Amount ($):`, a.owed.toFixed(2));
    if (amount !== null && amount !== '') router.post(`/admin/affiliates/${a.id}/payout`, { amount: Number(amount) }, { preserveScroll: true });
};

const revealed = ref(null);
const snippet = (a) => `<script async src="https://www.runmyprint.com/affiliate-widget.js"><\/script>\n<div data-rmp-affiliate="${a.key}" data-logo-url="VISITOR_LOGO_URL"></div>`;
const copySnippet = async (a) => {
    try { await navigator.clipboard.writeText(snippet(a)); alert('Embed snippet copied.'); } catch { revealed.value = revealed.value === a.id ? null : a.id; }
};

const money = (v) => '$' + Number(v || 0).toFixed(2);
const statusClass = (s) => ({ active: 'bg-emerald-100 text-emerald-700', pending: 'bg-amber-100 text-amber-700', paused: 'bg-paper-300 text-ink/60' }[s] || 'bg-paper-300 text-ink/60');
</script>

<template>
    <Head title="Affiliates" />
    <AdminLayout title="Affiliates">
        <template #actions>
            <button class="rounded-full bg-brand-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-700" @click="creating = !creating">+ New affiliate</button>
        </template>

        <p class="mb-4 max-w-3xl text-sm text-ink/55">
            Partners embed the widget and pass their visitors' logo or website; the upsell engine renders it on real products
            inside the ad. We pay per 1000 viewable impressions (CPM, $15–20). Pending rows are applications from the
            <a href="/affiliates" target="_blank" class="text-brand-700 hover:underline">landing page</a> — set a CPM and activate to send them live.
        </p>

        <form v-if="creating" class="mb-4 grid gap-3 rounded-2xl border border-paper-300 bg-white p-4 shadow-sm sm:grid-cols-5" @submit.prevent="create">
            <input v-model="form.name" required placeholder="Contact name" class="border border-ink/20 px-3 py-2 text-sm focus:border-brand-600 focus:outline-none" />
            <input v-model="form.company" placeholder="Company" class="border border-ink/20 px-3 py-2 text-sm focus:border-brand-600 focus:outline-none" />
            <input v-model="form.email" required type="email" placeholder="Email" class="border border-ink/20 px-3 py-2 text-sm focus:border-brand-600 focus:outline-none" />
            <input v-model="form.website" placeholder="Website" class="border border-ink/20 px-3 py-2 text-sm focus:border-brand-600 focus:outline-none" />
            <button :disabled="form.processing" class="rounded-full bg-brand-600 px-5 py-2 text-sm font-semibold text-white hover:bg-brand-700 disabled:opacity-60">Create active</button>
            <p v-if="form.errors.email" class="text-xs text-red-600 sm:col-span-5">{{ form.errors.email }}</p>
        </form>

        <div class="overflow-x-auto rounded-2xl border border-paper-300 bg-white shadow-sm">
            <table class="w-full min-w-[900px] text-sm">
                <thead class="bg-paper-200 text-left text-xs font-semibold uppercase tracking-wide text-ink/50">
                    <tr>
                        <th class="px-5 py-3">Affiliate</th><th class="px-5 py-3">Status</th><th class="px-5 py-3">CPM</th>
                        <th class="px-5 py-3 text-right">Impressions</th><th class="px-5 py-3 text-right">Clicks</th>
                        <th class="px-5 py-3 text-right">Earned</th><th class="px-5 py-3 text-right">Paid</th><th class="px-5 py-3 text-right">Owed</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-paper-200">
                    <template v-for="a in affiliates" :key="a.id">
                        <tr class="transition hover:bg-paper-200/60">
                            <td class="px-5 py-3">
                                <p class="font-medium text-ink">{{ a.name }} <span v-if="a.company" class="text-ink/45">· {{ a.company }}</span></p>
                                <p class="text-xs text-ink/45">{{ a.email }} <span v-if="a.website">· {{ a.website }}</span></p>
                            </td>
                            <td class="px-5 py-3"><span class="rounded-full px-2.5 py-0.5 text-xs font-semibold capitalize" :class="statusClass(a.status)">{{ a.status }}</span></td>
                            <td class="px-5 py-3">
                                <button class="font-medium text-ink/80 underline decoration-dotted underline-offset-2 hover:text-brand-700" title="Change CPM" @click="setCpm(a)">{{ money(a.cpm) }}</button>
                            </td>
                            <td class="px-5 py-3 text-right tabular-nums text-ink/80">{{ a.impressions.toLocaleString() }}</td>
                            <td class="px-5 py-3 text-right tabular-nums text-ink/80">{{ a.clicks.toLocaleString() }}</td>
                            <td class="px-5 py-3 text-right tabular-nums font-medium text-ink">{{ money(a.earned) }}</td>
                            <td class="px-5 py-3 text-right tabular-nums text-ink/70">{{ money(a.paid) }}</td>
                            <td class="px-5 py-3 text-right tabular-nums font-semibold" :class="a.owed > 0 ? 'text-brand-700' : 'text-ink/50'">{{ money(a.owed) }}</td>
                            <td class="px-5 py-3 text-right">
                                <div class="flex justify-end gap-2 whitespace-nowrap text-xs font-medium">
                                    <button v-if="a.status !== 'active'" class="text-emerald-700 hover:underline" @click="setStatus(a, 'active')">Activate</button>
                                    <button v-else class="text-ink/55 hover:underline" @click="setStatus(a, 'paused')">Pause</button>
                                    <button class="text-brand-700 hover:underline" @click="recordPayout(a)">Payout</button>
                                    <button class="text-ink/55 hover:underline" @click="copySnippet(a)">Embed</button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="revealed === a.id"><td colspan="9" class="bg-paper-200/50 px-5 py-3"><pre class="overflow-x-auto text-xs text-ink/70">{{ snippet(a) }}</pre></td></tr>
                    </template>
                    <tr v-if="!affiliates.length"><td colspan="9" class="px-5 py-12 text-center text-ink/50">No affiliates yet — applications from the landing page appear here.</td></tr>
                </tbody>
            </table>
        </div>
    </AdminLayout>
</template>
