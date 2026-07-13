<script setup>
import { ref, computed, watch } from 'vue';
import { money } from '../lib/format';

const props = defineProps({
    // product being added: { slug, name, img, fromPrice } — null when the modal is closed
    product: { type: Object, default: null },
    busy: { type: Boolean, default: false },
});
const emit = defineEmits(['close', 'confirm']);

const loading = ref(false);
const data = ref(null);   // fetched { name, fromPrice, quantities, options }
const qtyId = ref(null);  // selected tier id
const picks = ref({});    // option.id -> value.id

// Load the product's tiers + options each time the modal opens on a new product.
watch(() => props.product, async (p) => {
    if (!p) return;
    loading.value = true;
    data.value = null;
    picks.value = {};
    qtyId.value = null;
    try {
        const r = await fetch(`/upsell/options/${p.slug}`, { headers: { Accept: 'application/json' } });
        const d = await r.json();
        data.value = d;
        const defQ = (d.quantities || []).find((q) => q.isDefault) || (d.quantities || [])[0];
        qtyId.value = defQ ? defQ.id : null;
        (d.options || []).forEach((o) => {
            const dv = o.values.find((v) => v.isDefault) || o.values[0];
            if (dv) picks.value[o.id] = dv.id;
        });
    } catch (e) {
        data.value = { quantities: [], options: [] };
    } finally {
        loading.value = false;
    }
}, { immediate: true });

const currentTier = computed(() => (data.value?.quantities || []).find((q) => q.id === qtyId.value) || null);
const optionDelta = computed(() => (data.value?.options || []).reduce((sum, o) => {
    const v = o.values.find((x) => x.id === picks.value[o.id]);
    return sum + (v ? v.priceDelta || 0 : 0);
}, 0));
const total = computed(() => (currentTier.value ? currentTier.value.total : (data.value?.fromPrice || 0)) + optionDelta.value);
const qtyN = computed(() => currentTier.value?.quantity || 1);
const labelFor = (o) => o.values.find((v) => v.id === picks.value[o.id])?.label;

const confirm = () => emit('confirm', {
    slug: props.product.slug,
    quantityId: qtyId.value,
    optionValueIds: Object.values(picks.value),
});
</script>

<template>
    <Teleport to="body">
        <div v-if="product" class="fixed inset-0 z-[60] flex items-end justify-center bg-ink/50 sm:items-center sm:p-4" @click.self="emit('close')">
            <div class="relative flex max-h-[92vh] w-full max-w-lg flex-col overflow-hidden rounded-t-3xl bg-white shadow-2xl sm:rounded-3xl">
                <!-- header -->
                <div class="flex items-center gap-3 border-b border-paper-200 p-4">
                    <div v-if="product.img" class="h-14 w-14 shrink-0 overflow-hidden rounded-xl bg-paper-200">
                        <img :src="product.img" alt="" class="h-full w-full object-cover" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-display text-lg font-semibold leading-tight text-ink">{{ data?.name || product.name }}</p>
                        <p class="text-sm text-ink/55">Choose quantity &amp; options</p>
                    </div>
                    <button class="grid h-8 w-8 shrink-0 place-items-center rounded-full text-ink/50 transition hover:bg-paper-200" aria-label="Close" @click="emit('close')">✕</button>
                </div>

                <!-- body -->
                <div class="flex-1 overflow-y-auto p-4">
                    <div v-if="loading" class="grid place-items-center py-14">
                        <div class="h-8 w-8 animate-spin rounded-full border-2 border-brand-600 border-t-transparent"></div>
                    </div>
                    <template v-else-if="data">
                        <!-- quantity -->
                        <div v-if="data.quantities && data.quantities.length > 1" class="mb-5">
                            <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-ink/55">Quantity</label>
                            <select v-model="qtyId" class="w-full rounded-xl border border-paper-300 bg-white px-3 py-2.5 text-sm text-ink focus:border-brand-400 focus:outline-none">
                                <option v-for="q in data.quantities" :key="q.id" :value="q.id">{{ q.quantity }} — {{ money(q.total + optionDelta) }}</option>
                            </select>
                        </div>

                        <!-- option groups -->
                        <div v-for="o in data.options" :key="o.id" class="mb-5">
                            <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-ink/55">
                                {{ o.name }}
                                <span v-if="o.type === 'swatch' && labelFor(o)" class="ml-1 font-normal normal-case text-ink/70">· {{ labelFor(o) }}</span>
                            </label>
                            <div v-if="o.type === 'swatch'" class="flex flex-wrap gap-2">
                                <button v-for="v in o.values" :key="v.id" type="button"
                                        class="h-9 w-9 rounded-full border-2 transition"
                                        :class="picks[o.id] === v.id ? 'border-brand-600 ring-2 ring-brand-200' : 'border-paper-300 hover:border-ink/30'"
                                        :style="{ backgroundColor: v.swatch || '#ddd' }" :title="v.label" @click="picks[o.id] = v.id"></button>
                            </div>
                            <div v-else class="flex flex-wrap gap-2">
                                <button v-for="v in o.values" :key="v.id" type="button"
                                        class="rounded-full border px-3.5 py-1.5 text-sm transition"
                                        :class="picks[o.id] === v.id ? 'border-brand-600 bg-brand-50 font-semibold text-brand-700' : 'border-paper-300 text-ink/70 hover:border-ink/30'"
                                        @click="picks[o.id] = v.id">
                                    {{ v.label }}<span v-if="v.priceDelta" class="text-ink/45"> ({{ v.priceDelta > 0 ? '+' : '' }}{{ money(v.priceDelta) }})</span>
                                </button>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- footer -->
                <div class="border-t border-paper-200 p-4">
                    <div class="mb-3 flex items-baseline justify-between">
                        <span class="text-sm text-ink/60">{{ qtyN }} {{ qtyN === 1 ? 'item' : 'items' }}</span>
                        <span class="font-display text-xl font-bold text-ink">{{ money(total) }}</span>
                    </div>
                    <button type="button" :disabled="busy || loading"
                            class="w-full rounded-full bg-brand-600 px-6 py-3 font-semibold text-white transition hover:bg-brand-700 disabled:opacity-70"
                            @click="confirm">
                        {{ busy ? 'Adding…' : 'Add to order' }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
