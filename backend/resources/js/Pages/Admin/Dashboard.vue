<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '../../Layouts/AdminLayout.vue';

defineProps({ stats: { type: Object, default: () => ({}) }, recent: { type: Array, default: () => [] } });

const money = (n) => '$' + Number(n).toFixed(2);
const statusClass = (s) => ({ paid: 'bg-emerald-100 text-emerald-700', pending: 'bg-amber-100 text-amber-700', failed: 'bg-red-100 text-red-700' }[s] || 'bg-paper-300 text-ink/60');
</script>

<template>
    <Head title="Dashboard" />
    <AdminLayout title="Dashboard">
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <div class="rounded-2xl border border-paper-300 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-widest text-ink/45">Revenue (paid)</p>
                <p class="mt-2 font-display text-3xl font-bold text-ink">{{ money(stats.revenue || 0) }}</p>
            </div>
            <div class="rounded-2xl border border-paper-300 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-widest text-ink/45">Orders</p>
                <p class="mt-2 font-display text-3xl font-bold text-ink">{{ stats.orders || 0 }}</p>
                <p class="mt-1 text-xs text-ink/50">{{ stats.paid || 0 }} paid · {{ stats.pending || 0 }} pending</p>
            </div>
            <div class="rounded-2xl border border-paper-300 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-widest text-ink/45">Customers</p>
                <p class="mt-2 font-display text-3xl font-bold text-ink">{{ stats.customers || 0 }}</p>
            </div>
            <div class="rounded-2xl border border-paper-300 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-widest text-ink/45">Products</p>
                <p class="mt-2 font-display text-3xl font-bold text-ink">{{ stats.products || 0 }}</p>
                <p class="mt-1 text-xs text-ink/50">{{ stats.active || 0 }} active</p>
            </div>
        </div>

        <div class="mt-8 overflow-hidden rounded-2xl border border-paper-300 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-paper-300 px-5 py-4">
                <h2 class="font-display text-base font-semibold text-ink">Recent orders</h2>
                <Link href="/admin/orders" class="text-sm font-medium text-brand-700 hover:underline">View all →</Link>
            </div>
            <table class="w-full text-sm">
                <thead class="bg-paper-200 text-left text-xs font-semibold uppercase tracking-wide text-ink/50">
                    <tr><th class="px-5 py-3">Order</th><th class="px-5 py-3">Customer</th><th class="px-5 py-3">Items</th><th class="px-5 py-3">Total</th><th class="px-5 py-3">Status</th><th class="hidden px-5 py-3 sm:table-cell">Date</th></tr>
                </thead>
                <tbody class="divide-y divide-paper-200">
                    <tr v-for="o in recent" :key="o.number" class="transition hover:bg-paper-200/60">
                        <td class="px-5 py-3"><Link :href="`/admin/orders/${o.number}`" class="font-medium text-brand-700 hover:underline">{{ o.number }}</Link></td>
                        <td class="px-5 py-3"><p class="font-medium text-ink">{{ o.name || '—' }}</p><p class="text-xs text-ink/50">{{ o.email }}</p></td>
                        <td class="px-5 py-3 text-ink/70">{{ o.items }}</td>
                        <td class="px-5 py-3 font-medium text-ink">{{ money(o.total) }}</td>
                        <td class="px-5 py-3"><span class="rounded-full px-2.5 py-0.5 text-xs font-semibold capitalize" :class="statusClass(o.status)">{{ o.status }}</span></td>
                        <td class="hidden px-5 py-3 text-xs text-ink/50 sm:table-cell">{{ o.date }}</td>
                    </tr>
                    <tr v-if="!recent.length"><td colspan="6" class="px-5 py-10 text-center text-ink/50">No orders yet.</td></tr>
                </tbody>
            </table>
        </div>
    </AdminLayout>
</template>
