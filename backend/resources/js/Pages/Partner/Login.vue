<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import StoreLayout from '../../Layouts/StoreLayout.vue';

const form = useForm({ key: '' });
const login = () => form.post('/partner');
</script>

<template>
    <Head title="Partner dashboard" />
    <StoreLayout>
        <div class="mx-auto max-w-md px-6 py-16">
            <p class="text-sm font-semibold uppercase tracking-widest text-brand-600">Partner program</p>
            <h1 class="mt-1 font-display text-3xl font-bold tracking-tight text-ink">Your dashboard</h1>
            <p class="mt-2 text-ink/60">Sign in with your widget key — it's in your approval email. Impressions, earnings and payouts, live.</p>

            <form class="mt-8 space-y-4" @submit.prevent="login">
                <div>
                    <label class="text-xs font-semibold uppercase tracking-wide text-ink/55">Widget key</label>
                    <input v-model="form.key" required type="password" autocomplete="off" placeholder="Paste your key"
                           class="mt-1.5 w-full rounded-xl border border-paper-300 bg-white px-3.5 py-2.5 font-mono text-sm focus:border-brand-400 focus:outline-none" />
                    <p v-if="form.errors.key" class="mt-1 text-xs text-red-600">{{ form.errors.key }}</p>
                </div>
                <button :disabled="form.processing" class="w-full rounded-full bg-brand-600 px-6 py-3 font-semibold text-white transition hover:bg-brand-700 disabled:opacity-60">Open dashboard</button>
            </form>
            <p class="mt-6 text-center text-sm text-ink/55">Not a partner yet? <a href="/affiliates" class="text-brand-700 hover:underline">Apply here</a>.</p>
        </div>
    </StoreLayout>
</template>
