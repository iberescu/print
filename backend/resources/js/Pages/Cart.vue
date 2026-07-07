<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import StoreLayout from '../Layouts/StoreLayout.vue';
import FreeShippingBar from '../Components/FreeShippingBar.vue';
import { money } from '../lib/format';

defineProps({
    items: { type: Array, default: () => [] },
    summary: { type: Object, default: () => ({}) },
    recommended: { type: Array, default: () => [] },
});

const remove = (id) => router.post(`/cart/remove/${id}`, {}, { preserveScroll: true });

const promo = ref('');
const applying = ref(false);
const applyPromo = () => {
    if (!promo.value.trim() || applying.value) return;
    applying.value = true;
    router.post('/cart/coupon', { code: promo.value.trim() }, { preserveScroll: true, onFinish: () => { applying.value = false; promo.value = ''; } });
};
const removePromo = () => router.post('/cart/coupon/remove', {}, { preserveScroll: true });

// Reopen the line's design project in the editor; re-adding after the edit
// REPLACES this line (same project id), it doesn't duplicate it.
const editHref = (it) => {
    const p = new URLSearchParams();
    p.set('mode', it.design?.mode || 'design');
    p.set('project', it.design.project);
    if (it.quantity_id) p.set('qty', it.quantity_id);
    (it.option_value_ids || []).forEach((id) => p.append('opts[]', id));
    return `/design/${it.slug}?${p.toString()}`;
};
</script>

<template>
    <Head title="Your Cart" />
    <StoreLayout>
        <div class="mx-auto max-w-7xl px-6 py-10">
            <h1 class="font-display text-3xl font-semibold tracking-tight">Your cart</h1>

            <FreeShippingBar v-if="items.length" class="mt-6" :subtotal="summary.subtotal" :threshold="summary.threshold" :remaining="summary.remaining" :qualifies="summary.qualifies" />

            <div v-if="!items.length" class="mt-10 rounded-2xl border border-paper-300 bg-white p-12 text-center">
                <p class="text-ink/60">Your cart is empty.</p>
                <Link href="/" class="mt-5 inline-block rounded-full bg-brand-600 px-6 py-3 font-semibold text-white transition hover:bg-brand-700">Browse products</Link>
            </div>

            <div v-else class="mt-8 grid gap-8 lg:grid-cols-[1fr_360px]">
                <div class="space-y-4">
                    <div v-for="it in items" :key="it.id" class="flex gap-4 rounded-2xl border border-paper-300 bg-white p-4">
                        <div class="h-24 w-32 shrink-0 overflow-hidden rounded-lg bg-paper-200">
                            <img v-if="it.design?.preview || it.image" :src="it.design?.preview || it.image" :alt="it.name" class="h-full w-full object-cover" />
                        </div>
                        <div class="flex flex-1 flex-col">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-display text-lg font-semibold text-ink">{{ it.name }}</p>
                                    <p class="text-sm text-ink/50">Qty {{ it.quantity }} · {{ money(it.unit_price) }}/ea</p>
                                </div>
                                <p class="font-semibold text-ink">{{ money(it.line_total) }}</p>
                            </div>
                            <div class="mt-2 flex flex-wrap gap-1.5">
                                <span v-for="(val, key) in it.options" :key="key" class="rounded-full bg-paper-200 px-2.5 py-0.5 text-xs text-ink/70">{{ key }}: {{ val }}</span>
                                <span v-if="it.design" class="rounded-full bg-brand-50 px-2.5 py-0.5 text-xs font-medium text-brand-700">✎ {{ it.design.mode === 'upload' ? 'Uploaded artwork' : 'Custom design' }}</span>
                            </div>
                            <div class="mt-auto flex items-center gap-4 pt-2">
                                <Link v-if="it.design?.project" :href="editHref(it)" class="text-sm font-medium text-brand-700 transition hover:underline">✎ Edit design</Link>
                                <button class="text-sm text-ink/45 transition hover:text-red-600" @click="remove(it.id)">Remove</button>
                            </div>
                        </div>
                    </div>
                </div>

                <aside class="h-max rounded-2xl border border-paper-300 bg-white p-5 lg:sticky lg:top-24">
                    <form v-if="!summary.coupon" class="mb-4 flex gap-2" @submit.prevent="applyPromo">
                        <input v-model="promo" type="text" placeholder="Promo code" class="w-full rounded-lg border border-paper-300 px-3 py-2 text-sm uppercase placeholder:normal-case focus:border-brand-400 focus:outline-none" />
                        <button :disabled="applying" class="shrink-0 rounded-lg border border-brand-600 px-4 py-2 text-sm font-semibold text-brand-700 transition hover:bg-brand-50 disabled:opacity-60">Apply</button>
                    </form>
                    <div v-else class="mb-4 flex items-center justify-between rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                        <span>Code <strong>{{ summary.coupon }}</strong> applied</span>
                        <button class="text-emerald-700 underline" @click="removePromo">Remove</button>
                    </div>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between"><dt class="text-ink/60">Subtotal</dt><dd class="font-medium">{{ money(summary.subtotal) }}</dd></div>
                        <div v-if="summary.discount > 0" class="flex justify-between text-emerald-700"><dt>Discount ({{ summary.coupon }})</dt><dd class="font-medium">−{{ money(summary.discount) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-ink/60">Shipping</dt><dd class="font-medium">{{ summary.shipping ? money(summary.shipping) : 'FREE' }}</dd></div>
                        <div class="flex justify-between border-t border-paper-300 pt-2 text-base font-semibold"><dt>Total</dt><dd>{{ money(summary.total) }}</dd></div>
                    </dl>
                    <Link href="/checkout" class="mt-5 block rounded-full bg-brand-600 px-6 py-3.5 text-center font-semibold text-white transition hover:bg-brand-700">Proceed to checkout</Link>
                    <Link href="/" class="mt-3 block text-center text-sm text-ink/55 transition hover:text-ink">Continue shopping</Link>
                </aside>
            </div>

            <!-- req 15: nudge toward free shipping -->
            <section v-if="recommended.length" class="mt-16">
                <h2 class="font-display text-2xl font-semibold tracking-tight">{{ summary.qualifies ? 'You may also like' : 'Reach free shipping with' }}</h2>
                <div class="mt-6 grid grid-cols-2 gap-5 md:grid-cols-4">
                    <Link v-for="p in recommended" :key="p.slug" :href="`/product/${p.slug}`" class="group overflow-hidden rounded-2xl border border-paper-300 bg-white transition hover:-translate-y-1 hover:shadow-lg">
                        <div class="aspect-square overflow-hidden bg-paper-200">
                            <img v-if="p.image" :src="p.image" :alt="p.name" class="h-full w-full object-cover transition duration-500 group-hover:scale-105" />
                        </div>
                        <div class="p-3">
                            <p class="text-[11px] font-semibold uppercase tracking-widest text-brand-700/70">{{ p.category }}</p>
                            <p class="font-display text-sm font-semibold text-ink">{{ p.name }}</p>
                            <p class="text-xs text-ink/55">From {{ money(p.fromPrice) }}</p>
                        </div>
                    </Link>
                </div>
            </section>
        </div>
    </StoreLayout>
</template>
