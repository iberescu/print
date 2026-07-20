<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, nextTick, onMounted } from 'vue';
import StoreLayout from '../Layouts/StoreLayout.vue';
import { money } from '../lib/format';
import { rtbStartOrder } from '../lib/rtb';

const props = defineProps({
    items: { type: Array, default: () => [] },
    summary: { type: Object, default: () => ({}) },
    customer: { type: Object, default: () => ({}) },
});

onMounted(() => rtbStartOrder()); // RTB House (store hosts only — no-op on the main shop)

const methods = computed(() => props.summary.methods || []);
const defaultCode = props.summary.methods?.[0]?.code ?? 'economy';
const form = useForm({
    email: props.customer.email ?? '',
    name: props.customer.name ?? '',
    company: '',
    address: '', city: '', state: '', postal: '', country: 'United States',
    // each product carries its own delivery-speed choice
    itemMethods: Object.fromEntries((props.items ?? []).map((it) => [it.id, it.ship ?? defaultCode])),
    billingSame: true,
    billingName: '', billingCompany: '', billingAddress: '', billingCity: '', billingState: '', billingPostal: '', billingCountry: 'United States',
});
const US_STATES = ['AL','AK','AZ','AR','CA','CO','CT','DE','DC','FL','GA','HI','ID','IL','IN','IA','KS','KY','LA','ME','MD','MA','MI','MN','MS','MO','MT','NE','NV','NH','NJ','NM','NY','NC','ND','OH','OK','OR','PA','RI','SC','SD','TN','TX','UT','VT','VA','WA','WV','WI','WY'];

const methodByCode = (code) => methods.value.find((m) => m.code === code) || methods.value[0];
const itemShip = (it) => { const m = methodByCode(form.itemMethods[it.id]); return m ? (m.free_eligible ? 0 : Number(m.unit_price)) : 0; };
const shipDate = (it) => methodByCode(form.itemMethods[it.id])?.eta ?? '';
const shippingCost = computed(() => Math.round((props.items ?? []).reduce((s, it) => s + itemShip(it), 0) * 100) / 100);
const taxRate = computed(() => Number(props.summary.tax_rates?.[form.state] ?? 0));
const taxable = computed(() => Math.max(0, Number(props.summary.subtotal || 0) - Number(props.summary.discount || 0)));
const taxAmount = computed(() => Math.round(taxable.value * taxRate.value) / 100);
const orderTotal = computed(() => Math.max(0, taxable.value + shippingCost.value + taxAmount.value));
const remainingForFree = computed(() => Math.max(0, Number(props.summary.threshold || 0) - Number(props.summary.subtotal || 0)));

