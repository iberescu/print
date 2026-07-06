<script setup>
import { ref, computed, nextTick, onBeforeUnmount } from 'vue';

// Zendesk-style support bubble. AI (Gemini flash) answers first; when it
// punts, the ticket turns red in the admin inbox and a human replies here —
// the panel polls while open, so later answers stream into the same thread.
const open = ref(false);
const draft = ref('');
const busy = ref(false);
const status = ref(null);
const messages = ref([]);
const thread = ref(null);
let timer = null;
let loaded = false;

const xsrf = () => decodeURIComponent(
    (document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/) || [])[1] || ''
);

// waiting on the assistant once the customer had the last word
const typing = computed(() => {
    const last = messages.value[messages.value.length - 1];
    return !!last && last.sender === 'customer' && status.value !== 'needs_human';
});

async function refresh() {
    try {
        const r = await fetch('/support/messages', { headers: { Accept: 'application/json' } });
        if (!r.ok) return;
        const d = await r.json();
        const grew = d.messages.length > messages.value.length;
        status.value = d.status;
        messages.value = d.messages;
        if (grew) scrollDown();
    } catch (e) { /* poll again later */ }
}

function toggle() {
    open.value = !open.value;
    if (open.value) {
        if (!loaded) { loaded = true; refresh(); }
        timer = setInterval(refresh, 3000);
        scrollDown();
    } else if (timer) {
        clearInterval(timer);
        timer = null;
    }
}

async function send() {
    const body = draft.value.trim();
    if (!body || busy.value) return;
    busy.value = true;
    draft.value = '';
    try {
        const r = await fetch('/support', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': xsrf() },
            body: JSON.stringify({ body }),
        });
        if (r.ok) {
            const d = await r.json();
            status.value = d.status;
            messages.value = d.messages;
            scrollDown();
        } else {
            draft.value = body; // let them retry
        }
    } catch (e) {
        draft.value = body;
    } finally {
        busy.value = false;
    }
}

function scrollDown() {
    nextTick(() => { if (thread.value) thread.value.scrollTop = thread.value.scrollHeight; });
}

onBeforeUnmount(() => { if (timer) clearInterval(timer); });
</script>

<template>
    <!-- panel -->
    <transition
        enter-active-class="transition duration-200 ease-out" enter-from-class="translate-y-3 opacity-0" enter-to-class="translate-y-0 opacity-100"
        leave-active-class="transition duration-150 ease-in" leave-from-class="translate-y-0 opacity-100" leave-to-class="translate-y-3 opacity-0"
    >
        <div v-if="open" class="fixed bottom-24 right-4 z-50 flex w-[min(94vw,380px)] flex-col overflow-hidden rounded-2xl border border-paper-300 bg-white shadow-2xl shadow-navy/30 sm:right-6" style="height: min(70vh, 560px)">
            <!-- header -->
            <div class="relative overflow-hidden bg-navy px-5 py-4 text-white">
                <svg class="pointer-events-none absolute -right-6 -top-8 h-28 w-28 text-brand-blue opacity-25" viewBox="0 0 96 96" fill="none" aria-hidden="true">
                    <circle cx="48" cy="48" r="28" stroke="currentColor" stroke-width="1.5" />
                    <circle cx="48" cy="48" r="40" stroke="currentColor" stroke-dasharray="3 5" />
                </svg>
                <p class="font-display text-base font-semibold">RunMyPrint support</p>
                <p class="mt-0.5 flex items-center gap-1.5 text-xs text-white/65">
                    <span class="inline-block h-2 w-2 rounded-full bg-lime-accent"></span>
                    We usually reply in under a minute
                </p>
            </div>

            <!-- thread -->
            <div ref="thread" class="flex-1 space-y-3 overflow-y-auto bg-paper-200/60 p-4">
                <div v-if="!messages.length" class="rounded-xl border border-paper-300 bg-white p-3.5 text-sm text-ink/70">
                    👋 Hi! Ask us anything about products, pricing, shipping or your design — we're happy to help.
                </div>

                <div v-for="m in messages" :key="m.id" class="flex" :class="m.sender === 'customer' ? 'justify-end' : 'justify-start'">
                    <div class="max-w-[85%] rounded-xl px-3.5 py-2.5 text-sm leading-relaxed" :class="m.sender === 'customer' ? 'bg-brand-600 text-white' : 'border border-paper-300 bg-white text-ink/85'">
                        <p v-if="m.sender !== 'customer'" class="mb-0.5 text-[10px] font-semibold uppercase tracking-wider" :class="m.sender === 'admin' ? 'text-brand-700' : 'text-ink/40'">
                            {{ m.sender === 'admin' ? 'RunMyPrint team' : 'Assistant' }}
                        </p>
                        <p class="whitespace-pre-line">{{ m.body }}</p>
                        <p class="mt-1 text-right text-[10px] opacity-50">{{ m.at }}</p>
                    </div>
                </div>

                <!-- assistant typing -->
                <div v-if="typing" class="flex justify-start">
                    <div class="flex items-center gap-1.5 rounded-xl border border-paper-300 bg-white px-4 py-3">
                        <span v-for="i in 3" :key="i" class="h-1.5 w-1.5 animate-bounce rounded-full bg-ink/30" :style="{ animationDelay: `${i * 0.15}s` }"></span>
                    </div>
                </div>

                <p v-if="status === 'needs_human'" class="rounded-xl bg-brand-50 px-3.5 py-2.5 text-xs text-brand-700">
                    Your question is with our team — replies will appear right here. Feel free to close this window and check back.
                </p>
            </div>

            <!-- composer -->
            <form class="flex items-end gap-2 border-t border-paper-300 bg-white p-3" @submit.prevent="send">
                <textarea
                    v-model="draft" rows="1" placeholder="Type your question…"
                    class="max-h-24 min-h-[42px] flex-1 resize-none rounded-xl border border-paper-300 bg-paper-200/50 px-3.5 py-2.5 text-sm focus:border-brand-400 focus:outline-none"
                    @keydown.enter.exact.prevent="send"
                ></textarea>
                <button type="submit" :disabled="busy || !draft.trim()" class="grid h-[42px] w-[42px] shrink-0 place-items-center rounded-xl bg-brand-600 text-white transition hover:bg-brand-700 disabled:opacity-40" aria-label="Send">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4.5 12 20 4.5 15.5 20l-3.4-6.1L4.5 12z" stroke-linejoin="round" /></svg>
                </button>
            </form>
        </div>
    </transition>

    <!-- bubble -->
    <button
        class="fixed bottom-5 right-4 z-50 grid h-14 w-14 place-items-center text-white shadow-xl shadow-navy/40 transition hover:scale-105 sm:right-6"
        :aria-label="open ? 'Close support chat' : 'Open support chat'"
        @click="toggle"
    >
        <svg class="absolute inset-0 h-full w-full" viewBox="0 0 56 56" aria-hidden="true">
            <circle cx="28" cy="28" r="27" fill="#2b3b55" />
            <circle cx="28" cy="28" r="27" fill="none" stroke="#398aff" stroke-opacity="0.55" stroke-width="1.5" />
        </svg>
        <svg v-if="!open" class="relative h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path d="M21 12a8 8 0 0 1-8 8H5.5L3 22V12a8 8 0 0 1 8-8h2a8 8 0 0 1 8 8z" stroke-linejoin="round" />
            <path d="M8.5 11h.01M12 11h.01M15.5 11h.01" stroke-linecap="round" stroke-width="2.4" />
        </svg>
        <svg v-else class="relative h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path d="m6 6 12 12M18 6 6 18" stroke-linecap="round" />
        </svg>
    </button>
</template>
