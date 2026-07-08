<script setup>
import { computed } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';

const props = defineProps({ surface: { type: Object, required: true } });

const form = useForm({
    name: props.surface.name,
    slug: props.surface.slug,
    unit: props.surface.unit,
    width: props.surface.width,
    height: props.surface.height,
    bleed: props.surface.bleed,
    safety: props.surface.safety,
    isActive: props.surface.isActive,
    noPrint: props.surface.noPrint.map((a) => ({ label: a.label ?? 'No print', x: a.x ?? 0, y: a.y ?? 0, w: a.w ?? 0, h: a.h ?? 0 })),
    fold: props.surface.fold.map((f) => ({ label: f.label ?? 'Fold', orientation: f.orientation ?? 'vertical', position: f.position ?? 0 })),
    cutPath: props.surface.cutPath ?? '',
});

const addNoPrint = () => form.noPrint.push({ label: 'No print', x: 0, y: 0, w: Number(form.width) || 0, h: 5 });
const removeNoPrint = (i) => form.noPrint.splice(i, 1);
const addFold = () => form.fold.push({ label: 'Fold', orientation: 'vertical', position: +((Number(form.width) || 0) / 2).toFixed(2) });
const removeFold = (i) => form.fold.splice(i, 1);

const save = () => form.put(`/admin/surfaces/${props.surface.slug}`);
const destroy = () => { if (confirm('Delete this surface? Products using it fall back to auto sizing.')) router.delete(`/admin/surfaces/${props.surface.slug}`); };

// scaled preview (longest edge → 300px), mirrors the designer overlay
const geo = computed(() => {
    const w = Number(form.width) || 1;
    const h = Number(form.height) || 1;
    const ppu = 300 / Math.max(w, h);
    const bleed = (Number(form.bleed) || 0) * ppu;
    const safety = (Number(form.safety) || 0) * ppu;
    const tw = w * ppu;
    const th = h * ppu;
    return { ppu, bleed, safety, tw, th, cw: tw + 2 * bleed, ch: th + 2 * bleed, safeW: Math.max(0, tw - 2 * safety), safeH: Math.max(0, th - 2 * safety) };
});
const npRect = (a) => ({ x: geo.value.bleed + (Number(a.x) || 0) * geo.value.ppu, y: geo.value.bleed + (Number(a.y) || 0) * geo.value.ppu, w: (Number(a.w) || 0) * geo.value.ppu, h: (Number(a.h) || 0) * geo.value.ppu });
const foldPos = (f) => geo.value.bleed + (Number(f.position) || 0) * geo.value.ppu;
</script>

