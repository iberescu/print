<script setup>
import { reactive } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';

const props = defineProps({
    product: { type: Object, required: true },
    categories: { type: Array, default: () => [] },
    surfaces: { type: Array, default: () => [] },
    options: { type: Array, default: () => [] },
    quantities: { type: Array, default: () => [] },
});

const form = useForm({
    name: props.product.name,
    slug: props.product.slug,
    categoryId: props.product.categoryId,
    tagline: props.product.tagline ?? '',
    description: props.product.description ?? '',
    fromPrice: props.product.fromPrice ?? 0,
    badge: props.product.badge ?? '',
    supportsDesign: props.product.supportsDesign,
    supportsUpload: props.product.supportsUpload,
    isActive: props.product.isActive,
    surfaceId: props.product.surfaceId ?? null,
    seo: {
        description: props.product.seo?.description ?? '',
        details: [...(props.product.seo?.details ?? [])],
        faq: (props.product.seo?.faq ?? []).map((f) => ({ q: f.q ?? '', a: f.a ?? '' })),
    },
    options: props.options.map((o) => ({
        name: o.name, type: o.type, required: o.required,
        values: o.values.map((v) => ({
            label: v.label, priceDelta: v.priceDelta, badge: v.badge ?? '', swatch: v.swatch ?? '', description: v.description ?? '', isDefault: v.isDefault,
            surfaceId: v.surfaceId ?? null,
            attributes: (v.attributes ?? []).map((a) => ({ name: a.name ?? '', value: a.value ?? '' })),
        })),
    })),
    quantities: props.quantities.map((q) => ({ quantity: q.quantity, unitPrice: q.unitPrice, totalPrice: q.totalPrice ?? q.unitPrice * q.quantity, isDefault: q.isDefault })),
});

const money = (n) => '$' + Number(n || 0).toFixed(2);
const perUnit = (t) => (t.quantity ? (Number(t.totalPrice || 0) / t.quantity) : 0);

// options
const blankValue = (isDefault = false) => ({ label: '', priceDelta: 0, badge: '', swatch: '', description: '', isDefault, surfaceId: null, attributes: [] });
const addOption = () => form.options.push({ name: '', type: 'select', required: true, values: [blankValue(true)] });
const removeOption = (oi) => form.options.splice(oi, 1);
const addValue = (oi) => form.options[oi].values.push(blankValue(form.options[oi].values.length === 0));
const removeValue = (oi, vi) => form.options[oi].values.splice(vi, 1);
const setDefaultValue = (oi, vi) => form.options[oi].values.forEach((v, i) => (v.isDefault = i === vi));

// per-value detail menu (specs + surface)
const openValue = reactive({});
const isOpen = (oi, vi) => !!openValue[`${oi}-${vi}`];
const toggleValue = (oi, vi) => (openValue[`${oi}-${vi}`] = !openValue[`${oi}-${vi}`]);
const addAttr = (oi, vi) => form.options[oi].values[vi].attributes.push({ name: '', value: '' });
const removeAttr = (oi, vi, ai) => form.options[oi].values[vi].attributes.splice(ai, 1);

// SEO content (drives the storefront product page + JSON-LD)
const addDetail = () => form.seo.details.push('');
const removeDetail = (i) => form.seo.details.splice(i, 1);
const addFaq = () => form.seo.faq.push({ q: '', a: '' });
const removeFaq = (i) => form.seo.faq.splice(i, 1);

// pricing tiers
const addTier = () => form.quantities.push({ quantity: 1, unitPrice: 0, totalPrice: 0, isDefault: form.quantities.length === 0 });
const removeTier = (ti) => form.quantities.splice(ti, 1);
const setDefaultTier = (ti) => form.quantities.forEach((t, i) => (t.isDefault = i === ti));

const save = () => {
    // derive unit_price from the authoritative tier total (matches storefront pricing)
    form.transform((data) => ({
        ...data,
        quantities: data.quantities.map((t) => ({ ...t, unitPrice: t.quantity ? +(Number(t.totalPrice || 0) / t.quantity).toFixed(4) : 0 })),
    })).put(`/admin/products/${props.product.slug}`);
};

const destroy = () => {
    if (confirm(`Delete “${props.product.name}”? This removes its options and pricing too.`)) {
        router.delete(`/admin/products/${props.product.slug}`);
    }
};
</script>

