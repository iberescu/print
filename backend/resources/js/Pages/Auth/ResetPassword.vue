<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import StoreLayout from '../../Layouts/StoreLayout.vue';

const props = defineProps({
    token: { type: String, required: true },
    email: { type: String, default: '' },
});

const form = useForm({ token: props.token, email: props.email, password: '', password_confirmation: '' });
const save = () => form.post('/reset-password');
</script>

<template>
    <Head title="Choose a new password" />
    <StoreLayout>
        <div class="mx-auto max-w-md px-6 py-16">
            <h1 class="font-display text-3xl font-bold tracking-tight text-ink">Choose a new password</h1>

            <form class="mt-8 space-y-4" @submit.prevent="save">
                <div>
                    <label class="text-xs font-semibold uppercase tracking-wide text-ink/55">Email</label>
                    <input v-model="form.email" required type="email" autocomplete="email"
                           class="mt-1.5 w-full rounded-xl border border-paper-300 bg-white px-3.5 py-2.5 text-sm focus:border-brand-400 focus:outline-none" />
                    <p v-if="form.errors.email" class="mt-1 text-xs text-red-600">{{ form.errors.email }}</p>
                </div>
                <div>
                    <label class="text-xs font-semibold uppercase tracking-wide text-ink/55">New password</label>
                    <input v-model="form.password" required type="password" autocomplete="new-password"
                           class="mt-1.5 w-full rounded-xl border border-paper-300 bg-white px-3.5 py-2.5 text-sm focus:border-brand-400 focus:outline-none" />
                    <p v-if="form.errors.password" class="mt-1 text-xs text-red-600">{{ form.errors.password }}</p>
                </div>
                <div>
                    <label class="text-xs font-semibold uppercase tracking-wide text-ink/55">Repeat new password</label>
                    <input v-model="form.password_confirmation" required type="password" autocomplete="new-password"
                           class="mt-1.5 w-full rounded-xl border border-paper-300 bg-white px-3.5 py-2.5 text-sm focus:border-brand-400 focus:outline-none" />
                </div>
                <button :disabled="form.processing" class="w-full rounded-full bg-brand-600 px-6 py-3 font-semibold text-white transition hover:bg-brand-700 disabled:opacity-60">
                    {{ form.processing ? 'Saving…' : 'Set new password' }}
                </button>
            </form>
        </div>
    </StoreLayout>
</template>
