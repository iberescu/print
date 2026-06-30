<script setup>
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';

const props = defineProps({
    products: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
});

const money = (n) => '$' + Number(n).toFixed(2);

const creating = ref(false);
const form = useForm({ name: '', categoryId: props.categories[0]?.id ?? null });
const create = () => form.post('/admin/products', { onSuccess: () => { creating.value = false; form.reset(); } });
</script>

<template>
    <Head title="Products" />
    <AdminLayout title="Products">
        <template #actions>
            <button class="rounded-full bg-brand-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-700" @click="creating = !creating">+ New product</button>
        </template>

        <form v-if="creating" class="mb-4 flex flex-wrap items-end gap-3 rounded-2xl border border-paper-300 bg-white p-4 shadow-sm" @submit.prevent="create">
            <div class="flex-1">
                <label class="mb-1 block text-xs font-medium text-ink/55">Product name</label>
                <input v-model="form.name" required placeholder="e.g. Premium Flyers" class="w-full border border-ink/20 px-3 py-2 text-sm focus:border-brand-600 focus:outline-none" />
                <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-ink/55">Category</label>
                <select v-model="form.categoryId" class="border border-ink/20 bg-white px-3 py-2 text-sm focus:border-brand-600 focus:outline-none">
                    <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
            </div>
            <button :disabled="form.processing" class="rounded-full bg-brand-600 px-5 py-2 text-sm font-semibold text-white hover:bg-brand-700 disabled:opacity-60">Create &amp; edit</button>
        </form>

        <div class="overflow-hidden rounded-2xl border border-paper-300 bg-white shadow-sm">
            <table class="w-full text-sm">
                <thead class="bg-paper-200 text-left text-xs font-semibold uppercase tracking-wide text-ink/50">
                    <tr><th class="px-5 py-3">Product</th><th class="hidden px-5 py-3 sm:table-cell">Category</th><th class="px-5 py-3">From</th><th class="hidden px-5 py-3 md:table-cell">Options</th><th class="hidden px-5 py-3 md:table-cell">Tiers</th><th class="px-5 py-3">Status</th><th class="px-5 py-3"></th></tr>
                </thead>
                <tbody class="divide-y divide-paper-200">
                    <tr v-for="p in products" :key="p.id" class="transition hover:bg-paper-200/60">
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-3">
                                <div class="h-10 w-10 shrink-0 overflow-hidden rounded-lg border border-paper-300 bg-paper-200">
                                    <img v-if="p.image" :src="p.image" :alt="p.name" class="h-full w-full object-cover" />
                                </div>
                                <div><p class="font-medium text-ink">{{ p.name }}</p><p class="text-xs text-ink/45">{{ p.slug }}</p></div>
                            </div>
                        </td>
                        <td class="hidden px-5 py-3 text-ink/70 sm:table-cell">{{ p.category }}</td>
                        <td class="px-5 py-3 font-medium text-ink">{{ money(p.fromPrice) }}</td>
                        <td class="hidden px-5 py-3 text-ink/70 md:table-cell">{{ p.options }}</td>
                        <td class="hidden px-5 py-3 text-ink/70 md:table-cell">{{ p.tiers }}</td>
                        <td class="px-5 py-3">
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold" :class="p.active ? 'bg-emerald-100 text-emerald-700' : 'bg-paper-300 text-ink/55'">{{ p.active ? 'Active' : 'Draft' }}</span>
                        </td>
                        <td class="px-5 py-3 text-right"><Link :href="`/admin/products/${p.slug}/edit`" class="font-medium text-brand-700 hover:underline">Edit</Link></td>
                    </tr>
                    <tr v-if="!products.length"><td colspan="7" class="px-5 py-12 text-center text-ink/50">No products yet.</td></tr>
                </tbody>
            </table>
        </div>
    </AdminLayout>
</template>
