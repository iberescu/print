<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';

defineProps({
    orders: { type: Object, default: () => ({ data: [], links: [] }) },
    status: { type: String, default: null },
    counts: { type: Object, default: () => ({}) },
});

const money = (n) => '$' + Number(n).toFixed(2);
const statusClass = (s) => ({ paid: 'bg-emerald-100 text-emerald-700', pending: 'bg-amber-100 text-amber-700', failed: 'bg-red-100 text-red-700' }[s] || 'bg-paper-300 text-ink/60');
const tabs = [{ key: null, label: 'All' }, { key: 'pending', label: 'Pending' }, { key: 'paid', label: 'Paid' }, { key: 'failed', label: 'Failed' }];
const tabHref = (key) => (key ? `/admin/orders?status=${key}` : '/admin/orders');
</script>

<template>
    <Head title="Orders" />
    <AdminLayout title="Orders">
        <div class="mb-4 flex flex-wrap gap-1.5">
            <Link v-for="t in tabs" :key="t.label" :href="tabHref(t.key)"
                  class="rounded-full px-4 py-1.5 text-sm font-medium transition"
                  :class="(status ?? null) === t.key ? 'bg-brand-600 text-white' : 'bg-white text-ink/70 ring-1 ring-paper-300 hover:bg-paper-200'">
                {{ t.label }}<span class="ml-1.5 text-xs opacity-70">{{ t.key ? (counts[t.key] ?? 0) : (counts.all ?? 0) }}</span>
            </Link>
        </div>

        <div class="overflow-hidden rounded-2xl border border-paper-300 bg-white shadow-sm">
            <table class="w-full text-sm">
                <thead class="bg-paper-200 text-left text-xs font-semibold uppercase tracking-wide text-ink/50">
                    <tr><th class="px-5 py-3">Order</th><th class="px-5 py-3">Customer</th><th class="px-5 py-3">Items</th><th class="px-5 py-3">Total</th><th class="px-5 py-3">Status</th><th class="hidden px-5 py-3 sm:table-cell">Date</th></tr>
                </thead>
                <tbody class="divide-y divide-paper-200">
                    <tr v-for="o in orders.data" :key="o.number" class="transition hover:bg-paper-200/60">
                        <td class="px-5 py-3"><Link :href="`/admin/orders/${o.number}`" class="font-medium text-brand-700 hover:underline">{{ o.number }}</Link></td>
                        <td class="px-5 py-3"><p class="font-medium text-ink">{{ o.name || '—' }}</p><p class="text-xs text-ink/50">{{ o.email }}</p></td>
                        <td class="px-5 py-3 text-ink/70">{{ o.items }}</td>
                        <td class="px-5 py-3 font-medium text-ink">{{ money(o.total) }}</td>
                        <td class="px-5 py-3"><span class="rounded-full px-2.5 py-0.5 text-xs font-semibold capitalize" :class="statusClass(o.status)">{{ o.status }}</span></td>
                        <td class="hidden px-5 py-3 text-xs text-ink/50 sm:table-cell">{{ o.date }}</td>
                    </tr>
                    <tr v-if="!orders.data.length"><td colspan="6" class="px-5 py-12 text-center text-ink/50">No orders found.</td></tr>
                </tbody>
            </table>
        </div>

        <div v-if="orders.links && orders.links.length > 3" class="mt-4 flex flex-wrap gap-1">
            <template v-for="(l, i) in orders.links" :key="i">
                <Link v-if="l.url" :href="l.url" class="rounded-md px-3 py-1.5 text-sm transition" :class="l.active ? 'bg-brand-600 text-white' : 'bg-white text-ink/70 ring-1 ring-paper-300 hover:bg-paper-200'" v-html="l.label" />
                <span v-else class="rounded-md px-3 py-1.5 text-sm text-ink/30" v-html="l.label" />
            </template>
        </div>
    </AdminLayout>
</template>
