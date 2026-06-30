<script setup>
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({ email: '', password: '' });
const submit = () => form.post('/admin/login');
</script>

<template>
    <Head title="Admin sign in" />
    <div class="flex min-h-screen items-center justify-center bg-navy px-4">
        <div class="w-full max-w-sm">
            <div class="mb-6 text-center">
                <span class="font-display text-2xl font-bold text-white">runmyprint</span>
                <span class="ml-1.5 rounded bg-lime-accent px-1.5 py-0.5 text-[11px] font-bold uppercase tracking-wide text-navy">Admin</span>
            </div>
            <form class="space-y-4 rounded-2xl bg-white p-7 shadow-2xl shadow-black/30" @submit.prevent="submit">
                <h1 class="font-display text-lg font-semibold text-ink">Sign in to the dashboard</h1>
                <div>
                    <label class="mb-1 block text-sm font-medium text-ink/70">Email</label>
                    <input v-model="form.email" type="email" autocomplete="username" required class="w-full border border-ink/20 bg-white px-3 py-2.5 text-sm focus:border-brand-600 focus:outline-none" />
                    <p v-if="form.errors.email" class="mt-1.5 text-xs font-medium text-red-600">{{ form.errors.email }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-ink/70">Password</label>
                    <input v-model="form.password" type="password" autocomplete="current-password" required class="w-full border border-ink/20 bg-white px-3 py-2.5 text-sm focus:border-brand-600 focus:outline-none" />
                </div>
                <button :disabled="form.processing" class="w-full bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-700 disabled:opacity-60">
                    {{ form.processing ? 'Signing in…' : 'Sign in' }}
                </button>
            </form>
        </div>
    </div>
</template>
