<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';

defineProps({ customers: { type: Object, default: () => ({ data: [], links: [] }) } });

const money = (n) => '$' + Number(n).toFixed(2);
</script>

<template>
    <Head title="Customers" />
    <AdminLayout title="Customers">
        <div class="overflow-hidden rounded-2xl border border-paper-300 bg-white shadow-sm">
            <table class="w-full text-sm">
                <thead class="bg-paper-200 text-left text-xs font-semibold uppercase tracking-wide text-ink/50">
                    <tr><th class="px-5 py-3">Customer</th><th class="px-5 py-3">Orders</th><th class="px-5 py-3">Total spent</th><th class="hidden px-5 py-3 sm:table-cell">Last order</th></tr>
                </thead>
                <tbody class="divide-y divide-paper-200">
                    <tr v-for="c in customers.data" :key="c.email" class="transition hover:bg-paper-200/60">
                        <td class="px-5 py-3">
                            <p class="font-medium text-ink">{{ c.name || '—' }}</p>
                            <p class="text-xs text-ink/50">{{ c.email }}</p>
                        </td>
                        <td class="px-5 py-3 text-ink/70">{{ c.orders }}</td>
                        <td class="px-5 py-3 font-medium text-ink">{{ money(c.spent) }}</td>
                        <td class="hidden px-5 py-3 text-xs text-ink/50 sm:table-cell">{{ c.lastOrder }}</td>
                    </tr>
                    <tr v-if="!customers.data.length"><td colspan="4" class="px-5 py-12 text-center text-ink/50">No customers yet.</td></tr>
                </tbody>
            </table>
        </div>

        <div v-if="customers.links && customers.links.length > 3" class="mt-4 flex flex-wrap gap-1">
            <template v-for="(l, i) in customers.links" :key="i">
                <Link v-if="l.url" :href="l.url" class="rounded-md px-3 py-1.5 text-sm transition" :class="l.active ? 'bg-brand-600 text-white' : 'bg-white text-ink/70 ring-1 ring-paper-300 hover:bg-paper-200'" v-html="l.label" />
                <span v-else class="rounded-md px-3 py-1.5 text-sm text-ink/30" v-html="l.label" />
            </template>
        </div>
    </AdminLayout>
</template>
