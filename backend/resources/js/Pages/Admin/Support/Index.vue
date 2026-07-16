<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';

defineProps({
    tickets: { type: Object, default: () => ({ data: [], links: [] }) },
    status: { type: String, default: null },
    counts: { type: Object, default: () => ({}) },
});

const statusMeta = {
    needs_human: { label: 'Needs human', class: 'bg-red-100 text-red-700' },
    ai:          { label: 'AI answered', class: 'bg-emerald-100 text-emerald-700' },
    answered:    { label: 'Answered', class: 'bg-brand-50 text-brand-700' },
    open:        { label: 'Open', class: 'bg-amber-100 text-amber-700' },
};
const tabs = [
    { key: null, label: 'All' },
    { key: 'needs_human', label: 'Needs human' },
    { key: 'ai', label: 'AI answered' },
    { key: 'answered', label: 'Answered' },
];
const tabHref = (key) => (key ? `/admin/support?status=${key}` : '/admin/support');
</script>

<template>
    <Head title="Support" />
    <AdminLayout title="Support">
        <div class="mb-4 flex flex-wrap gap-1.5">
            <Link v-for="t in tabs" :key="t.label" :href="tabHref(t.key)"
                  class="rounded-full px-4 py-1.5 text-sm font-medium transition"
                  :class="(status ?? null) === t.key ? 'bg-brand-600 text-white' : 'bg-white text-ink/70 ring-1 ring-paper-300 hover:bg-paper-200'">
                {{ t.label }}<span class="ml-1.5 text-xs opacity-70">{{ t.key ? (counts[t.key] ?? 0) : (counts.all ?? 0) }}</span>
            </Link>
        </div>

        <div class="overflow-hidden rounded-2xl border border-paper-300 bg-white shadow-sm">
            <table class="w-full text-sm">
                <thead class="bg-paper-200 text-left text-xs font-semibold uppercase tracking-wide text-ink/50">
                    <tr><th class="px-5 py-3">Ticket</th><th class="px-5 py-3">Customer</th><th class="px-5 py-3">Inquiry</th><th class="px-5 py-3">Status</th><th class="hidden px-5 py-3 sm:table-cell">Updated</th></tr>
                </thead>
                <tbody class="divide-y divide-paper-200">
                    <tr v-for="t in tickets.data" :key="t.id" class="transition hover:bg-paper-200/60" :class="t.status === 'needs_human' ? 'bg-red-50/60' : ''">
                        <td class="px-5 py-3"><Link :href="`/admin/support/${t.id}`" class="font-medium text-brand-700 hover:underline">#{{ t.id }}</Link></td>
                        <td class="px-5 py-3 text-ink/80">
                            <span class="mr-1" :title="t.channel === 'email' ? 'Email' : 'Chat'">{{ t.channel === 'email' ? '✉️' : '💬' }}</span>{{ t.customer }}
                        </td>
                        <td class="max-w-md px-5 py-3 text-ink/60">
                            <span v-if="t.subject" class="line-clamp-1 font-medium text-ink/80">{{ t.subject }}</span>
                            <span class="line-clamp-1">{{ t.excerpt }}</span><span class="text-xs text-ink/40">{{ t.count }} messages</span>
                        </td>
                        <td class="px-5 py-3"><span class="rounded-full px-2.5 py-0.5 text-xs font-semibold" :class="(statusMeta[t.status] || statusMeta.open).class">{{ (statusMeta[t.status] || statusMeta.open).label }}</span></td>
                        <td class="hidden px-5 py-3 text-xs text-ink/50 sm:table-cell">{{ t.updated }}</td>
                    </tr>
                    <tr v-if="!tickets.data.length"><td colspan="5" class="px-5 py-12 text-center text-ink/50">No tickets yet.</td></tr>
                </tbody>
            </table>
        </div>

        <div v-if="tickets.links && tickets.links.length > 3" class="mt-4 flex flex-wrap gap-1">
            <template v-for="(l, i) in tickets.links" :key="i">
                <Link v-if="l.url" :href="l.url" class="rounded-md px-3 py-1.5 text-sm transition" :class="l.active ? 'bg-brand-600 text-white' : 'bg-white text-ink/70 ring-1 ring-paper-300 hover:bg-paper-200'" v-html="l.label" />
                <span v-else class="rounded-md px-3 py-1.5 text-sm text-ink/30" v-html="l.label" />
            </template>
        </div>
    </AdminLayout>
</template>
