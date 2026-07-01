<script setup>
import LegalLayout from '../../Layouts/LegalLayout.vue';
</script>

<template>
    <LegalLayout title="Terms of Service" v-slot="{ company }">
        <p>These Terms of Service ("Terms") govern your use of {{ company.brand }} (the "Service"), operated by <strong>{{ company.name }}</strong>, a limited liability company registered in {{ company.state }}, United States, with a mailing address at {{ company.address }}. By placing an order or using the Service you agree to these Terms.</p>

        <h2>1. Orders &amp; payment</h2>
        <p>All orders are subject to acceptance. Prices are shown in US dollars and are payable in full at checkout. We may correct pricing errors and cancel affected orders with a full refund. Applicable taxes are added where required.</p>

        <h2>2. Your content &amp; licence</h2>
        <p>You retain ownership of the artwork, logos, text and images you upload or create ("Your Content"). You grant {{ company.name }} a limited licence to reproduce and print Your Content solely to fulfil your order. You represent that you own or have permission to use Your Content and that it does not infringe any third-party rights or applicable laws.</p>

        <h2>3. Proofs &amp; approval</h2>
        <p>Before checkout you are shown a preview and must approve your design. You are responsible for reviewing spelling, layout, colours and that important content sits within the safe area. Once approved, your design is sent to production as-is and we are not liable for errors contained in the approved file.</p>

        <h2>4. Colour &amp; production variance</h2>
        <p>Screens display colour differently from printed output. Minor variations in colour, cutting and finishing are inherent to printing and are not considered defects.</p>

        <h2>5. Cancellations &amp; changes</h2>
        <p>Because products are custom-made, orders can only be changed or cancelled before they enter production. See our <a href="/returns">Returns</a> policy for defective or incorrect items.</p>

        <h2>6. Prohibited content</h2>
        <p>You may not use the Service to produce material that is unlawful, infringing, hateful, or that violates the rights of others. We may refuse or cancel any order that breaches this section.</p>

        <h2>7. Limitation of liability</h2>
        <p>To the fullest extent permitted by law, {{ company.name }}'s total liability for any claim arising from an order is limited to the amount you paid for that order. We are not liable for indirect or consequential damages.</p>

        <h2>8. Governing law</h2>
        <p>These Terms are governed by the laws of the State of {{ company.state }}, without regard to its conflict-of-laws rules.</p>

        <h2>9. Contact</h2>
        <p>Questions about these Terms can be sent to <a :href="`mailto:${company.email}`">{{ company.email }}</a>.</p>
    </LegalLayout>
</template>
