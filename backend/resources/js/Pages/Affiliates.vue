<script setup>
import { ref, computed, onMounted } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import StoreLayout from '../Layouts/StoreLayout.vue';
import SmartImage from '../Components/SmartImage.vue';

const props = defineProps({
    heroImage: { type: String, default: null },
    showcaseImage: { type: String, default: null },
    demoKey: { type: String, default: null },
});

// ---- live hero demo: the REAL widget, rendering against the real engine ----
const demoHost = ref(null);
const demoStarted = ref(false);
onMounted(() => {
    if (!props.demoKey || !demoHost.value) return;
    const el = document.createElement('div');
    el.setAttribute('data-rmp-affiliate', props.demoKey);
    // a committed sample logo — public on this origin, so the engine can fetch it
    el.setAttribute('data-logo-url', `${window.location.origin}/storage/logo-samples/bakery.svg`);
    demoHost.value.appendChild(el);
    const s = document.createElement('script');
    s.src = '/affiliate-widget.js';
    s.async = true;
    document.head.appendChild(s);
    demoStarted.value = true;
});

// ---- earnings calculator ----------------------------------------------------
const monthly = ref(250000);
const fmt = (n) => n >= 1000000 ? (n / 1000000) + 'M' : (n / 1000) + 'k';
const low = computed(() => (monthly.value / 1000 * 15).toLocaleString(undefined, { maximumFractionDigits: 0 }));
const high = computed(() => (monthly.value / 1000 * 20).toLocaleString(undefined, { maximumFractionDigits: 0 }));

// ---- application ------------------------------------------------------------
const form = useForm({ name: '', company: '', email: '', website: '' });
const sent = ref(false);
const apply = () => form.post('/affiliates/apply', { preserveScroll: true, onSuccess: () => (sent.value = true) });

const snippet = `<script async src="https://www.runmyprint.com/affiliate-widget.js"><\/script>
<div data-rmp-affiliate="YOUR_KEY"
     data-logo-url="https://yourapp.com/user/logo.png"></div>`;

const faq = [
    { q: 'How are impressions counted?', a: 'One impression is counted when the ad has finished rendering the visitor\'s products AND at least half of it is on screen. Blank or below-the-fold units never count — you are paid for ads people actually saw.' },
    { q: 'When and how do we get paid?', a: 'Monthly, once your balance passes $50. Your dashboard row shows impressions, the exact amount earned and what is still owed at any moment.' },
    { q: 'What do we pass to the widget?', a: 'One attribute: a public URL of your user\'s logo (data-logo-url) or their website address (data-website). No personal data, no cookies from us, no tracking scripts beyond the impression counter.' },
    { q: 'What does the ad look like?', a: 'A quiet card in your page\'s flow: a personalized ad showing real products made for that visitor, one line of copy and a button. You saw a live one at the top of this page.' },
    { q: 'Who is this for?', a: 'Products whose users run a business: site builders, business-formation tools, hosting panels, directory listings, invoicing apps. If your users have a brand or a website, you can serve them personalized ads.' },
    { q: 'Why $15–20 and not one number?', a: 'New partners start at $15 CPM. Placements that consistently convert to orders move to $20 — we review monthly and only ever move rates up.' },
];

const steps = [
    { n: '1', title: 'Paste two lines', text: 'The script tag and one div. No SDK, no build step, no account linking — the widget is 4 KB of vanilla JavaScript.' },
    { n: '2', title: 'Point it at your user data', text: 'Your users already added a brand image or a website in your product. Hand the widget that URL and our print engine does the rest.' },
    { n: '3', title: 'Each visitor sees a personalized ad', text: 'Business cards, shirts, mugs, totes carrying their own brand — rendered server-side in about 30 seconds, as a clean ad card linking to our shop.' },
    { n: '4', title: 'You are paid per view', text: '$15–20 per 1000 viewable impressions, counted only when the rendered ad is actually on screen. Paid monthly.' },
];
</script>

