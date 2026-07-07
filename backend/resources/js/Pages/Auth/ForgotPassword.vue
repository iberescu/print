<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import StoreLayout from '../../Layouts/StoreLayout.vue';

const form = useForm({ email: '' });
const send = () => form.post('/forgot-password');
</script>

<template>
    <Head title="Reset your password" />
    <StoreLayout>
        <div class="mx-auto max-w-md px-6 py-16">
            <h1 class="font-display text-3xl font-bold tracking-tight text-ink">Reset your password</h1>
            <p class="mt-2 text-ink/60">Enter your account email — we'll send a link that lets you choose a new password.</p>

            <form class="mt-8 space-y-4" @submit.prevent="send">
                <div>
                    <label class="text-xs font-semibold uppercase tracking-wide text-ink/55">Email</label>
                    <input v-model="form.email" required type="email" autocomplete="email"
                           class="mt-1.5 w-full rounded-xl border border-paper-300 bg-white px-3.5 py-2.5 text-sm focus:border-brand-400 focus:outline-none" />
                    <p v-if="form.errors.email" class="mt-1 text-xs text-red-600">{{ form.errors.email }}</p>
                </div>
                <button :disabled="form.processing" class="w-full rounded-full bg-brand-600 px-6 py-3 font-semibold text-white transition hover:bg-brand-700 disabled:opacity-60">
                    {{ form.processing ? 'Sending…' : 'Email me a reset link' }}
                </button>
            </form>
            <p class="mt-6 text-center text-sm text-ink/55"><Link href="/login" class="text-brand-700 hover:underline">← Back to sign in</Link></p>
        </div>
    </StoreLayout>
</template>
