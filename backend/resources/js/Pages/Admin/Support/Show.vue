<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';

const props = defineProps({ ticket: { type: Object, required: true } });

const form = useForm({ body: '' });
const submit = () => form.post(`/admin/support/${props.ticket.id}/reply`, {
    preserveScroll: true,
    onSuccess: () => form.reset(),
});

const senderMeta = {
    customer: { label: 'Customer', box: 'border-paper-300 bg-white', tag: 'text-ink/45' },
    ai:       { label: 'AI assistant', box: 'border-paper-300 bg-paper-200/70', tag: 'text-ink/45' },
    admin:    { label: 'RunMyPrint team', box: 'border-brand-200 bg-brand-50', tag: 'text-brand-700' },
};
</script>

<template>
    <Head :title="`Ticket #${ticket.id}`" />
    <AdminLayout :title="`Ticket #${ticket.id}`">
        <div class="mb-5 flex flex-wrap items-center gap-3">
            <Link href="/admin/support" class="text-sm text-ink/55 hover:text-ink">← All tickets</Link>
            <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold"
                  :class="ticket.status === 'needs_human' ? 'bg-red-100 text-red-700' : ticket.status === 'answered' ? 'bg-brand-50 text-brand-700' : 'bg-emerald-100 text-emerald-700'">
                {{ ticket.status === 'needs_human' ? 'Needs human' : ticket.status === 'answered' ? 'Answered' : 'AI answered' }}
            </span>
            <span class="text-sm text-ink/55">{{ ticket.customer }} · opened {{ ticket.created }}</span>
        </div>

        <div class="max-w-3xl space-y-3">
            <div v-for="m in ticket.messages" :key="m.id" class="rounded-2xl border p-4" :class="(senderMeta[m.sender] || senderMeta.customer).box">
                <p class="mb-1 text-[11px] font-semibold uppercase tracking-wider" :class="(senderMeta[m.sender] || senderMeta.customer).tag">
                    {{ (senderMeta[m.sender] || senderMeta.customer).label }} · {{ m.at }}
                </p>
                <p class="whitespace-pre-line text-sm leading-relaxed text-ink/85">{{ m.body }}</p>
            </div>

            <form class="rounded-2xl border border-paper-300 bg-white p-4 shadow-sm" @submit.prevent="submit">
                <label class="text-xs font-semibold uppercase tracking-wide text-ink/50">Reply as RunMyPrint team</label>
                <textarea v-model="form.body" rows="4" required
                          class="mt-2 w-full rounded-xl border border-paper-300 bg-paper-200/40 p-3 text-sm focus:border-brand-400 focus:outline-none"
                          placeholder="Write your answer — the customer sees it instantly in their chat."></textarea>
                <p v-if="form.errors.body" class="mt-1 text-xs text-red-600">{{ form.errors.body }}</p>
                <div class="mt-3 flex justify-end">
                    <button type="submit" :disabled="form.processing" class="rounded-full bg-brand-600 px-6 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-700 disabled:opacity-50">
                        {{ form.processing ? 'Sending…' : 'Send reply' }}
                    </button>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
