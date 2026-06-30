<script setup>
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import StoreLayout from '../Layouts/StoreLayout.vue';

const props = defineProps({
    product: { type: Object, required: true },
    category: { type: Object, default: () => ({}) },
    templates: { type: Array, default: () => [] },
    canvas: { type: Object, default: () => ({}) },
    selection: { type: Object, default: () => ({}) },
});

// Frame the previews in the product's trim aspect (BC 1.75:1, A4 portrait, …).
const aspect = computed(() => (props.canvas?.trimW && props.canvas?.trimH ? props.canvas.trimW / props.canvas.trimH : 1.75));

function openEditor(templateRef = null) {
    router.get(`/design/${props.product.slug}`, {
        mode: 'design',
        qty: props.selection?.quantityId ?? undefined,
        opts: props.selection?.optionValueIds ?? [],
        ...(templateRef ? { template: templateRef } : {}),
    });
}
</script>

<template>
    <Head :title="`Templates — ${product.name}`" />
    <StoreLayout>
        <div class="mx-auto max-w-7xl px-6 py-8 sm:py-10">
            <nav class="text-sm text-ink/50">
                <Link href="/" class="hover:text-ink">Home</Link>
                <span class="mx-1.5">/</span>
                <Link :href="`/category/${category.slug}`" class="hover:text-ink">{{ category.name }}</Link>
                <span class="mx-1.5">/</span>
                <Link :href="`/product/${product.slug}`" class="hover:text-ink">{{ product.name }}</Link>
                <span class="mx-1.5">/</span>
                <span class="text-ink/80">Templates</span>
            </nav>

            <div class="mt-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-widest text-brand-700/70">{{ product.name }}</p>
                    <h1 class="mt-1.5 font-display text-3xl font-semibold tracking-tight text-ink sm:text-4xl">Choose a template</h1>
                    <p class="mt-2 text-ink/60">
                        Pick a starting point — you can change every detail in the editor.
                        <span v-if="canvas.label" class="ml-1 text-ink/45">Format: {{ canvas.label }}.</span>
                    </p>
                </div>
                <button type="button" @click="openEditor()"
                        class="inline-flex items-center justify-center gap-2 self-start rounded-full border border-ink/20 bg-white px-6 py-3 text-sm font-semibold text-ink transition hover:border-ink/40 sm:self-auto">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M12 5v14M5 12h14" stroke-linecap="round" /></svg>
                    Start from scratch
                </button>
            </div>

            <div v-if="templates.length" class="mt-8 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                <button v-for="t in templates" :key="t.ref" type="button" @click="openEditor(t.ref)"
                        class="group flex flex-col overflow-hidden rounded-xl border border-paper-300 bg-white text-left shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg hover:ring-2 hover:ring-brand-600">
                    <div class="relative w-full overflow-hidden bg-paper-200" :style="{ aspectRatio: aspect }">
                        <img v-if="t.preview" :src="t.preview" :alt="t.name" loading="lazy" class="absolute inset-0 h-full w-full object-cover transition duration-300 group-hover:scale-105" />
                        <div v-else class="grid h-full w-full place-items-center text-sm text-ink/30">No preview</div>
                    </div>
                    <div class="flex items-center justify-between gap-2 px-3 py-2.5">
                        <p class="truncate text-xs font-medium text-ink/70">{{ t.name }}</p>
                        <span class="shrink-0 text-xs font-semibold text-brand-700 opacity-0 transition group-hover:opacity-100">Use →</span>
                    </div>
                </button>
            </div>
            <div v-else class="mt-10 rounded-2xl border border-dashed border-paper-300 bg-paper-200 p-12 text-center">
                <p class="text-ink/60">No ready-made templates for this product yet.</p>
                <button type="button" @click="openEditor()" class="mt-4 rounded-full bg-brand-600 px-6 py-3 text-sm font-semibold text-white transition hover:bg-brand-700">Design from scratch</button>
            </div>
        </div>
    </StoreLayout>
</template>
