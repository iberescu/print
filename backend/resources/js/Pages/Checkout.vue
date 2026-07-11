<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import StoreLayout from '../Layouts/StoreLayout.vue';
import { money } from '../lib/format';

const props = defineProps({
    items: { type: Array, default: () => [] },
    summary: { type: Object, default: () => ({}) },
    customer: { type: Object, default: () => ({}) },
});

const methods = computed(() => props.summary.methods || []);
const form = useForm({
    email: props.customer.email ?? '',
    name: props.customer.name ?? '',
    company: '',
    address: '', city: '', state: '', postal: '', country: 'United States',
    shippingMethod: props.summary.shipping_method ?? methods.value[0]?.code ?? 'economy',
    billingSame: true,
    billingName: '', billingCompany: '', billingAddress: '', billingCity: '', billingState: '', billingPostal: '', billingCountry: 'United States',
});
const US_STATES = ['AL','AK','AZ','AR','CA','CO','CT','DE','DC','FL','GA','HI','ID','IL','IN','IA','KS','KY','LA','ME','MD','MA','MI','MN','MS','MO','MT','NE','NV','NH','NJ','NM','NY','NC','ND','OH','OK','OR','PA','RI','SC','SD','TN','TX','UT','VT','VA','WA','WV','WI','WY'];

const selectedMethod = computed(() => methods.value.find((m) => m.code === form.shippingMethod) || methods.value[0]);
const shippingCost = computed(() => (selectedMethod.value ? Number(selectedMethod.value.price) : Number(props.summary.shipping || 0)));
const taxRate = computed(() => Number(props.summary.tax_rates?.[form.state] ?? 0));
const taxable = computed(() => Math.max(0, Number(props.summary.subtotal || 0) - Number(props.summary.discount || 0)));
const taxAmount = computed(() => Math.round(taxable.value * taxRate.value) / 100);
const orderTotal = computed(() => Math.max(0, taxable.value + shippingCost.value + taxAmount.value));
const remainingForFree = computed(() => Math.max(0, Number(props.summary.threshold || 0) - Number(props.summary.subtotal || 0)));
const itemCount = computed(() => props.items.length);

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

                    <!-- delivery speed -->
                    <div class="space-y-2 border-t border-paper-200 pt-5">
                        <h2 class="font-display text-lg font-semibold">Delivery speed</h2>
                        <label
                            v-for="m in methods" :key="m.code"
                            class="flex cursor-pointer items-center gap-3 rounded-xl border px-4 py-3 transition"
                            :class="form.shippingMethod === m.code ? 'border-brand-600 bg-brand-50' : 'border-paper-300 hover:border-ink/25'"
                        >
                            <input v-model="form.shippingMethod" type="radio" :value="m.code" class="h-4 w-4 accent-brand-600" />
                            <span class="flex-1">
                                <span class="block text-sm font-semibold text-ink">{{ m.label }}</span>
                                <span class="block text-xs text-ink/55">{{ m.eta }}</span>
                            </span>
                            <span class="text-right">
                                <span class="block text-sm font-semibold" :class="m.free ? 'text-brand-700' : 'text-ink'">{{ m.free || m.price === 0 ? 'FREE' : money(m.price) }}</span>
                                <span v-if="!m.free && m.unit_price > 0" class="block text-[11px] text-ink/45">{{ money(m.unit_price) }}/item<template v-if="itemCount > 1"> × {{ itemCount }}</template></span>
                            </span>
                        </label>
                        <p v-if="remainingForFree > 0" class="pt-1 text-xs text-ink/50">Add <strong class="text-ink">{{ money(remainingForFree) }}</strong> more to unlock free Standard shipping.</p>
                        <p class="pt-1 text-[11px] text-ink/40">*Estimated delivery timeframe, not a guaranteed date.</p>
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
                                <span v-if="selectedMethod" class="block text-[11px] text-ink/45">🚚 {{ selectedMethod.eta }} · {{ selectedMethod.free || selectedMethod.unit_price === 0 ? 'free shipping' : '+' + money(selectedMethod.unit_price) + ' shipping' }}</span>
                            </span>
                            <span class="shrink-0 font-medium">{{ money(it.line_total) }}</span>
                        </div>
                    </div>
                    <dl class="mt-4 space-y-2 border-t border-paper-300 pt-3 text-sm">
                        <div class="flex justify-between"><dt class="text-ink/60">Subtotal</dt><dd>{{ money(summary.subtotal) }}</dd></div>
                        <div v-if="summary.discount > 0" class="flex justify-between text-brand-700"><dt>Discount{{ summary.coupon ? ` (${summary.coupon})` : '' }}</dt><dd>−{{ money(summary.discount) }}</dd></div>
                        <div class="flex justify-between">
                            <dt class="text-ink/60">Shipping <span class="text-ink/40">· {{ selectedMethod?.label }}</span>
                                <span v-if="shippingCost > 0 && selectedMethod" class="block text-[11px] text-ink/40">{{ money(selectedMethod.unit_price) }}/item × {{ itemCount }} {{ itemCount === 1 ? 'product' : 'products' }}</span>
                            </dt>
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