<template>
    <Head :title="`Edit — ${product.name}`" />
    <AdminLayout :title="product.name">
        <template #actions>
            <a :href="`/product/${product.slug}`" target="_blank" class="hidden text-sm font-medium text-ink/60 hover:text-ink sm:inline">View in store ↗</a>
            <button :disabled="form.processing" class="rounded-full bg-brand-600 px-5 py-2 text-sm font-semibold text-white transition hover:bg-brand-700 disabled:opacity-60" @click="save">{{ form.processing ? 'Saving…' : 'Save changes' }}</button>
        </template>

        <div class="space-y-6">
            <!-- basics -->
            <section class="rounded-2xl border border-paper-300 bg-white p-6 shadow-sm">
                <h2 class="mb-4 font-display text-base font-semibold text-ink">Product details</h2>
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-ink/55">Name</label>
                        <input v-model="form.name" class="w-full border border-ink/20 px-3 py-2 text-sm focus:border-brand-600 focus:outline-none" />
                        <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-ink/55">Slug (URL)</label>
                        <input v-model="form.slug" class="w-full border border-ink/20 px-3 py-2 text-sm focus:border-brand-600 focus:outline-none" />
                        <p v-if="form.errors.slug" class="mt-1 text-xs text-red-600">{{ form.errors.slug }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-ink/55">Category</label>
                        <select v-model="form.categoryId" class="w-full border border-ink/20 bg-white px-3 py-2 text-sm focus:border-brand-600 focus:outline-none">
                            <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-ink/55">"From" price (display)</label>
                        <input v-model.number="form.fromPrice" type="number" step="0.01" min="0" class="w-full border border-ink/20 px-3 py-2 text-sm focus:border-brand-600 focus:outline-none" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-ink/55">Designer surface (default)</label>
                        <select v-model="form.surfaceId" class="w-full border border-ink/20 bg-white px-3 py-2 text-sm focus:border-brand-600 focus:outline-none">
                            <option :value="null">— Auto (from size / format option) —</option>
                            <option v-for="s in surfaces" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-ink/55">Tagline</label>
                        <input v-model="form.tagline" class="w-full border border-ink/20 px-3 py-2 text-sm focus:border-brand-600 focus:outline-none" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-ink/55">Badge (optional)</label>
                        <input v-model="form.badge" placeholder="e.g. Bestseller" class="w-full border border-ink/20 px-3 py-2 text-sm focus:border-brand-600 focus:outline-none" />
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-xs font-medium text-ink/55">Description</label>
                        <textarea v-model="form.description" rows="3" class="w-full border border-ink/20 px-3 py-2 text-sm focus:border-brand-600 focus:outline-none"></textarea>
                    </div>
                </div>
                <div class="mt-4 flex flex-wrap gap-5 border-t border-paper-200 pt-4 text-sm">
                    <label class="flex items-center gap-2"><input v-model="form.isActive" type="checkbox" class="h-4 w-4" /> Active (visible in store)</label>
                    <label class="flex items-center gap-2"><input v-model="form.supportsDesign" type="checkbox" class="h-4 w-4" /> Online designer</label>
                    <label class="flex items-center gap-2"><input v-model="form.supportsUpload" type="checkbox" class="h-4 w-4" /> Upload artwork</label>
                </div>
            </section>

            <!-- SEO content (storefront product page + JSON-LD) -->
            <section class="rounded-2xl border border-paper-300 bg-white p-6 shadow-sm">
                <div class="mb-4">
                    <h2 class="font-display text-base font-semibold text-ink">SEO content</h2>
                    <p class="text-sm text-ink/50">Shown on the product page (About / Product details / FAQ) and emitted as Product + FAQ structured data for search engines.</p>
                </div>

                <div>
                    <label class="mb-1 block text-xs font-medium text-ink/55">About / description <span class="text-ink/35">— blank lines separate paragraphs</span></label>
                    <textarea v-model="form.seo.description" rows="6" placeholder="Two short paragraphs describing the product, its benefits and use cases…" class="w-full border border-ink/20 px-3 py-2 text-sm leading-relaxed focus:border-brand-600 focus:outline-none"></textarea>
                    <p v-if="form.errors['seo.description']" class="mt-1 text-xs text-red-600">{{ form.errors['seo.description'] }}</p>
                </div>

                <div class="mt-5 grid gap-6 md:grid-cols-2">
                    <!-- details -->
                    <div>
                        <label class="mb-1.5 block text-xs font-medium text-ink/55">Product details</label>
                        <div v-for="(d, i) in form.seo.details" :key="i" class="mb-1.5 flex items-center gap-2">
                            <span class="text-brand-600">✓</span>
                            <input v-model="form.seo.details[i]" placeholder="e.g. Available in A5, DL and A4" class="flex-1 border border-ink/20 px-2.5 py-1.5 text-sm focus:border-brand-600 focus:outline-none" />
                            <button class="px-2 text-ink/40 hover:text-red-600" title="Remove" @click="removeDetail(i)">✕</button>
                        </div>
                        <button class="mt-1 text-sm font-medium text-brand-700 hover:underline" @click="addDetail">+ Add detail</button>
                    </div>

                    <!-- faq -->
                    <div>
                        <label class="mb-1.5 block text-xs font-medium text-ink/55">FAQ</label>
                        <div v-for="(f, i) in form.seo.faq" :key="i" class="mb-2 rounded-lg border border-paper-300 bg-paper-200/40 p-2.5">
                            <div class="flex items-center gap-2">
                                <input v-model="f.q" placeholder="Question" class="flex-1 border border-ink/20 bg-white px-2.5 py-1.5 text-sm font-medium focus:border-brand-600 focus:outline-none" />
                                <button class="px-2 text-ink/40 hover:text-red-600" title="Remove" @click="removeFaq(i)">✕</button>
                            </div>
                            <textarea v-model="f.a" rows="2" placeholder="Answer" class="mt-1.5 w-full border border-ink/20 bg-white px-2.5 py-1.5 text-sm focus:border-brand-600 focus:outline-none"></textarea>
                        </div>
                        <button class="mt-1 text-sm font-medium text-brand-700 hover:underline" @click="addFaq">+ Add question</button>
                    </div>
                </div>
            </section>

            <!-- print options -->
            <section class="rounded-2xl border border-paper-300 bg-white p-6 shadow-sm">
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <h2 class="font-display text-base font-semibold text-ink">Print options</h2>
                        <p class="text-sm text-ink/50">Format, material, finish, colour… each value can add to the price.</p>
                    </div>
                    <button class="rounded-full bg-brand-50 px-4 py-1.5 text-sm font-semibold text-brand-700 transition hover:bg-brand-100" @click="addOption">+ Option</button>
                </div>

                <div v-if="!form.options.length" class="rounded-xl border border-dashed border-paper-300 py-8 text-center text-sm text-ink/50">No options yet — add Size, Paper, Finish, etc.</div>

                <div v-for="(o, oi) in form.options" :key="oi" class="mb-4 rounded-xl border border-paper-300 bg-paper-200/40 p-4">
                    <div class="flex flex-wrap items-end gap-3">
                        <div class="flex-1">
                            <label class="mb-1 block text-xs font-medium text-ink/55">Option name</label>
                            <input v-model="o.name" placeholder="e.g. Paper Stock" class="w-full border border-ink/20 bg-white px-3 py-2 text-sm focus:border-brand-600 focus:outline-none" />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-ink/55">Type</label>
                            <select v-model="o.type" class="border border-ink/20 bg-white px-3 py-2 text-sm focus:border-brand-600 focus:outline-none">
                                <option value="select">Buttons</option>
                                <option value="radio">Radio</option>
                                <option value="swatch">Colour swatch</option>
                            </select>
                        </div>
                        <label class="flex items-center gap-2 pb-2 text-sm"><input v-model="o.required" type="checkbox" class="h-4 w-4" /> Required</label>
                        <button class="pb-1 text-sm font-medium text-red-600 hover:underline" @click="removeOption(oi)">Remove</button>
                    </div>

                    <div class="mt-3 space-y-2">
                        <div v-for="(v, vi) in o.values" :key="vi" class="rounded-lg border border-paper-300 bg-white">
                            <div class="grid grid-cols-[auto_1fr_auto_auto_auto_auto] items-center gap-2 p-2">
                                <input type="radio" :checked="v.isDefault" class="h-4 w-4 justify-self-center" title="Default value" @change="setDefaultValue(oi, vi)" />
                                <input v-model="v.label" placeholder="e.g. A4 / Matte" class="border border-ink/20 px-2.5 py-1.5 text-sm focus:border-brand-600 focus:outline-none" />
                                <div class="flex items-center" title="Price add-on"><span class="px-1 text-ink/40">$</span><input v-model.number="v.priceDelta" type="number" step="0.01" class="w-20 border border-ink/20 px-2 py-1.5 text-sm focus:border-brand-600 focus:outline-none" /></div>
                                <input v-if="o.type === 'swatch'" v-model="v.swatch" type="color" class="h-8 w-12 cursor-pointer border border-ink/20 p-0.5" />
                                <input v-else v-model="v.badge" placeholder="badge" class="w-24 border border-ink/20 px-2 py-1.5 text-sm focus:border-brand-600 focus:outline-none" />
                                <button class="rounded-md px-2.5 py-1.5 text-xs font-medium transition" :class="isOpen(oi, vi) || v.attributes.length || v.surfaceId ? 'bg-brand-50 text-brand-700' : 'text-ink/50 hover:bg-paper-200'" title="Specs &amp; surface" @click="toggleValue(oi, vi)">
                                    Details<span v-if="v.attributes.length"> · {{ v.attributes.length }}</span>
                                </button>
                                <button class="px-2 text-ink/40 hover:text-red-600" title="Remove value" @click="removeValue(oi, vi)">✕</button>
                            </div>
                            <div v-if="isOpen(oi, vi)" class="border-t border-paper-200 bg-paper-200/40 p-3">
                                <p class="mb-1.5 text-[11px] font-semibold uppercase tracking-wide text-ink/45">Specs — weight, thickness, width/height…</p>
                                <div v-for="(a, ai) in v.attributes" :key="ai" class="mb-1.5 flex items-center gap-2">
                                    <input v-model="a.name" placeholder="Name (e.g. Weight)" class="w-44 border border-ink/20 bg-white px-2 py-1.5 text-sm focus:border-brand-600 focus:outline-none" />
                                    <input v-model="a.value" placeholder="Value (e.g. 350gsm)" class="flex-1 border border-ink/20 bg-white px-2 py-1.5 text-sm focus:border-brand-600 focus:outline-none" />
                                    <button class="px-2 text-ink/40 hover:text-red-600" title="Remove spec" @click="removeAttr(oi, vi, ai)">✕</button>
                                </div>
                                <button class="text-sm font-medium text-brand-700 hover:underline" @click="addAttr(oi, vi)">+ Add spec</button>
                                <div class="mt-3 border-t border-paper-200 pt-3">
                                    <label class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-ink/45">Designer surface for this value</label>
                                    <select v-model="v.surfaceId" class="w-full max-w-xs border border-ink/20 bg-white px-2 py-1.5 text-sm focus:border-brand-600 focus:outline-none">
                                        <option :value="null">— Use product default —</option>
                                        <option v-for="s in surfaces" :key="s.id" :value="s.id">{{ s.name }}</option>
                                    </select>
                                    <p class="mt-1 text-xs text-ink/45">e.g. a Format value like “A4” can point to the matching surface.</p>
                                </div>
                            </div>
                        </div>
                        <button class="mt-1 text-sm font-medium text-brand-700 hover:underline" @click="addValue(oi)">+ Add value</button>
                    </div>
                </div>
            </section>

            <!-- pricing -->
            <section class="rounded-2xl border border-paper-300 bg-white p-6 shadow-sm">
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <h2 class="font-display text-base font-semibold text-ink">Pricing tiers</h2>
                        <p class="text-sm text-ink/50">Total price per quantity. Option add-ons are added on top at checkout.</p>
                    </div>
                    <button class="rounded-full bg-brand-50 px-4 py-1.5 text-sm font-semibold text-brand-700 transition hover:bg-brand-100" @click="addTier">+ Tier</button>
                </div>

                <div v-if="!form.quantities.length" class="rounded-xl border border-dashed border-paper-300 py-8 text-center text-sm text-ink/50">No pricing tiers yet — add at least one quantity + price.</div>

                <div v-else class="overflow-hidden rounded-xl border border-paper-300">
                    <table class="w-full text-sm">
                        <thead class="bg-paper-200 text-left text-[11px] font-semibold uppercase tracking-wide text-ink/45">
                            <tr><th class="px-4 py-2.5">Default</th><th class="px-4 py-2.5">Quantity</th><th class="px-4 py-2.5">Total price</th><th class="px-4 py-2.5">Per unit</th><th class="px-4 py-2.5"></th></tr>
                        </thead>
                        <tbody class="divide-y divide-paper-200">
                            <tr v-for="(t, ti) in form.quantities" :key="ti">
                                <td class="px-4 py-2"><input type="radio" :checked="t.isDefault" class="h-4 w-4" @change="setDefaultTier(ti)" /></td>
                                <td class="px-4 py-2"><input v-model.number="t.quantity" type="number" min="1" class="w-24 border border-ink/20 px-2 py-1.5 text-sm focus:border-brand-600 focus:outline-none" /></td>
                                <td class="px-4 py-2"><div class="flex items-center"><span class="px-1 text-ink/40">$</span><input v-model.number="t.totalPrice" type="number" step="0.01" min="0" class="w-28 border border-ink/20 px-2 py-1.5 text-sm focus:border-brand-600 focus:outline-none" /></div></td>
                                <td class="px-4 py-2 text-ink/55">{{ money(perUnit(t)) }} ea</td>
                                <td class="px-4 py-2 text-right"><button class="px-2 text-ink/40 hover:text-red-600" title="Remove tier" @click="removeTier(ti)">✕</button></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <div class="flex items-center justify-between">
                <button class="text-sm font-medium text-red-600 hover:underline" @click="destroy">Delete product</button>
                <button :disabled="form.processing" class="rounded-full bg-brand-600 px-6 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-700 disabled:opacity-60" @click="save">{{ form.processing ? 'Saving…' : 'Save changes' }}</button>
            </div>
        </div>
    </AdminLayout>
</template>
