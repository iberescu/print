<script setup>
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';

const props = defineProps({ ticket: { type: Object, required: true } });

const form = useForm({ body: '' });
const submit = () => form.post(`/admin/support/${props.ticket.id}/reply`, {
    preserveScroll: true,
    onSuccess: () => form.reset(),
});

// Synchronous Gemini re-run — takes a few seconds, keep the button honest.
const retrying = ref(false);
const retryAi = () => {
    retrying.value = true;
    router.post(`/admin/support/${props.ticket.id}/retry-ai`, {}, {
        preserveScroll: true,
        onFinish: () => { retrying.value = false; },
    });
};

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
            <span class="rounded-full bg-paper-200 px-2.5 py-0.5 text-xs font-semibold text-ink/60">
                {{ ticket.channel === 'email' ? '✉️ Email' : '💬 Chat' }}
            </span>
            <span class="text-sm text-ink/55">{{ ticket.customer }} · opened {{ ticket.created }}</span>
            <button type="button" :disabled="retrying" class="ml-auto rounded-full px-4 py-1.5 text-xs font-semibold text-brand-700 ring-1 ring-brand-300 transition hover:bg-brand-50 disabled:opacity-50"
                    @click="retryAi">
                {{ retrying ? 'Asking AI…' : '↻ Try AI again' }}
            </button>
        </div>
        <p v-if="ticket.subject" class="-mt-2 mb-4 text-base font-semibold text-ink/85">{{ ticket.subject }}</p>

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
                          :placeholder="ticket.channel === 'email'
                              ? `Write your answer — it's emailed to ${ticket.customer} (replies thread back here).`
                              : 'Write your answer — the customer sees it instantly in their chat.'"></textarea>
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
