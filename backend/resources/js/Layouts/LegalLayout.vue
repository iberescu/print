<script setup>
import { computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import StoreLayout from './StoreLayout.vue';

defineProps({ title: { type: String, required: true }, updated: { type: String, default: 'July 2026' } });

const company = computed(() => usePage().props.shop?.company ?? {});
</script>

<template>
    <Head :title="title" />
    <StoreLayout>
        <div class="mx-auto max-w-3xl px-6 py-10 sm:py-14">
            <h1 class="font-display text-3xl font-bold tracking-tight text-ink sm:text-4xl">{{ title }}</h1>
            <p class="mt-1.5 text-sm text-ink/45">Last updated {{ updated }}</p>
            <div class="legal mt-8 space-y-4 text-ink/70"><slot :company="company" /></div>
            <p class="mt-12 border-t border-paper-300 pt-6 text-sm text-ink/45">
                {{ company.name }} ({{ company.brand }}) · {{ company.address }} · <a :href="`mailto:${company.email}`" class="hover:text-brand-700">{{ company.email }}</a>
            </p>
        </div>
    </StoreLayout>
</template>

<style scoped>
.legal :deep(h2) { margin-top: 1.75rem; font-family: var(--font-display); font-size: 1.15rem; font-weight: 600; color: var(--color-ink); }
.legal :deep(p) { line-height: 1.7; }
.legal :deep(ul) { list-style: disc; padding-left: 1.35rem; }
.legal :deep(li) { margin-top: 0.35rem; line-height: 1.6; }
.legal :deep(a) { color: var(--color-brand-700); text-decoration: underline; text-underline-offset: 2px; }
.legal :deep(strong) { color: var(--color-ink); font-weight: 600; }
</style>
