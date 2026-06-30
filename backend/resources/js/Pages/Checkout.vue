<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import StoreLayout from '../Layouts/StoreLayout.vue';

const props = defineProps({
    items: { type: Array, default: () => [] },
    summary: { type: Object, default: () => ({}) },
    customer: { type: Object, default: () => ({}) },
});

const money = (n) => '$' + Number(n || 0).toFixed(2);
const form = useForm({ email: props.customer.email ?? '', name: props.customer.name ?? '', address: '', city: '', postal: '', country: 'United States' });
const submit = () => form.post('/checkout');
const field = 'mt-1 w-full rounded-lg border border-paper-300 px-3 py-2.5 text-ink focus:border-brand-600 focus:outline-none focus:ring-2 focus:ring-brand-600/15';
</script>

<template>
    <Head title="Checkout" />
    <StoreLayout>
        <div class="mx-auto max-w-5xl px-6 py-10">
            <h1 class="font-display text-3xl font-semibold tracking-tight">Checkout</h1>

            <div class="mt-8 grid gap-8 lg:grid-cols-[1fr_360px]">
                <form class="space-y-5 rounded-2xl border border-paper-300 bg-white p-6" @submit.prevent="submit">
                    <h2 class="font-display text-lg font-semibold">Contact &amp; shipping</h2>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="block sm:col-span-2"><span class="text-sm text-ink/60">Email</span>
                            <input v-model="form.email" type="email" required :class="field" />
                            <span v-if="form.errors.email" class="text-xs text-red-600">{{ form.errors.email }}</span>
                        </label>
                        <label class="block sm:col-span-2"><span class="text-sm text-ink/60">Full name</span>
                            <input v-model="form.name" required :class="field" />
                        </label>
                        <label class="block sm:col-span-2"><span class="text-sm text-ink/60">Address</span>
                            <input v-model="form.address" required :class="field" />
                        </label>
                        <label class="block"><span class="text-sm text-ink/60">City</span>
                            <input v-model="form.city" required :class="field" />
                        </label>
                        <label class="block"><span class="text-sm text-ink/60">Postal code</span>
                            <input v-model="form.postal" required :class="field" />
                        </label>
                        <label class="block sm:col-span-2"><span class="text-sm text-ink/60">Country</span>
                            <input v-model="form.country" required :class="field" />
                        </label>
                    </div>
                    <button :disabled="form.processing" class="w-full rounded-full bg-brand-600 px-6 py-3.5 font-semibold text-white transition hover:bg-brand-700 disabled:opacity-60">
                        {{ form.processing ? 'Processing…' : 'Pay ' + money(summary.total) }}
                    </button>
                    <p class="text-center text-xs text-ink/45">🔒 Secure checkout — card details are handled by Stripe.</p>
                </form>

                <aside class="h-max rounded-2xl border border-paper-300 bg-white p-5 lg:sticky lg:top-24">
                    <h2 class="font-display text-lg font-semibold">Order summary</h2>
                    <div class="mt-3 space-y-2">
                        <div v-for="it in items" :key="it.id" class="flex justify-between gap-3 text-sm">
                            <span class="text-ink/70">{{ it.name }} <span class="text-ink/40">×{{ it.quantity }}</span></span>
                            <span class="shrink-0 font-medium">{{ money(it.line_total) }}</span>
                        </div>
                    </div>
                    <dl class="mt-4 space-y-2 border-t border-paper-300 pt-3 text-sm">
                        <div class="flex justify-between"><dt class="text-ink/60">Subtotal</dt><dd>{{ money(summary.subtotal) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-ink/60">Shipping</dt><dd>{{ summary.shipping ? money(summary.shipping) : 'FREE' }}</dd></div>
                        <div class="flex justify-between border-t border-paper-300 pt-2 text-base font-semibold"><dt>Total</dt><dd>{{ money(summary.total) }}</dd></div>
                    </dl>
                    <Link href="/cart" class="mt-4 block text-center text-sm text-ink/55 transition hover:text-ink">← Back to cart</Link>
                </aside>
            </div>
        </div>
    </StoreLayout>
</template>
