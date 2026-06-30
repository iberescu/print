<script setup>
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';

defineProps({ surfaces: { type: Array, default: () => [] } });

const creating = ref(false);
const form = useForm({ name: '' });
const create = () => form.post('/admin/surfaces', { onSuccess: () => { creating.value = false; form.reset(); } });
</script>

<template>
    <Head title="Surfaces" />
    <AdminLayout title="Surfaces">
        <template #actions>
            <button class="rounded-full bg-brand-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-700" @click="creating = !creating">+ New surface</button>
        </template>

        <p class="mb-4 max-w-2xl text-sm text-ink/55">Surfaces define the print geometry — size, bleed, safe area, no-print zones and fold lines — shown to customers in the online designer. Assign one to a product (or a Format value) in the product editor.</p>

        <form v-if="creating" class="mb-4 flex flex-wrap items-end gap-3 rounded-2xl border border-paper-300 bg-white p-4 shadow-sm" @submit.prevent="create">
            <div class="flex-1">
                <label class="mb-1 block text-xs font-medium text-ink/55">Surface name</label>
                <input v-model="form.name" required placeholder="e.g. A4 Flyer / Folded Business Card" class="w-full border border-ink/20 px-3 py-2 text-sm focus:border-brand-600 focus:outline-none" />
                <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
            </div>
            <button :disabled="form.processing" class="rounded-full bg-brand-600 px-5 py-2 text-sm font-semibold text-white hover:bg-brand-700 disabled:opacity-60">Create &amp; edit</button>
        </form>

        <div class="overflow-hidden rounded-2xl border border-paper-300 bg-white shadow-sm">
            <table class="w-full text-sm">
                <thead class="bg-paper-200 text-left text-xs font-semibold uppercase tracking-wide text-ink/50">
                    <tr><th class="px-5 py-3">Surface</th><th class="px-5 py-3">Size</th><th class="px-5 py-3">Bleed</th><th class="px-5 py-3">Safety</th><th class="hidden px-5 py-3 sm:table-cell">No-print</th><th class="hidden px-5 py-3 sm:table-cell">Folds</th><th class="px-5 py-3"></th></tr>
                </thead>
                <tbody class="divide-y divide-paper-200">
                    <tr v-for="s in surfaces" :key="s.slug" class="transition hover:bg-paper-200/60">
                        <td class="px-5 py-3"><p class="font-medium text-ink">{{ s.name }}</p><p class="text-xs text-ink/45">{{ s.slug }}</p></td>
                        <td class="px-5 py-3 text-ink/70">{{ s.size }}</td>
                        <td class="px-5 py-3 text-ink/70">{{ s.bleed }} {{ s.unit }}</td>
                        <td class="px-5 py-3 text-ink/70">{{ s.safety }} {{ s.unit }}</td>
                        <td class="hidden px-5 py-3 text-ink/70 sm:table-cell">{{ s.noPrint }}</td>
                        <td class="hidden px-5 py-3 text-ink/70 sm:table-cell">{{ s.fold }}</td>
                        <td class="px-5 py-3 text-right"><Link :href="`/admin/surfaces/${s.slug}/edit`" class="font-medium text-brand-700 hover:underline">Edit</Link></td>
                    </tr>
                    <tr v-if="!surfaces.length"><td colspan="7" class="px-5 py-12 text-center text-ink/50">No surfaces yet — create one to define print geometry.</td></tr>
                </tbody>
            </table>
        </div>
    </AdminLayout>
</template>
