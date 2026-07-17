<script setup>
import { onMounted } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import StoreLayout from '../Layouts/StoreLayout.vue';
import { money } from '../lib/format';
import { adsConversion } from '../lib/gads';
import { rtbConversion } from '../lib/rtb';

const props = defineProps({
    order: { type: Object, default: () => ({}) },
    rtb: { type: Object, default: null },
});

// Purchase conversion — Google dedupes repeats by transaction_id, so a
// refreshed thank-you page can't double count. RTB House likewise dedupes
// by conversionId (the order number).
onMounted(() => {
    if (props.order?.number) {
        adsConversion('purchase', { value: Number(props.order.total || 0), transaction_id: props.order.number });
    }
    if (props.rtb) {
        rtbConversion(props.rtb.offerIds, props.rtb.value, props.rtb.orderId);
    }
});
</script>

<template>
    <Head title="Order confirmed" />
    <StoreLayout>
        <div class="mx-auto max-w-2xl px-6 py-20 text-center">
            <div class="mx-auto grid h-16 w-16 place-items-center rounded-full bg-brand-50 text-brand-600">
                <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 13l4 4L19 7" stroke-linecap="round" stroke-linejoin="round" /></svg>
            </div>
            <h1 class="mt-6 font-display text-3xl font-semibold tracking-tight">Thank you — order confirmed!</h1>
            <p class="mt-3 text-ink/60">We've emailed a confirmation to <strong>{{ order.email }}</strong>.</p>

            <div class="mx-auto mt-8 max-w-sm rounded-2xl border border-paper-300 bg-white p-5 text-left">
                <div class="flex justify-between text-sm"><span class="text-ink/55">Order number</span><span class="font-semibold">{{ order.number }}</span></div>
                <div class="mt-2 flex justify-between text-sm"><span class="text-ink/55">Total</span><span class="font-semibold">{{ money(order.total) }}</span></div>
                <div class="mt-2 flex justify-between text-sm"><span class="text-ink/55">Status</span><span class="font-semibold capitalize text-brand-700">{{ order.status }}</span></div>
            </div>

            <Link href="/" class="mt-8 inline-block rounded-full bg-brand-600 px-6 py-3 font-semibold text-white transition hover:bg-brand-700">Continue shopping</Link>
        </div>
    </StoreLayout>
</template>
