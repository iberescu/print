<script setup>
import { computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import StoreLayout from '../Layouts/StoreLayout.vue';
import { money } from '../lib/format';

defineProps({
    orders: { type: Array, default: () => [] },
    designs: { type: Array, default: () => [] },
});

const user = computed(() => usePage().props.auth?.user ?? {});
const statusClass = (s) => ({ paid: 'bg-emerald-100 text-emerald-700', pending: 'bg-amber-100 text-amber-700', failed: 'bg-red-100 text-red-700' }[s] || 'bg-paper-300 text-ink/60');
const logout = () => router.post('/logout');
</script>

<template>
    <Head title="My account" />
    <StoreLayout>
        <div class="mx-auto max-w-4xl px-6 py-10 sm:py-14">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="font-display text-3xl font-bold tracking-tight text-ink">My account</h1>
                    <p class="mt-1 text-ink/60">{{ user.name }} · {{ user.email }}</p>
                </div>
                <button class="rounded-full border border-ink/20 bg-white px-5 py-2.5 text-sm font-semibold text-ink transition hover:border-ink/40" @click="logout">Sign out</button>
            </div>

            <template v-if="designs.length">
                <h2 class="mt-10 font-display text-xl font-semibold text-ink">My designs</h2>
                <div class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-3">
                    <div v-for="d in designs" :key="d.id" class="group overflow-hidden rounded-2xl border border-paper-300 bg-white shadow-sm transition hover:shadow-md">
                        <Link :href="`/design/${d.slug}?project=${d.id}`" class="block">
                            <div class="aspect-[16/10] bg-paper-200">
                                <img v-if="d.preview" :src="d.preview" :alt="`${d.product} design`" loading="lazy" class="h-full w-full object-cover" />
                                <div v-else class="grid h-full place-items-center text-3xl text-ink/20">✎</div>
                            </div>
                        </Link>
                        <div class="flex items-center justify-between gap-2 px-3.5 py-2.5">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-ink">{{ d.product }}</p>
                                <p class="text-xs text-ink/45">{{ d.date }}</p>
                            </div>
                            <Link :href="`/design/${d.slug}?project=${d.id}`" class="shrink-0 rounded-full border border-brand-600 px-3 py-1 text-xs font-semibold text-brand-700 transition hover:bg-brand-50">Edit</Link>
                        </div>
                    </div>
                </div>
            </template>

            <h2 class="mt-10 font-display text-xl font-semibold text-ink">Order history</h2>
            <div class="mt-4 overflow-hidden rounded-2xl border border-paper-300 bg-white shadow-sm">
                <table class="w-full text-sm">
                    <thead class="bg-paper-200 text-left text-xs font-semibold uppercase tracking-wide text-ink/50">
                        <tr><th class="px-5 py-3">Order</th><th class="px-5 py-3">Items</th><th class="px-5 py-3">Total</th><th class="px-5 py-3">Status</th><th class="hidden px-5 py-3 sm:table-cell">Date</th></tr>
                    </thead>
                    <tbody class="divide-y divide-paper-200">
                        <tr v-for="o in orders" :key="o.number">
                            <td class="px-5 py-3 font-medium text-ink">{{ o.number }}</td>
                            <td class="px-5 py-3 text-ink/70">{{ o.items }}</td>
                            <td class="px-5 py-3 font-medium text-ink">{{ money(o.total) }}</td>
                            <td class="px-5 py-3"><span class="rounded-full px-2.5 py-0.5 text-xs font-semibold capitalize" :class="statusClass(o.status)">{{ o.status }}</span></td>
                            <td class="hidden px-5 py-3 text-xs text-ink/50 sm:table-cell">{{ o.date }}</td>
                        </tr>
                        <tr v-if="!orders.length"><td colspan="5" class="px-5 py-12 text-center text-ink/50">No orders yet. <Link href="/" class="font-medium text-brand-700 hover:underline">Start shopping →</Link></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </StoreLayout>
</template>
