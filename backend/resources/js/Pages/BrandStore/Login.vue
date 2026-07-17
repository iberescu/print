<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    company: { type: String, required: true },
    domain: { type: String, required: true },
    logo: { type: String, default: null },
    sent: { type: Boolean, default: false },
});

const error = computed(() => usePage().props.flash?.error ?? null);
const form = useForm({ email: '' });
const submit = () => form.post('/store-login');
</script>

<template>
    <Head :title="`${company} — Private Brand Store`" />
    <div class="grid min-h-screen place-items-center bg-paper px-4">
        <div class="w-full max-w-md">
            <div class="rounded-3xl border border-paper-300 bg-white p-8 shadow-sm sm:p-10">
                <img v-if="logo" :src="logo" :alt="company" class="mx-auto mb-6 max-h-16 w-auto max-w-[220px] object-contain" />
                <p class="text-center text-xs font-semibold uppercase tracking-widest text-ink/45">🔒 Private Brand Store</p>
                <h1 class="mt-2 text-center font-display text-2xl font-bold text-ink">{{ company }}</h1>
                <p class="mt-3 text-center text-sm text-ink/60">
                    Ordering is reserved for authorized {{ company }} employees. Enter your
                    <span class="font-semibold text-ink">@{{ domain }}</span> work email and we'll send you a sign-in link to check out.
                </p>

                <div v-if="sent" class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                    ✓ If that address belongs to {{ company }}, a sign-in link is on its way. It expires in 30 minutes.
                </div>
                <p v-if="error" class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">{{ error }}</p>

                <form class="mt-6" @submit.prevent="submit">
                    <input v-model="form.email" type="email" required :placeholder="`you@${domain}`"
                           class="w-full rounded-full border border-paper-300 bg-paper-200/40 px-5 py-3 text-sm focus:border-brand-400 focus:outline-none" />
                    <p v-if="form.errors.email" class="mt-1 px-2 text-xs text-red-600">{{ form.errors.email }}</p>
                    <button type="submit" :disabled="form.processing"
                            class="mt-3 w-full rounded-full bg-navy px-6 py-3 font-semibold text-white transition hover:opacity-90 disabled:opacity-50">
                        {{ form.processing ? 'Sending…' : 'Email me a sign-in link' }}
                    </button>
                </form>
            </div>
            <p class="mt-5 text-center text-sm text-ink/50">
                Not a {{ company }} employee? <a href="https://www.runmyprint.com" class="font-medium text-ink/70 underline underline-offset-2 hover:text-ink">Visit the main shop</a>
            </p>
        </div>
    </div>
</template>
