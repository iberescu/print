<script setup>
import LegalLayout from '../../Layouts/LegalLayout.vue';
</script>

<template>
    <LegalLayout title="Returns & Reprints" v-slot="{ company }">
        <p>Every order is backed by our quality guarantee. Because products are custom-printed to your approved design, they can't be resold and are <strong>not eligible for change-of-mind returns</strong> — but we'll always make it right if something is wrong on our end.</p>

        <h2>We'll reprint or refund if…</h2>
        <ul>
            <li>Your order arrives <strong>damaged</strong> or with a manufacturing defect;</li>
            <li>The printed product doesn't match the design you <strong>approved</strong>;</li>
            <li>You received the <strong>wrong item</strong> or quantity.</li>
        </ul>

        <h2>What isn't covered</h2>
        <ul>
            <li>Errors in the approved file (typos, low-resolution images, wrong colours in the artwork, or content placed outside the safe area);</li>
            <li>Minor colour, trimming or finishing variation inherent to printing;</li>
            <li>Delays caused by the carrier.</li>
        </ul>

        <h2>How to report an issue</h2>
        <p>Contact us within <strong>14 days</strong> of delivery at <a :href="`mailto:${company.email}`">{{ company.email }}</a> with your order number and a few photos of the issue. We review each case quickly.</p>

        <h2>Resolution</h2>
        <p>For approved claims we'll <strong>reprint the affected items at no charge</strong>, or issue a refund to your original payment method if you prefer. We may ask you to return or dispose of the defective items.</p>

        <h2>Cancellations</h2>
        <p>Orders can be cancelled for a full refund only before they enter production. Once production begins, an order can no longer be cancelled.</p>

        <p class="pt-2">Returns are handled by {{ company.name }}. This policy doesn't limit any rights you may have under applicable consumer law.</p>
    </LegalLayout>
</template>
