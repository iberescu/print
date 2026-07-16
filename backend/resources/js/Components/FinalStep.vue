<script setup>
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import SmartImage from './SmartImage.vue';
import { money } from '../lib/format';

// Final step of the funnel: the design is approved and in the cart — the buyer
// can still change quantity and the options that don't touch the print surface
// (paper stock, finish, …). Every click re-quotes server-side; totals below are
// computed locally from the same inputs so the UI never waits on the network.
const props = defineProps({
    payload: { type: Object, required: true },
    summary: { type: Object, default: () => ({}) },
});

const groups = computed(() => props.payload.groups || []);
const quantities = computed(() => props.payload.quantities || []);
const line = computed(() => props.payload.line || {});

const qtyId = ref(props.payload.quantityId);
const chosen = ref({});
const busy = ref(false);

function syncFromServer() {
    qtyId.value = props.payload.quantityId;
    const selected = props.payload.optionValueIds || [];
    const map = {};
    groups.value.forEach((g) => {
        const v = g.values.find((x) => selected.includes(x.id))
            ?? g.values.find((x) => x.isDefault)
            ?? g.values[0];
        if (v) map[g.id] = v.id;
    });
    chosen.value = map;
}
syncFromServer();
// server truth wins after every re-quote (or if a submitted id was rejected)
watch(() => props.payload, syncFromServer);

function save() {
    busy.value = true;
    router.post('/upsell/finalize',
        { quantityId: qtyId.value, optionValueIds: Object.values(chosen.value) },
        { preserveScroll: true, preserveState: true, onFinish: () => (busy.value = false) });
}
function pickQty(q) {
    if (qtyId.value === q.id) return;
    qtyId.value = q.id;
    save();
}
function pick(g, v) {
    if (chosen.value[g.id] === v.id) return;
    chosen.value = { ...chosen.value, [g.id]: v.id };
    save();
}
// Grey the continue CTAs (with a small spinner) while the step advance is in
// flight — double-presses used to double-advance the wizard.
const advancing = ref(false);
function toCart() {
    if (advancing.value) return;
    advancing.value = true;
    router.post('/upsell/next', {}, { onFinish: () => (advancing.value = false) });
}

// ---- instant pricing (mirrors Pricing::quote server-side) ------------------
const optionDeltas = computed(() => {
    let sum = Number(props.payload.lockedDelta || 0);
    groups.value.forEach((g) => {
        const v = g.values.find((x) => x.id === chosen.value[g.id]);
        if (v) sum += Number(v.priceDelta);
    });
    return sum;
});
const tierTotal = (q) => Number(q.total) + optionDeltas.value;
const perUnit = (q) => (q.quantity ? tierTotal(q) / q.quantity : tierTotal(q));
const selectedTier = computed(() => quantities.value.find((q) => q.id === qtyId.value));
const total = computed(() => (selectedTier.value ? tierTotal(selectedTier.value) : Number(line.value.lineTotal)));
const totalPerUnit = computed(() => (selectedTier.value ? perUnit(selectedTier.value) : Number(line.value.unitPrice)));

// savings vs the smallest run — the classic "order more, pay less each" nudge
const baseline = computed(() => {
    const smallest = [...quantities.value].sort((a, b) => a.quantity - b.quantity)[0];
    return smallest ? perUnit(smallest) : 0;
});
const savings = (q) => (baseline.value > 0 ? 1 - perUnit(q) / baseline.value : 0);

const selectedLabel = (g) => g.values.find((v) => v.id === chosen.value[g.id])?.label || '—';
const attrLine = (v) => (v.attributes || [])
    .filter((a) => a.name || a.value)
    .slice(0, 2)
    .map((a) => [a.name, a.value].filter(Boolean).join(' '))
    .join(' · ');
</script>