<template>
    <Head :title="`Surface — ${surface.name}`" />
    <AdminLayout :title="surface.name">
        <template #actions>
            <button :disabled="form.processing" class="rounded-full bg-brand-600 px-5 py-2 text-sm font-semibold text-white transition hover:bg-brand-700 disabled:opacity-60" @click="save">{{ form.processing ? 'Saving…' : 'Save surface' }}</button>
        </template>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <!-- dimensions -->
                <section class="rounded-2xl border border-paper-300 bg-white p-6 shadow-sm">
                    <h2 class="mb-4 font-display text-base font-semibold text-ink">Dimensions</h2>
                    <div class="grid gap-4 sm:grid-cols-4">
                        <div class="sm:col-span-3"><label class="mb-1 block text-xs font-medium text-ink/55">Name</label><input v-model="form.name" class="w-full border border-ink/20 px-3 py-2 text-sm focus:border-brand-600 focus:outline-none" /></div>
                        <div><label class="mb-1 block text-xs font-medium text-ink/55">Unit</label><select v-model="form.unit" class="w-full border border-ink/20 bg-white px-3 py-2 text-sm focus:border-brand-600 focus:outline-none"><option value="mm">mm</option><option value="cm">cm</option><option value="in">in</option><option value="ft">ft</option></select></div>
                        <div><label class="mb-1 block text-xs font-medium text-ink/55">Width</label><input v-model.number="form.width" type="number" step="0.01" min="1" class="w-full border border-ink/20 px-3 py-2 text-sm focus:border-brand-600 focus:outline-none" /></div>
                        <div><label class="mb-1 block text-xs font-medium text-ink/55">Height</label><input v-model.number="form.height" type="number" step="0.01" min="1" class="w-full border border-ink/20 px-3 py-2 text-sm focus:border-brand-600 focus:outline-none" /></div>
                        <div><label class="mb-1 block text-xs font-medium text-ink/55">Bleed</label><input v-model.number="form.bleed" type="number" step="0.01" min="0" class="w-full border border-ink/20 px-3 py-2 text-sm focus:border-brand-600 focus:outline-none" /></div>
                        <div><label class="mb-1 block text-xs font-medium text-ink/55">Safety</label><input v-model.number="form.safety" type="number" step="0.01" min="0" class="w-full border border-ink/20 px-3 py-2 text-sm focus:border-brand-600 focus:outline-none" /></div>
                        <div class="sm:col-span-2"><label class="mb-1 block text-xs font-medium text-ink/55">Slug</label><input v-model="form.slug" class="w-full border border-ink/20 px-3 py-2 text-sm focus:border-brand-600 focus:outline-none" /><p v-if="form.errors.slug" class="mt-1 text-xs text-red-600">{{ form.errors.slug }}</p></div>
                    </div>
                    <label class="mt-4 flex items-center gap-2 border-t border-paper-200 pt-4 text-sm"><input v-model="form.isActive" type="checkbox" class="h-4 w-4" /> Active</label>
                </section>

                <!-- no-print -->
                <section class="rounded-2xl border border-paper-300 bg-white p-6 shadow-sm">
                    <div class="mb-3 flex items-center justify-between">
                        <div><h2 class="font-display text-base font-semibold text-ink">No-print areas</h2><p class="text-sm text-ink/50">Zones the customer can't print in (pole pockets, grommets, glue strips). Coordinates from the trim's top-left, in {{ form.unit }}.</p></div>
                        <button class="rounded-full bg-brand-50 px-4 py-1.5 text-sm font-semibold text-brand-700 hover:bg-brand-100" @click="addNoPrint">+ Zone</button>
                    </div>
                    <div v-if="form.noPrint.length" class="space-y-2">
                        <div class="grid grid-cols-[1fr_auto_auto_auto_auto_auto] gap-2 px-1 text-[11px] font-semibold uppercase tracking-wide text-ink/40"><span>Label</span><span class="w-16 text-center">X</span><span class="w-16 text-center">Y</span><span class="w-16 text-center">W</span><span class="w-16 text-center">H</span><span></span></div>
                        <div v-for="(a, i) in form.noPrint" :key="i" class="grid grid-cols-[1fr_auto_auto_auto_auto_auto] items-center gap-2">
                            <input v-model="a.label" class="border border-ink/20 px-2 py-1.5 text-sm focus:border-brand-600 focus:outline-none" />
                            <input v-model.number="a.x" type="number" step="0.01" class="w-16 border border-ink/20 px-2 py-1.5 text-sm focus:border-brand-600 focus:outline-none" />
                            <input v-model.number="a.y" type="number" step="0.01" class="w-16 border border-ink/20 px-2 py-1.5 text-sm focus:border-brand-600 focus:outline-none" />
                            <input v-model.number="a.w" type="number" step="0.01" class="w-16 border border-ink/20 px-2 py-1.5 text-sm focus:border-brand-600 focus:outline-none" />
                            <input v-model.number="a.h" type="number" step="0.01" class="w-16 border border-ink/20 px-2 py-1.5 text-sm focus:border-brand-600 focus:outline-none" />
                            <button class="px-2 text-ink/40 hover:text-red-600" @click="removeNoPrint(i)">✕</button>
                        </div>
                    </div>
                    <p v-else class="text-sm text-ink/45">None.</p>
                </section>

                <!-- die-cut -->
                <section class="rounded-2xl border border-paper-300 bg-white p-6 shadow-sm">
                    <h2 class="font-display text-base font-semibold text-ink">Die-cut / sewn edge</h2>
                    <p class="mb-3 text-sm text-ink/50">For shaped products (feather flags, circle/oval cards): an SVG path in <strong>normalized coordinates 0–100</strong> on both axes, relative to the trim box. Leave empty for rectangular products.</p>
                    <textarea v-model="form.cutPath" rows="3" spellcheck="false" placeholder="e.g. M 50 0 A 50 50 0 1 0 50 100 A 50 50 0 1 0 50 0 Z" class="w-full border border-ink/20 px-3 py-2 font-mono text-xs focus:border-brand-600 focus:outline-none"></textarea>
                    <p v-if="form.errors.cutPath" class="mt-1 text-xs text-red-600">{{ form.errors.cutPath }}</p>
                </section>

                <!-- folds -->
                <section class="rounded-2xl border border-paper-300 bg-white p-6 shadow-sm">
                    <div class="mb-3 flex items-center justify-between">
                        <div><h2 class="font-display text-base font-semibold text-ink">Fold lines</h2><p class="text-sm text-ink/50">Where the piece folds (brochures, folded cards). Position in {{ form.unit }} from the trim's left (vertical) or top (horizontal).</p></div>
                        <button class="rounded-full bg-brand-50 px-4 py-1.5 text-sm font-semibold text-brand-700 hover:bg-brand-100" @click="addFold">+ Fold</button>
                    </div>
                    <div v-if="form.fold.length" class="space-y-2">
                        <div v-for="(f, i) in form.fold" :key="i" class="flex flex-wrap items-center gap-2">
                            <input v-model="f.label" class="w-32 border border-ink/20 px-2 py-1.5 text-sm focus:border-brand-600 focus:outline-none" />
                            <select v-model="f.orientation" class="border border-ink/20 bg-white px-2 py-1.5 text-sm focus:border-brand-600 focus:outline-none"><option value="vertical">Vertical</option><option value="horizontal">Horizontal</option></select>
                            <div class="flex items-center gap-1 text-sm text-ink/55">at <input v-model.number="f.position" type="number" step="0.01" class="w-20 border border-ink/20 px-2 py-1.5 text-sm focus:border-brand-600 focus:outline-none" /> {{ form.unit }}</div>
                            <button class="px-2 text-ink/40 hover:text-red-600" @click="removeFold(i)">✕</button>
                        </div>
                    </div>
                    <p v-else class="text-sm text-ink/45">None.</p>
                </section>
            </div>

            <!-- live preview -->
            <div class="lg:col-span-1">
                <section class="sticky top-24 rounded-2xl border border-paper-300 bg-white p-6 shadow-sm">
                    <h2 class="mb-3 font-display text-base font-semibold text-ink">Preview</h2>
                    <div class="grid min-h-[320px] place-items-center rounded-xl bg-paper-200 p-4">
                        <svg :width="geo.cw" :height="geo.ch" :viewBox="`0 0 ${geo.cw} ${geo.ch}`" class="max-w-full bg-white shadow ring-1 ring-paper-300">
                            <path v-if="geo.bleed" :d="`M0 0H${geo.cw}V${geo.ch}H0Z M${geo.bleed} ${geo.bleed}h${geo.tw}v${geo.th}h${-geo.tw}Z`" fill="rgba(225,29,72,0.12)" fill-rule="evenodd" />
                            <template v-if="form.cutPath && form.cutPath.trim()">
                                <svg :x="geo.bleed" :y="geo.bleed" :width="geo.tw" :height="geo.th" viewBox="0 0 100 100" preserveAspectRatio="none" class="overflow-visible">
                                    <path :d="form.cutPath" fill="rgba(225,29,72,0.05)" stroke="#e11d48" stroke-width="1" vector-effect="non-scaling-stroke" />
                                    <!-- per-axis inset so the margin really is `safety` on both axes (a uniform 0.9 was 4× too deep on tall dies) -->
                                    <path v-if="geo.safety" :d="form.cutPath" fill="none" stroke="#0ea5e9" stroke-width="1" stroke-dasharray="4 3" vector-effect="non-scaling-stroke" :transform="`translate(50 50) scale(${geo.safeW / geo.tw} ${geo.safeH / geo.th}) translate(-50 -50)`" />
                                </svg>
                            </template>
                            <template v-else>
                                <rect :x="geo.bleed" :y="geo.bleed" :width="geo.tw" :height="geo.th" fill="none" stroke="#e11d48" stroke-width="1" />
                                <rect :x="geo.bleed + geo.safety" :y="geo.bleed + geo.safety" :width="geo.safeW" :height="geo.safeH" fill="none" stroke="#0ea5e9" stroke-width="1" stroke-dasharray="5 4" />
                            </template>
                            <g v-for="(a, i) in form.noPrint" :key="'np' + i"><rect :x="npRect(a).x" :y="npRect(a).y" :width="npRect(a).w" :height="npRect(a).h" fill="rgba(15,23,42,0.42)" stroke="#0f172a" stroke-width="0.8" /></g>
                            <g v-for="(f, i) in form.fold" :key="'f' + i">
                                <line v-if="f.orientation === 'vertical'" :x1="foldPos(f)" :y1="geo.bleed" :x2="foldPos(f)" :y2="geo.bleed + geo.th" stroke="#9333ea" stroke-width="1" stroke-dasharray="2 3" />
                                <line v-else :x1="geo.bleed" :y1="foldPos(f)" :x2="geo.bleed + geo.tw" :y2="foldPos(f)" stroke="#9333ea" stroke-width="1" stroke-dasharray="2 3" />
                            </g>
                        </svg>
                    </div>
                    <div class="mt-3 space-y-1 text-xs text-ink/55">
                        <p><span class="mr-1.5 inline-block h-2.5 w-3.5 bg-rose-500/15 align-middle ring-1 ring-rose-500/60"></span>Bleed · <span class="mx-1 inline-block h-0 w-4 border-t-2 border-rose-500 align-middle"></span>Trim · <span class="mx-1 inline-block h-0 w-4 border-t-2 border-dashed border-sky-500 align-middle"></span>Safe</p>
                        <p><span class="mr-1.5 inline-block h-2.5 w-3.5 bg-slate-800/40 align-middle"></span>No-print · <span class="mx-1 inline-block h-0 w-4 border-t-2 border-dashed border-purple-600 align-middle"></span>Fold</p>
                    </div>
                </section>
            </div>
        </div>

        <div class="mt-6 flex items-center justify-between">
            <button class="text-sm font-medium text-red-600 hover:underline" @click="destroy">Delete surface</button>
            <button :disabled="form.processing" class="rounded-full bg-brand-600 px-6 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-700 disabled:opacity-60" @click="save">{{ form.processing ? 'Saving…' : 'Save surface' }}</button>
        </div>
    </AdminLayout>
</template>
