<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    source: { type: String, default: 'footer' },
    cta: { type: String, default: 'Sign up' },
});

const email = ref('');
const done = ref(false);
const busy = ref(false);

const submit = () => {
    const e = email.value.trim();
    if (!e || busy.value) return;
    busy.value = true;
    router.post('/subscribe', { email: e, source: props.source }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => { done.value = true; email.value = ''; },
        onFinish: () => { busy.value = false; },
    });
};
</script>

<template>
    <p v-if="done" class="text-sm font-semibold text-brand-700">✓ You're on the list — check your inbox for your 20% off code.</p>
    <form v-else class="flex w-full max-w-md" @submit.prevent="submit">
        <input v-model="email" type="email" required placeholder="Enter your email" class="w-full rounded-none border border-ink/20 bg-white px-4 py-3 text-sm text-ink focus:border-brand-400 focus:outline-none" />
        <button :disabled="busy" class="shrink-0 bg-brand-600 px-6 py-3 text-sm font-semibold text-white transition hover:bg-brand-700 disabled:opacity-60">{{ busy ? '…' : cta }}</button>
    </form>
</template>
