<script setup>
import LegalLayout from '../../Layouts/LegalLayout.vue';

defineProps({ threshold: { type: Number, default: 50 } });
const money = (n) => '$' + Number(n).toFixed(0);
</script>

<template>
    <LegalLayout title="Shipping & Delivery">
        <h2>Production time</h2>
        <p>Orders are custom-produced after you approve your design. Standard production takes <strong>2–4 business days</strong>; many products offer a 2-day production option at checkout. Production time is separate from transit time.</p>

        <h2>Free shipping</h2>
        <p>Orders with a subtotal of <strong>{{ money(threshold) }}</strong> or more ship <strong>free</strong> (Standard). Shipping is charged per product below that threshold. For orders below {{ money(threshold) }}, a flat shipping rate is calculated and shown at checkout before you pay.</p>

        <h2>Delivery estimates</h2>
        <p>Once produced, most orders are delivered within 3–7 business days by our carriers, depending on your location and the shipping method selected. Estimated delivery windows are shown at checkout and are not guaranteed dates.</p>

        <h2>Tracking</h2>
        <p>When your order ships you'll receive a confirmation with tracking (where available) at the email on your order.</p>

        <h2>Shipping address</h2>
        <p>Please double-check your shipping address at checkout. We're not responsible for orders delayed or lost due to an incorrect or incomplete address. If a package is returned to us as undeliverable, we'll contact you to arrange re-shipment.</p>

        <h2>Where we ship</h2>
        <p>We currently ship within the United States. For other destinations, contact us before ordering.</p>

        <h2>Delays</h2>
        <p>Carrier delays, weather and holidays can affect delivery. If your order is significantly late, reach out and we'll help track it down.</p>
    </LegalLayout>
</template>
