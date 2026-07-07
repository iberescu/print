<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({ googleEnabled: { type: Boolean, default: false } });

const form = useForm({ email: '', password: '', remember: false });
const submit = () => form.post('/login');
</script>

<template>
    <Head title="Sign in" />
    <div class="flex min-h-screen flex-col items-center justify-center bg-paper-200 px-4 py-10">
        <Link href="/" class="mb-6"><img src="/storage/brand/logo.svg" alt="runmyprint" class="h-16 w-auto" /></Link>
        <div class="w-full max-w-sm rounded-2xl border border-paper-300 bg-white p-7 shadow-xl shadow-navy/5">
            <h1 class="font-display text-xl font-bold tracking-tight text-ink">Sign in</h1>
            <p class="mt-1 text-sm text-ink/55">Welcome back — sign in to check out.</p>

            <a v-if="googleEnabled" href="/auth/google" class="mt-5 flex w-full items-center justify-center gap-2.5 rounded-lg border border-ink/15 bg-white px-4 py-2.5 text-sm font-semibold text-ink transition hover:bg-paper-200">
                <svg class="h-5 w-5" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.27-4.74 3.27-8.1z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84A11 11 0 0 0 12 23z"/><path fill="#FBBC05" d="M5.84 14.1a6.6 6.6 0 0 1 0-4.2V7.06H2.18a11 11 0 0 0 0 9.88l3.66-2.84z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84C6.71 7.3 9.14 5.38 12 5.38z"/></svg>
                Continue with Google
            </a>

            <div v-if="googleEnabled" class="my-5 flex items-center gap-3 text-xs text-ink/40">
                <span class="h-px flex-1 bg-paper-300"></span>or<span class="h-px flex-1 bg-paper-300"></span>
            </div>

            <form class="space-y-4" @submit.prevent="submit">
                <div>
                    <label class="mb-1 block text-sm font-medium text-ink/70">Email</label>
                    <input v-model="form.email" type="email" autocomplete="username" required class="w-full border border-ink/20 px-3 py-2.5 text-sm focus:border-brand-600 focus:outline-none" />
                    <p v-if="form.errors.email" class="mt-1.5 text-xs font-medium text-red-600">{{ form.errors.email }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-ink/70">Password</label>
                    <input v-model="form.password" type="password" autocomplete="current-password" required class="w-full border border-ink/20 px-3 py-2.5 text-sm focus:border-brand-600 focus:outline-none" />
                </div>
                <label class="flex items-center gap-2 text-sm text-ink/60"><input v-model="form.remember" type="checkbox" class="h-4 w-4" /> Remember me</label>
                <button :disabled="form.processing" class="w-full bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-700 disabled:opacity-60">{{ form.processing ? 'Signing in…' : 'Sign in' }}</button>
                <p class="mt-3 text-center text-sm"><Link href="/forgot-password" class="text-ink/55 transition hover:text-brand-700">Forgot your password?</Link></p>
            </form>

            <p class="mt-5 text-center text-sm text-ink/55">New here? <Link href="/register" class="font-semibold text-brand-700 hover:underline">Create an account</Link></p>
        </div>
    </div>
</template>