<template>
    <div class="mt-8 grid items-start gap-8 lg:grid-cols-[1fr_360px] lg:gap-10">
        <!-- ============ choices ============ -->
        <div class="min-w-0 space-y-10">
            <!-- quantity -->
            <section v-if="quantities.length > 1">
                <div class="mb-3 flex items-baseline justify-between gap-4">
                    <h2 class="font-display text-lg font-semibold text-ink">Quantity</h2>
                    <p class="text-xs text-ink/50">Larger runs cost less per unit</p>
                </div>
                <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-3 xl:grid-cols-4">
                    <button
                        v-for="q in quantities" :key="q.id" type="button"
                        class="relative rounded-xl border p-3 text-left transition"
                        :class="qtyId === q.id ? 'border-brand-600 bg-brand-50 shadow-sm' : 'border-paper-300 bg-white hover:border-ink/25'"
                        @click="pickQty(q)"
                    >
                        <span v-if="savings(q) >= 0.05" class="absolute right-2 top-2 rounded-full bg-lime-accent px-1.5 py-0.5 text-[10px] font-bold text-navy">−{{ Math.round(savings(q) * 100) }}%</span>
                        <span class="block font-display text-xl font-semibold text-ink">{{ q.quantity }}</span>
                        <span class="block text-sm font-medium text-ink/80">{{ money(tierTotal(q)) }}</span>
                        <span class="block text-xs text-ink/45">{{ money(perUnit(q)) }} each</span>
                    </button>
                </div>
            </section>

            <!-- changeable options — material & friends, with generated previews -->
            <section v-for="g in groups" :key="g.id">
                <div class="mb-3 flex items-baseline justify-between gap-4">
                    <h2 class="font-display text-lg font-semibold text-ink">{{ g.name }}</h2>
                    <p class="truncate text-xs text-ink/50">{{ selectedLabel(g) }}</p>
                </div>
                <div class="grid grid-cols-2 gap-3 md:grid-cols-3">
                    <button
                        v-for="v in g.values" :key="v.id" type="button"
                        class="group relative overflow-hidden rounded-2xl border text-left transition"
                        :class="chosen[g.id] === v.id ? 'border-brand-600 ring-2 ring-brand-600 shadow-md' : 'border-paper-300 bg-white hover:border-ink/25 hover:shadow-md'"
                        @click="pick(g, v)"
                    >
                        <!-- preview: AI material shot, swatch colour, or textured placeholder -->
                        <div class="relative aspect-[4/3] overflow-hidden bg-paper-200">
                            <img
                                v-if="v.image" :src="v.image" :alt="`${v.label} preview`" loading="lazy"
                                class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.04]"
                            />
                            <div
                                v-else class="bg-grain grid h-full w-full place-items-center"
                                :style="v.swatch ? { backgroundColor: v.swatch } : {}"
                            >
                                <span class="font-display text-4xl font-semibold" :class="v.swatch ? 'text-white/70' : 'text-ink/15'">{{ v.label.slice(0, 1) }}</span>
                            </div>
                            <span
                                v-if="chosen[g.id] === v.id"
                                class="absolute right-2 top-2 grid h-6 w-6 place-items-center rounded-full bg-brand-600 text-white shadow"
                            >
                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="m5 13 4 4L19 7" stroke-linecap="round" stroke-linejoin="round" /></svg>
                            </span>
                            <span v-if="v.badge" class="absolute left-2 top-2 rounded-full bg-lime-accent px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-navy">{{ v.badge }}</span>
                        </div>
                        <div class="p-3">
                            <div class="flex items-baseline justify-between gap-2">
                                <span class="truncate text-sm font-semibold text-ink">{{ v.label }}</span>
                                <span class="shrink-0 text-xs font-medium" :class="Number(v.priceDelta) > 0 ? 'text-ink/60' : 'text-brand-700'">
                                    {{ Number(v.priceDelta) > 0 ? `+${money(v.priceDelta)}` : 'Included' }}
                                </span>
                            </div>
                            <p v-if="v.description" class="mt-1 line-clamp-2 text-xs leading-relaxed text-ink/55">{{ v.description }}</p>
                            <p v-if="attrLine(v)" class="mt-1 text-[11px] text-ink/40">{{ attrLine(v) }}</p>
                        </div>
                    </button>
                </div>
            </section>

            <!-- surface-bound options stay as approved -->
            <div v-if="(payload.locked || []).length" class="flex items-start gap-3 rounded-2xl border border-paper-300 bg-paper-200/60 p-4">
                <svg class="mt-0.5 h-4.5 w-4.5 shrink-0 text-ink/45" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="5" y="11" width="14" height="9" rx="2" /><path d="M8 11V8a4 4 0 0 1 8 0v3" stroke-linecap="round" /></svg>
                <div class="text-sm">
                    <p class="font-medium text-ink/80">
                        Locked to your approved design:
                        <span class="text-ink/60"><template v-for="(l, i) in payload.locked" :key="l.name">{{ i ? ' · ' : ' ' }}{{ l.name }} — {{ l.label }}</template></span>
                    </p>
                    <p class="mt-0.5 text-xs text-ink/50">These set the print area, so changing them would mean redesigning.</p>
                </div>
            </div>
        </div>

        <!-- mobile: total + continue always at hand (before the aside so the
             summary CTA stays the LAST visible continue button on desktop) -->
        <div class="fixed inset-x-0 bottom-0 z-40 flex items-center justify-between gap-3 border-t border-paper-300 bg-white/95 px-4 py-3 backdrop-blur lg:hidden">
            <div>
                <p class="font-display text-lg font-bold leading-tight text-ink" :class="busy ? 'animate-pulse opacity-50' : ''">{{ money(total) }}</p>
                <p class="text-[11px] leading-tight text-ink/50">{{ money(totalPerUnit) }} each</p>
            </div>
            <button :disabled="advancing" class="inline-flex items-center gap-2 rounded-full bg-brand-600 px-6 py-2.5 text-sm font-semibold text-white shadow transition hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-50 disabled:saturate-0" @click="toCart">
                <span v-if="advancing" class="h-3.5 w-3.5 animate-spin rounded-full border-2 border-white/60 border-t-transparent"></span>
                Continue →
            </button>
        </div>

        <!-- ============ sticky order summary ============ -->
        <aside class="h-max rounded-2xl border border-paper-300 bg-white shadow-sm lg:sticky lg:top-24">
            <div class="border-b border-paper-300 bg-paper-200/60 p-4">
                <div class="grid place-items-center rounded-xl bg-white p-3 shadow-inner">
                    <img v-if="line.preview" :src="line.preview" :alt="`${line.name} preview`" class="max-h-44 w-auto max-w-full rounded-md ring-1 ring-paper-300" />
                    <div v-else class="aspect-[4/3] w-full overflow-hidden rounded-md"><SmartImage :src="line.image" :alt="line.name" /></div>
                </div>
            </div>
            <div class="p-5">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="font-display text-base font-semibold text-ink">{{ line.name }}</h2>
                    <span class="shrink-0 rounded-full bg-brand-50 px-2.5 py-0.5 text-[11px] font-semibold text-brand-700">
                        {{ line.mode === 'upload' ? 'Your artwork' : 'Custom design' }}
                    </span>
                </div>

                <dl class="mt-4 space-y-2 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-ink/55">Quantity</dt>
                        <dd class="font-medium text-ink">{{ selectedTier?.quantity ?? line.quantity }} units</dd>
                    </div>
                    <div v-for="g in groups" :key="g.id" class="flex justify-between gap-4">
                        <dt class="text-ink/55">{{ g.name }}</dt>
                        <dd class="text-right font-medium text-ink">{{ selectedLabel(g) }}</dd>
                    </div>
                    <div v-for="l in payload.locked || []" :key="l.name" class="flex justify-between gap-4 text-ink/45">
                        <dt>{{ l.name }}</dt>
                        <dd class="text-right">{{ l.label }} 🔒</dd>
                    </div>
                </dl>

                <div class="mt-4 flex items-end justify-between gap-4 border-t border-paper-300 pt-4">
                    <div>
                        <p class="text-xs text-ink/50">Total</p>
                        <p class="font-display text-2xl font-bold text-ink transition-opacity" :class="busy ? 'animate-pulse opacity-50' : ''">{{ money(total) }}</p>
                    </div>
                    <p class="pb-1 text-xs text-ink/50">{{ money(totalPerUnit) }} each</p>
                </div>

                <p v-if="summary.qualifies" class="mt-2 text-xs font-medium text-brand-700">✓ This order ships free</p>
                <p v-else-if="summary.remaining > 0" class="mt-2 text-xs text-ink/55">{{ money(summary.remaining) }} away from free shipping</p>

                <button
                    :disabled="advancing"
                    class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-full bg-brand-600 px-6 py-3.5 font-semibold text-white shadow-lg shadow-brand-600/20 transition hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-50 disabled:shadow-none disabled:saturate-0"
                    @click="toCart"
                >
                    <span v-if="advancing" class="h-4 w-4 animate-spin rounded-full border-2 border-white/60 border-t-transparent"></span>
                    Looks perfect — continue →
                </button>
                <p class="mt-3 text-center text-[11px] text-ink/45">100% satisfaction guarantee — love it or we reprint it</p>
            </div>
        </aside>

        <div class="h-16 lg:hidden" aria-hidden="true"></div>
    </div>
</template>