const submit = () => form.post('/checkout', {
    preserveScroll: true,
    // On mobile the Pay button is far down the form — bring any validation failure into view
    // right where the user tapped, instead of a silent jump to the top.
    onError: () => nextTick(() => document.getElementById('checkout-errors')?.scrollIntoView({ behavior: 'smooth', block: 'center' })),
});
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
                            <span v-if="form.errors.name" class="text-xs text-red-600">{{ form.errors.name }}</span>
                        </label>
                        <label class="block sm:col-span-2"><span class="text-sm text-ink/60">Company <span class="text-ink/40">(optional)</span></span>
                            <input v-model="form.company" :class="field" />
                        </label>
                        <label class="block sm:col-span-2"><span class="text-sm text-ink/60">Address</span>
                            <input v-model="form.address" required :class="field" />
                        </label>
                        <label class="block"><span class="text-sm text-ink/60">City</span>
                            <input v-model="form.city" required :class="field" />
                        </label>
                        <label class="block"><span class="text-sm text-ink/60">State</span>
                            <select v-model="form.state" required :class="field">
                                <option value="" disabled>Select…</option>
                                <option v-for="s in US_STATES" :key="s" :value="s">{{ s }}</option>
                            </select>
                            <span v-if="form.errors.state" class="text-xs text-red-600">{{ form.errors.state }}</span>
                        </label>
                        <label class="block"><span class="text-sm text-ink/60">Postal code</span>
                            <input v-model="form.postal" required :class="field" />
                        </label>
                        <label class="block"><span class="text-sm text-ink/60">Country</span>
                            <input v-model="form.country" required :class="field" />
                        </label>
                    </div>

                    <!-- billing address -->
                    <div class="space-y-3 border-t border-paper-200 pt-5">
                        <h2 class="font-display text-lg font-semibold">Billing address</h2>
                        <label class="flex items-center gap-2 text-sm text-ink/75">
                            <input v-model="form.billingSame" type="checkbox" class="h-4 w-4 accent-brand-600" />
                            Use same as shipping address
                        </label>
                        <div v-if="!form.billingSame" class="grid gap-4 sm:grid-cols-2">
                            <label class="block sm:col-span-2"><span class="text-sm text-ink/60">Full name</span>
                                <input v-model="form.billingName" :class="field" />
                                <span v-if="form.errors.billingName" class="text-xs text-red-600">{{ form.errors.billingName }}</span>
                            </label>
                            <label class="block sm:col-span-2"><span class="text-sm text-ink/60">Company <span class="text-ink/40">(optional)</span></span>
                                <input v-model="form.billingCompany" :class="field" />
                            </label>
                            <label class="block sm:col-span-2"><span class="text-sm text-ink/60">Address</span>
                                <input v-model="form.billingAddress" :class="field" />
                            </label>
                            <label class="block"><span class="text-sm text-ink/60">City</span>
                                <input v-model="form.billingCity" :class="field" />
                            </label>
                            <label class="block"><span class="text-sm text-ink/60">State</span>
                                <select v-model="form.billingState" :class="field">
                                    <option value="" disabled>Select…</option>
                                    <option v-for="s in US_STATES" :key="s" :value="s">{{ s }}</option>
                                </select>
                            </label>
                            <label class="block"><span class="text-sm text-ink/60">Postal code</span>
                                <input v-model="form.billingPostal" :class="field" />
                            </label>
                            <label class="block"><span class="text-sm text-ink/60">Country</span>
                                <input v-model="form.billingCountry" :class="field" />
                            </label>
                        </div>
                    </div>

                    <!-- delivery speed (per product) -->
                    <div class="space-y-3 border-t border-paper-200 pt-5">
                        <h2 class="font-display text-lg font-semibold">Delivery speed <span class="text-sm font-normal text-ink/50">— choose per product</span></h2>
                        <div v-for="it in items" :key="it.id" class="flex flex-col gap-1.5 sm:flex-row sm:items-center sm:justify-between sm:gap-3">
                            <span class="text-sm text-ink/80 sm:min-w-0 sm:flex-1 sm:truncate">{{ it.name }} <span class="text-ink/40">×{{ it.quantity }}</span></span>
                            <select v-model="form.itemMethods[it.id]" data-ship class="w-full shrink-0 rounded-lg border border-paper-300 px-2.5 py-2 text-sm focus:border-brand-600 focus:outline-none sm:w-[62%]">
                                <option v-for="m in methods" :key="m.code" :value="m.code">
                                    {{ m.label }} · {{ m.free_eligible ? 'FREE' : money(m.unit_price) }} · by {{ m.eta.replace('Delivery as soon as ', '').replace('*', '') }}
                                </option>
                            </select>
                        </div>
                        <p v-if="remainingForFree > 0" class="pt-1 text-xs text-ink/50">Add <strong class="text-ink">{{ money(remainingForFree) }}</strong> more to unlock free Standard shipping.</p>
                        <p class="pt-1 text-[11px] text-ink/40">*Estimated delivery timeframe, not a guaranteed date.</p>
                    </div>

                    <div v-if="Object.keys(form.errors).length" id="checkout-errors" class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">
                        <p class="font-medium">Please fix the following:</p>
                        <ul class="mt-1 list-disc space-y-0.5 pl-5">
                            <li v-for="(msg, key) in form.errors" :key="key">{{ msg }}</li>
                        </ul>
                    </div>
                    <button :disabled="form.processing" class="w-full rounded-full bg-brand-600 px-6 py-3.5 font-semibold text-white transition hover:bg-brand-700 disabled:opacity-60">
                        {{ form.processing ? 'Processing…' : 'Pay ' + money(orderTotal) }}
                    </button>
                    <p class="text-center text-xs text-ink/45">🔒 Secure checkout — card details are handled by Stripe.</p>
                </form>

                <aside class="h-max rounded-2xl border border-paper-300 bg-white p-5 lg:sticky lg:top-24">
                    <h2 class="font-display text-lg font-semibold">Order summary</h2>
                    <div class="mt-3 space-y-2">
                        <div v-for="it in items" :key="it.id" class="flex justify-between gap-3 text-sm">
                            <span class="text-ink/70">{{ it.name }} <span class="text-ink/40">×{{ it.quantity }}</span>
                                <span class="block text-[11px] text-ink/45">🚚 {{ shipDate(it) }} · {{ itemShip(it) === 0 ? 'free shipping' : '+' + money(itemShip(it)) + ' shipping' }}</span>
                            </span>
                            <span class="shrink-0 font-medium">{{ money(it.line_total) }}</span>
                        </div>
                    </div>
                    <dl class="mt-4 space-y-2 border-t border-paper-300 pt-3 text-sm">
                        <div class="flex justify-between"><dt class="text-ink/60">Subtotal</dt><dd>{{ money(summary.subtotal) }}</dd></div>
                        <div v-if="summary.discount > 0" class="flex justify-between text-brand-700"><dt>Discount{{ summary.coupon ? ` (${summary.coupon})` : '' }}</dt><dd>−{{ money(summary.discount) }}</dd></div>
                        <div class="flex justify-between">
                            <dt class="text-ink/60">Shipping <span class="text-ink/40">· per product</span></dt>
                            <dd>{{ shippingCost > 0 ? money(shippingCost) : 'FREE' }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-ink/60">Estimated tax <span v-if="form.state" class="text-ink/40">· {{ form.state }} {{ taxRate }}%</span></dt>
                            <dd>{{ form.state ? money(taxAmount) : '—' }}</dd>
                        </div>
                        <div class="flex justify-between border-t border-paper-300 pt-2 text-base font-semibold"><dt>Total</dt><dd>{{ money(orderTotal) }}</dd></div>
                    </dl>
                    <Link href="/cart" class="mt-4 block text-center text-sm text-ink/55 transition hover:text-ink">← Back to cart</Link>
                </aside>
            </div>
        </div>
    </StoreLayout>
</template>