<template>
    <Head title="Affiliate Program — Personalized Ads, $15–20 CPM" />
    <StoreLayout>
        <!-- hero: the pitch + the widget actually running -->
        <section class="relative isolate overflow-hidden bg-gradient-to-br from-navy via-navy to-navy-950 text-white">
            <svg class="pointer-events-none absolute inset-0 h-full w-full" viewBox="0 0 800 420" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
                <g stroke="#9cc6ff" fill="none" stroke-opacity="0.14">
                    <path d="M-20 90 H240 a10 10 0 0 1 10 10 V330 H820" stroke-dasharray="3 6" />
                    <path d="M-20 200 H820" stroke-dasharray="3 6" />
                </g>
                <g stroke="#ffffff" stroke-opacity="0.2" stroke-width="1.5" stroke-linecap="round">
                    <path d="M30 16v14M16 30h14" /><path d="M770 16v14M784 30h-14" /><path d="M30 404v-14M16 390h14" />
                </g>
            </svg>
            <div class="relative mx-auto grid max-w-7xl items-center gap-12 px-6 py-16 sm:px-8 sm:py-20 lg:grid-cols-[1.05fr_1fr]">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-widest text-[#9cc6ff]">Partner program</p>
                    <h1 class="mt-4 font-display text-4xl font-extrabold leading-[1.05] tracking-tight sm:text-5xl">
                        Personalized ads,<br />built for each visitor.<br /><span class="text-lime-accent">Paid per view.</span>
                    </h1>
                    <p class="mt-5 max-w-lg text-lg text-white/70">
                        Embed one widget. It builds a personalized ad for every visitor — their own brand, printed on
                        real products they can actually buy. We pay <strong class="text-lime-accent">$15–20 per 1000 views</strong>.
                    </p>
                    <!-- the integration IS this small — show it -->
                    <div class="mt-7 max-w-lg overflow-hidden rounded-xl border border-white/15 bg-[#0d1523] shadow-2xl shadow-black/40">
                        <div class="flex items-center gap-1.5 border-b border-white/10 px-4 py-2.5">
                            <span class="h-2.5 w-2.5 rounded-full bg-white/15"></span><span class="h-2.5 w-2.5 rounded-full bg-white/15"></span><span class="h-2.5 w-2.5 rounded-full bg-white/15"></span>
                            <span class="ml-2 text-[11px] font-medium uppercase tracking-wider text-white/40">the whole integration</span>
                        </div>
                        <pre class="overflow-x-auto px-4 py-3.5 text-[12.5px] leading-relaxed text-[#9cc6ff]"><code>{{ snippet }}</code></pre>
                    </div>
                    <div class="mt-7 flex flex-wrap items-center gap-4">
                        <a href="#apply" class="rounded-full bg-brand-blue px-8 py-4 font-semibold text-white shadow-lg shadow-brand-blue/30 transition hover:bg-[#2f78e0]">Apply to join</a>
                        <ul class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-white/65">
                            <li>✓ Viewable impressions only</li><li>✓ Paid monthly</li><li>✓ No exclusivity</li>
                        </ul>
                    </div>
                </div>
                <div>
                    <div class="overflow-hidden rounded-2xl border border-white/10 bg-white shadow-2xl shadow-black/40">
                        <div class="flex items-center justify-between border-b border-paper-300 bg-paper-200 px-4 py-2.5">
                            <div class="flex items-center gap-1.5">
                                <span class="h-2.5 w-2.5 rounded-full bg-[#e8554d]"></span><span class="h-2.5 w-2.5 rounded-full bg-[#e8b04b]"></span><span class="h-2.5 w-2.5 rounded-full bg-[#57bb63]"></span>
                            </div>
                            <span class="text-[11px] text-ink/40">yourapp.com — a page with the widget on it</span>
                            <span class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-brand-700">
                                <span class="inline-block h-1.5 w-1.5 animate-pulse rounded-full bg-brand-blue"></span> live
                            </span>
                        </div>
                        <div class="p-4">
                            <div class="mb-3 space-y-2" aria-hidden="true">
                                <div class="h-2.5 w-2/5 rounded bg-paper-300"></div>
                                <div class="h-2.5 w-3/5 rounded bg-paper-200"></div>
                            </div>
                            <div ref="demoHost">
                                <div v-if="!demoStarted" class="rounded-xl border border-paper-300 bg-paper-200/60 p-10 text-center text-sm text-ink/45">
                                    Widget demo loads on the live site.
                                </div>
                            </div>
                            <p class="mt-3 text-center text-xs text-ink/45">
                                This is the real widget building a personalized ad through our print engine right now — first products in ~30 s.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- money strip -->
        <section class="border-b border-paper-300 bg-paper-200/60">
            <div class="mx-auto grid max-w-7xl grid-cols-2 gap-6 px-6 py-8 sm:px-8 md:grid-cols-4">
                <div class="text-center"><p class="font-display text-2xl font-bold text-brand-700">$15–20</p><p class="mt-0.5 text-sm text-ink/55">per 1000 viewable impressions</p></div>
                <div class="text-center"><p class="font-display text-2xl font-bold text-brand-700">2 lines</p><p class="mt-0.5 text-sm text-ink/55">of code to integrate</p></div>
                <div class="text-center"><p class="font-display text-2xl font-bold text-brand-700">~30 s</p><p class="mt-0.5 text-sm text-ink/55">to render a visitor's products</p></div>
                <div class="text-center"><p class="font-display text-2xl font-bold text-brand-700">Monthly</p><p class="mt-0.5 text-sm text-ink/55">payouts, $50 minimum</p></div>
            </div>
        </section>

        <!-- how it works: a real pipeline, in order -->
        <section class="mx-auto max-w-7xl px-6 py-14 sm:px-8 sm:py-16">
            <div class="max-w-2xl">
                <p class="text-sm font-semibold uppercase tracking-widest text-brand-600">How it works</p>
                <h2 class="mt-2 font-display text-3xl font-bold tracking-tight sm:text-4xl">From embed to revenue in four steps</h2>
            </div>
            <div class="mt-9 grid gap-5 md:grid-cols-2 lg:grid-cols-4">
                <div v-for="s in steps" :key="s.n" class="rounded-2xl border border-paper-300 bg-white p-6 shadow-sm">
                    <span class="grid h-10 w-10 place-items-center rounded-full bg-brand-600 font-display text-lg font-bold text-white">{{ s.n }}</span>
                    <h3 class="mt-4 font-display text-lg font-semibold text-ink">{{ s.title }}</h3>
                    <p class="mt-1.5 text-sm leading-relaxed text-ink/60">{{ s.text }}</p>
                </div>
            </div>
        </section>

        <!-- what their users see -->
        <section class="bg-paper-200">
            <div class="mx-auto grid max-w-7xl items-center gap-10 px-6 py-14 sm:px-8 sm:py-16 lg:grid-cols-2">
                <div class="overflow-hidden rounded-3xl border border-paper-300 shadow-xl shadow-ink/10">
                    <SmartImage :src="heroImage" alt="One customer's logo across printed products — business cards, mug, shirt, tote and stickers" />
                </div>
                <div>
                    <p class="text-sm font-semibold uppercase tracking-widest text-brand-600">Why it converts</p>
                    <h2 class="mt-2 font-display text-3xl font-bold tracking-tight">It's not an ad for us. It's a preview of them.</h2>
                    <div class="mt-4 space-y-4 leading-relaxed text-ink/65">
                        <p>Generic banners get ignored. A personalized ad is different: it shows the visitor <em>their own brand</em>
                        on a stack of business cards and a shirt — the product photo of a thing they already want to exist.</p>
                        <p>Our print engine renders every ad server-side, per visitor. When they click through, the whole shop
                        stays personalized — their products follow them onto product pages, the designer and the cart.</p>
                        <p>You choose where the card sits: a dashboard sidebar, a "your brand" settings page, a post-signup screen.
                        It reads as a feature of your product, because for your users it is one.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- earnings calculator -->
        <section class="mx-auto max-w-7xl px-6 py-14 sm:px-8 sm:py-16">
            <div class="grid items-center gap-10 rounded-3xl bg-navy p-8 text-white sm:p-12 lg:grid-cols-2">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-widest text-[#9cc6ff]">Do the math</p>
                    <h2 class="mt-2 font-display text-3xl font-bold tracking-tight">What would your traffic earn?</h2>
                    <p class="mt-3 max-w-md text-white/65">Drag to your monthly widget impressions. New partners start at $15 CPM; converting placements move to $20.</p>
                    <input v-model.number="monthly" type="range" min="10000" max="2000000" step="10000"
                           class="mt-8 w-full accent-[#398aff]" aria-label="Monthly impressions" />
                    <p class="mt-2 text-sm text-white/55">{{ fmt(monthly) }} impressions / month</p>
                </div>
                <div class="rounded-2xl border border-white/15 bg-white/5 p-8 text-center">
                    <p class="text-sm uppercase tracking-widest text-white/50">Monthly payout</p>
                    <p class="mt-3 font-display text-5xl font-extrabold tracking-tight text-lime-accent">${{ low }}<span class="text-white/40"> – </span>${{ high }}</p>
                    <p class="mt-3 text-sm text-white/55">at $15–20 CPM, viewable impressions only</p>
                </div>
            </div>
        </section>

        <!-- placement illustration -->
        <section class="mx-auto max-w-7xl px-6 pb-14 sm:px-8">
            <div class="grid items-center gap-10 lg:grid-cols-2">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-widest text-brand-600">Fits your product</p>
                    <h2 class="mt-2 font-display text-3xl font-bold tracking-tight">A personalized ad in your layout, not a banner fighting it</h2>
                    <p class="mt-4 leading-relaxed text-ink/65">The widget inherits nothing and leaks nothing — one shadow-DOM card, sized to its container.
                    Put it where a "your brand" moment already happens in your product. If the engine can't render a visitor's
                    products, the widget shows nothing and you lose nothing.</p>
                </div>
                <div class="overflow-hidden rounded-3xl border border-paper-300 shadow-xl shadow-ink/10">
                    <SmartImage :src="showcaseImage" alt="The affiliate ad card embedded in a partner dashboard" />
                </div>
            </div>
        </section>

        <!-- FAQ -->
        <section class="mx-auto max-w-4xl px-6 pb-14 sm:px-8">
            <h2 class="font-display text-3xl font-bold tracking-tight">Partner questions</h2>
            <div class="mt-5 divide-y divide-paper-300 border-y border-paper-300">
                <details v-for="(f, i) in faq" :key="i" class="group py-4">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 text-[15px] font-medium text-ink">
                        {{ f.q }}
                        <svg class="h-5 w-5 shrink-0 text-ink/40 transition group-open:rotate-45" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M12 5v14M5 12h14" stroke-linecap="round" /></svg>
                    </summary>
                    <p class="mt-2.5 pr-8 text-sm leading-relaxed text-ink/60">{{ f.a }}</p>
                </details>
            </div>
        </section>

        <!-- apply -->
        <section id="apply" class="mx-auto max-w-4xl scroll-mt-24 px-6 pb-20 sm:px-8">
            <div class="rounded-3xl border border-paper-300 bg-white p-8 shadow-sm sm:p-10">
                <h2 class="font-display text-2xl font-bold tracking-tight sm:text-3xl">Apply to the program</h2>
                <p class="mt-2 max-w-xl text-ink/60">Tell us where the widget would live. We review within one business day and email your widget key — you could be earning this week.</p>

                <div v-if="sent" class="mt-6 rounded-2xl bg-emerald-50 px-5 py-6 text-emerald-800">
                    <p class="font-semibold">Application received.</p>
                    <p class="mt-1 text-sm">We'll email your widget key after review — usually within one business day.</p>
                </div>

                <form v-else class="mt-6 grid gap-4 sm:grid-cols-2" @submit.prevent="apply">
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-ink/55">Your name *</label>
                        <input v-model="form.name" required type="text" maxlength="120" class="mt-1.5 w-full rounded-xl border border-paper-300 bg-white px-3.5 py-2.5 text-sm focus:border-brand-400 focus:outline-none" />
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-ink/55">Company</label>
                        <input v-model="form.company" type="text" maxlength="160" class="mt-1.5 w-full rounded-xl border border-paper-300 bg-white px-3.5 py-2.5 text-sm focus:border-brand-400 focus:outline-none" />
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-ink/55">Work email *</label>
                        <input v-model="form.email" required type="email" maxlength="160" class="mt-1.5 w-full rounded-xl border border-paper-300 bg-white px-3.5 py-2.5 text-sm focus:border-brand-400 focus:outline-none" />
                        <p v-if="form.errors.email" class="mt-1 text-xs text-red-600">{{ form.errors.email }}</p>
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-ink/55">Where will the widget live? *</label>
                        <input v-model="form.website" required type="text" maxlength="200" placeholder="yourapp.com" class="mt-1.5 w-full rounded-xl border border-paper-300 bg-white px-3.5 py-2.5 text-sm focus:border-brand-400 focus:outline-none" />
                        <p v-if="form.errors.website" class="mt-1 text-xs text-red-600">{{ form.errors.website }}</p>
                    </div>
                    <div class="sm:col-span-2">
                        <button :disabled="form.processing" class="rounded-full bg-brand-blue px-8 py-3.5 font-semibold text-white shadow-lg shadow-brand-blue/30 transition hover:bg-[#2f78e0] disabled:opacity-60">
                            {{ form.processing ? 'Sending…' : 'Send application' }}
                        </button>
                    </div>
                </form>
            </div>
        </section>
    </StoreLayout>
</template>
