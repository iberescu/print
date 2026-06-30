<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';

const props = defineProps({ order: { type: Object, required: true } });

const money = (n) => '$' + Number(n).toFixed(2);
const statusClass = (s) => ({ paid: 'bg-emerald-100 text-emerald-700', pending: 'bg-amber-100 text-amber-700', failed: 'bg-red-100 text-red-700' }[s] || 'bg-paper-300 text-ink/60');
const setStatus = (e) => router.patch(`/admin/orders/${props.order.number}`, { status: e.target.value }, { preserveScroll: true });
</script>

<template>
    <Head :title="`Order ${order.number}`" />
    <AdminLayout :title="`Order ${order.number}`">
        <template #actions>
            <Link href="/admin/orders" class="text-sm font-medium text-ink/60 hover:text-ink">← Back to orders</Link>
        </template>

        <div class="grid gap-6 lg:grid-cols-3">
            <!-- items -->
            <div class="lg:col-span-2">
                <div class="overflow-hidden rounded-2xl border border-paper-300 bg-white shadow-sm">
                    <div class="border-b border-paper-300 px-5 py-4">
                        <h2 class="font-display text-base font-semibold text-ink">Items ({{ order.items?.length || 0 }})</h2>
                    </div>
                    <div class="divide-y divide-paper-200">
                        <div v-for="(it, i) in order.items" :key="i" class="flex gap-4 p-5">
                            <div class="h-16 w-16 shrink-0 overflow-hidden rounded-lg border border-paper-300 bg-paper-200">
                                <img v-if="it.design?.preview || it.image" :src="it.design?.preview || it.image" :alt="it.name" class="h-full w-full object-cover" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="font-medium text-ink">{{ it.name }}</p>
                                <p class="text-sm text-ink/55">Qty {{ it.quantity }}<span v-if="it.design?.mode"> · {{ it.design.mode }}</span></p>
                                <div v-if="it.options && Object.keys(it.options).length" class="mt-1 flex flex-wrap gap-1.5">
                                    <span v-for="(val, key) in it.options" :key="key" class="rounded bg-paper-200 px-2 py-0.5 text-xs text-ink/60">{{ key }}: {{ val }}</span>
                                </div>
                            </div>
                            <div class="shrink-0 text-right">
                                <p class="font-semibold text-ink">{{ money(it.line_total) }}</p>
                                <p class="text-xs text-ink/45">{{ money(it.unit_price) }} ea</p>
                            </div>
                        </div>
                    </div>
                    <div class="space-y-1.5 border-t border-paper-300 bg-paper-200/50 px-5 py-4 text-sm">
                        <div class="flex justify-between text-ink/60"><span>Subtotal</span><span>{{ money(order.subtotal) }}</span></div>
                        <div class="flex justify-between text-ink/60"><span>Shipping</span><span>{{ order.shipping > 0 ? money(order.shipping) : 'Free' }}</span></div>
                        <div class="flex justify-between pt-1 font-display text-base font-bold text-ink"><span>Total</span><span>{{ money(order.total) }}</span></div>
                    </div>
                </div>
            </div>

            <!-- side: status + customer -->
            <div class="space-y-6">
                <div class="rounded-2xl border border-paper-300 bg-white p-5 shadow-sm">
                    <div class="flex items-center justify-between">
                        <h2 class="font-display text-base font-semibold text-ink">Status</h2>
                        <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold capitalize" :class="statusClass(order.status)">{{ order.status }}</span>
                    </div>
                    <label class="mt-3 block text-xs font-medium text-ink/55">Change status</label>
                    <select :value="order.status" class="mt-1 w-full border border-ink/20 bg-white px-3 py-2 text-sm focus:border-brand-600 focus:outline-none" @change="setStatus">
                        <option value="pending">Pending</option>
                        <option value="paid">Paid</option>
                        <option value="failed">Failed</option>
                    </select>
                    <p class="mt-3 text-xs text-ink/45">Placed {{ order.date }}</p>
                </div>

                <div class="rounded-2xl border border-paper-300 bg-white p-5 shadow-sm">
                    <h2 class="font-display text-base font-semibold text-ink">Customer</h2>
                    <p class="mt-3 font-medium text-ink">{{ order.name || '—' }}</p>
                    <p class="text-sm text-ink/60">{{ order.email }}</p>
                    <div v-if="order.address" class="mt-3 border-t border-paper-200 pt-3 text-sm text-ink/60">
                        <p>{{ order.address.line }}</p>
                        <p>{{ order.address.city }} {{ order.address.postal }}</p>
                        <p>{{ order.address.country }}</p>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
