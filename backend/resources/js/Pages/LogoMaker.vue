<script setup>
import { Head } from '@inertiajs/vue3';
import { ref, onMounted, onBeforeUnmount, nextTick } from 'vue';
import StoreLayout from '../Layouts/StoreLayout.vue';
import LogoBuilder from '../Components/LogoBuilder.vue';
import SmartImage from '../Components/SmartImage.vue';
import { adsConversion } from '../lib/gads';

const props = defineProps({
    heroImage: { type: String, default: null },
    showcaseImage: { type: String, default: null },
    styles: { type: Array, default: () => [] },
    colors: { type: Array, default: () => [] },
    samples: { type: Array, default: () => [] },
    pqsg: { type: Object, default: () => ({}) },
});

const chosen = ref(null);
const galleryKey = ref(null);
const galleryItems = ref([]);   // [{key, img, label, product}] streamed from /pqsg/feed
const galleryWaiting = ref(true);
const galleryEmpty = ref(false);
const preparing = ref(false);   // 10s "preparing your file" hold before the download lands
let pqsgTimer = null;
let prepareTimer = null;
let lastUsedPath = null;        // re-downloading the same logo skips the hold

const xsrf = () => decodeURIComponent((document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/) || [])[1] || '');

// The server sends the file as Content-Disposition: attachment — browsers
// (including iOS Safari, which navigates away from anchor/blob downloads)
// show the save prompt and keep the page alive. The PNG rasterises
// server-side too: in-browser SVG→canvas fails silently on old iOS WebKit.
function downloadSvg(logo) {
    window.location.assign(`/logo-maker/download?path=${encodeURIComponent(logo.path)}`);
}

function downloadPng() {
    if (!chosen.value) return;
    window.location.assign(`/logo-maker/download?path=${encodeURIComponent(chosen.value.path)}&format=png`);
}

// Press "Download & continue": hand the logo to the upsell engine, move the user
// to the "your logo on products" section, and hold ~10s behind a "preparing your
// file" bar (the engine gets a head start and the gallery builds during the wait)
// before the file downloads. Re-downloading the same logo skips the theater.
async function useLogo(logo) {
    if (preparing.value) return; // a hold is already running
    const fresh = logo.path !== lastUsedPath;
    adsConversion('logo'); // ads conversion: the free-tool funnel's key event
    chosen.value = logo;

    if (!fresh) {
        // same logo already captured — no theater, just re-download and jump down
        document.getElementById('logo-gallery')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        return downloadSvg(logo);
    }
    lastUsedPath = logo.path;

    // start the hold now so the CSS bar and the JS timer stay in sync
    preparing.value = true;
    prepareTimer = setTimeout(() => { preparing.value = false; downloadSvg(logo); }, 10000);

    try {
        const r = await fetch('/logo-maker/finish', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': xsrf() },
            body: JSON.stringify({ path: logo.path }),
        });
        if (r.ok) {
            const { key } = await r.json();
            galleryKey.value = key;
            galleryWaiting.value = true;
            galleryEmpty.value = false;
            nextTick(() => initGallery(key));
        }
    } catch (e) { /* gallery is a bonus — the download still fires when the hold ends */ }

    nextTick(() => setTimeout(() => document.getElementById('logo-gallery')?.scrollIntoView({ behavior: 'smooth', block: 'start' }), 150));
}

// Same approach as the designer "your logo on products" step (Upsell.vue): poll
// our internal feed proxy and render native product cards as the engine finishes
// each mockup. The feed already returns the merch set filtered server-side.
function initGallery(key) {
    galleryItems.value = [];
    galleryWaiting.value = true;
    galleryEmpty.value = false;
    if (pqsgTimer) { clearTimeout(pqsgTimer); pqsgTimer = null; }

    const deadline = Date.now() + 4 * 60 * 1000; // results stream ~1 min; give up quietly after 4
    const poll = async () => {
        if (galleryKey.value !== key) return; // superseded by a newer selection
        let done = false;
        try {
            const r = await fetch(`/pqsg/feed/${key}?set=merch`, { headers: { Accept: 'application/json' } });
            const data = await r.json();
            done = !!data.done;
            if (data.images?.length) { galleryItems.value = data.images; galleryWaiting.value = false; }
        } catch (e) { /* best-effort */ }

        if (done || Date.now() > deadline) {
            galleryWaiting.value = false;
            if (!galleryItems.value.length) galleryEmpty.value = true;
            return;
        }
        pqsgTimer = setTimeout(poll, 1000); // 1s — show every result the moment it exists
    };
    poll();
}
onBeforeUnmount(() => { if (pqsgTimer) clearTimeout(pqsgTimer); if (prepareTimer) clearTimeout(prepareTimer); });

const faq = [
    { q: 'Is the AI logo maker really free?', a: 'Yes — generating logo concepts is completely free and unlimited. You only pay if you order printed products carrying your new logo.' },
    { q: 'What file formats do I get?', a: 'A true vector SVG plus a high-resolution PNG. Vectors scale to any size without losing sharpness — the same file works for a business card and a building sign — while the PNG drops straight into websites and social profiles.' },
    { q: 'Can I use the logo commercially?', a: 'Yes. Logos you generate are yours to use for your business — on your website, social profiles, packaging and anything you print with us.' },
    { q: 'How does the AI create my logo?', a: 'You describe your company, pick a style and colour direction, and our AI designs distinctive emblem-and-wordmark concepts in seconds. Generate as many variations as you like.' },
    { q: 'Can I put the logo on business cards, shirts or mugs?', a: 'Absolutely — that is what we do best. After you download your logo we instantly preview it on real products, and our online designer places it on business cards, apparel, stickers and more.' },
    { q: 'Can I edit the logo after generating it?', a: 'You can regenerate unlimited variations until it feels right, and the SVG file opens in any vector editor (Illustrator, Figma, Inkscape) for fine-tuning.' },
    { q: 'What makes a good logo brief?', a: 'Keep the company name short, describe the industry concretely ("artisan bakery" beats "food"), and pick the style closest to your brand personality. Two or three generations usually land a keeper.' },
];

const steps = [
    { n: '1', title: 'Describe your business', text: 'Company name, industry and an optional tagline — that is all the AI needs.' },
    { n: '2', title: 'Pick style & colours', text: 'Minimal to bold, brand blue to full colour — the AI designs around your direction.' },
    { n: '3', title: 'Explore & refine', text: 'Generate unlimited concepts free, and hit "more like this" to iterate on a favourite.' },
    { n: '4', title: 'Download & see it printed', text: 'Grab SVG + PNG files instantly — and watch your logo land on cards, shirts and mugs.' },
];

const stats = [
    { big: 'SVG + PNG', small: 'free brand-ready files' },
    { big: '~15 sec', small: 'per unique concept' },
    { big: 'Unlimited', small: 'free generations' },
    { big: '16 products', small: 'instant logo previews' },
];

const features = [
    { title: 'True vector SVG', text: 'Infinitely scalable files — crisp on a pen and razor-sharp on a banner.' },
    { title: 'Unlimited variations', text: 'Regenerate as often as you like until the concept feels right. No credits, no caps.' },
    { title: 'Print-ready by design', text: 'Clean flat shapes and solid colours that reproduce perfectly on paper, fabric and vinyl.' },
    { title: 'Straight onto products', text: 'One click previews your logo on business cards, apparel, stickers and more.' },
    { title: 'No design skills needed', text: 'Describe your business in plain words — the AI handles composition, type and colour.' },
    { title: 'Yours to keep', text: 'Full commercial use. Download the file and use it anywhere your brand lives.' },
];

// SEO: meta description + JSON-LD (WebApplication + FAQPage), Product.vue pattern.
let ldEl = null;
let prevDesc = null;
onMounted(() => {
    const meta = document.querySelector('meta[name="description"]');
    const desc = 'Free AI logo maker: generate professional vector logos in seconds, download the SVG and print it on business cards, shirts, mugs and more with RunMyPrint.';
    if (meta) { prevDesc = meta.getAttribute('content'); meta.setAttribute('content', desc); }
    ldEl = document.createElement('script');
    ldEl.type = 'application/ld+json';
    ldEl.text = JSON.stringify([
        {
            '@context': 'https://schema.org', '@type': 'WebApplication',
            name: 'RunMyPrint AI Logo Maker', applicationCategory: 'DesignApplication',
            operatingSystem: 'Web', url: window.location.href,
            offers: { '@type': 'Offer', price: '0', priceCurrency: 'USD', availability: 'https://schema.org/InStock' },
            description: desc,
        },
        {
            '@context': 'https://schema.org', '@type': 'FAQPage',
            mainEntity: faq.map((f) => ({ '@type': 'Question', name: f.q, acceptedAnswer: { '@type': 'Answer', text: f.a } })),
        },
    ]);
    document.head.appendChild(ldEl);
});
onBeforeUnmount(() => {
    if (ldEl) { ldEl.remove(); ldEl = null; }
    const meta = document.querySelector('meta[name="description"]');
    if (meta && prevDesc !== null) meta.setAttribute('content', prevDesc);
});
</script>

<template>
    <Head title="Free AI Logo Maker — Vector Logos in Seconds" />
    <StoreLayout>
        <!-- hero -->
        <section class="relative isolate overflow-hidden bg-gradient-to-br from-navy via-navy to-navy-950 text-white">
            <svg class="pointer-events-none absolute inset-0 h-full w-full" viewBox="0 0 800 420" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
                <g fill="none" stroke="#9cc6ff">
                    <circle cx="430" cy="-80" r="130" stroke-opacity="0.16" stroke-width="1.5" />
                    <circle cx="430" cy="-80" r="185" stroke-opacity="0.10" />
                    <circle cx="430" cy="-80" r="240" stroke-opacity="0.06" stroke-dasharray="4 6" />
                </g>
                <g stroke="#ffffff" stroke-opacity="0.22" stroke-width="1.5" stroke-linecap="round">
                    <path d="M30 16v14M16 30h14" /><path d="M770 16v14M784 30h-14" /><path d="M30 404v-14M16 390h14" />
                </g>
                <g>
                    <path d="M40 350C130 306 220 380 315 330" fill="none" stroke="#398aff" stroke-opacity="0.5" stroke-width="2" stroke-linecap="round" />
                    <rect x="36" y="346" width="8" height="8" fill="#0e1420" stroke="#9cc6ff" stroke-opacity="0.8" />
                    <rect x="311" y="326" width="8" height="8" fill="#0e1420" stroke="#9cc6ff" stroke-opacity="0.8" />
                    <circle cx="130" cy="306" r="2.6" fill="#398aff" />
                </g>
            </svg>
            <div class="relative mx-auto grid max-w-7xl items-center gap-10 px-6 py-16 sm:px-8 sm:py-20 lg:grid-cols-[1.05fr_1fr]">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-widest text-[#9cc6ff]">Free AI logo maker</p>
                    <h1 class="mt-4 font-display text-4xl font-extrabold leading-[1.05] tracking-tight sm:text-5xl">Your logo, designed by AI.<br />Printed by us.</h1>
                    <p class="mt-5 max-w-lg text-lg text-white/70">Describe your business and get professional vector logo concepts in seconds — free, unlimited, and ready to print on anything.</p>
                    <div class="mt-8 flex flex-wrap items-center gap-4">
                        <a href="#builder" class="rounded-full bg-brand-blue px-8 py-4 font-semibold text-white shadow-lg shadow-brand-blue/30 transition hover:bg-[#2f78e0]">Create my logo — free</a>
                        <ul class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-white/65">
                            <li>✓ Vector SVG</li><li>✓ Unlimited tries</li><li>✓ Commercial use</li>
                        </ul>
                    </div>
                </div>
                <div class="relative hidden overflow-hidden rounded-2xl border border-white/10 shadow-2xl shadow-black/30 lg:block">
                    <SmartImage :src="heroImage" alt="AI-generated logo presented on brand stationery" />
                </div>
            </div>
        </section>

        <!-- stats strip -->
        <section class="border-b border-paper-300 bg-paper-200/60">
            <div class="mx-auto grid max-w-7xl grid-cols-2 gap-6 px-6 py-8 sm:px-8 md:grid-cols-4">
                <div v-for="s in stats" :key="s.big" class="text-center">
                    <p class="font-display text-2xl font-bold text-brand-700">{{ s.big }}</p>
                    <p class="mt-0.5 text-sm text-ink/55">{{ s.small }}</p>
                </div>
            </div>
        </section>

        <!-- builder -->
        <section id="builder" class="mx-auto max-w-7xl scroll-mt-24 px-6 py-14 sm:px-8 sm:py-16">
            <div class="mb-8 max-w-2xl">
                <p class="text-sm font-semibold uppercase tracking-widest text-brand-600">Logo builder</p>
                <h2 class="mt-2 font-display text-3xl font-bold tracking-tight sm:text-4xl">Tell us about your business</h2>
                <p class="mt-2 text-ink/60">The more concrete the industry, the sharper the concepts. Generate as many variations as you like — it's free.</p>
            </div>
            <div class="rounded-3xl border border-paper-300 bg-white p-6 shadow-sm sm:p-8">
                <LogoBuilder :styles="styles" :colors="colors" use-label="Download & continue" @use="useLogo" />
            </div>
        </section>

        <!-- post-download gallery -->
        <section v-if="galleryKey || preparing" id="logo-gallery" class="mx-auto max-w-7xl scroll-mt-52 px-6 pb-14 sm:px-8">
            <div class="rounded-3xl border border-paper-300 bg-paper-200/50 p-6 sm:p-8">
                <template v-if="preparing">
                    <div class="flex items-center justify-between gap-4">
                        <h2 class="font-display text-2xl font-bold tracking-tight sm:text-3xl">Preparing your file…</h2>
                        <span class="h-5 w-5 shrink-0 animate-spin rounded-full border-2 border-brand-blue border-t-transparent"></span>
                    </div>
                    <p class="mt-2 max-w-2xl text-ink/60">Your download starts in a few seconds — meanwhile we're placing your new logo on real printed products below.</p>
                    <div class="mt-4 h-2 overflow-hidden rounded-full bg-white">
                        <div class="logo-prep-bar h-full rounded-full bg-brand-blue"></div>
                    </div>
                </template>
                <template v-else>
                    <h2 class="font-display text-2xl font-bold tracking-tight sm:text-3xl">🎉 Your logo is ready — see it on real products</h2>
                    <p class="mt-2 max-w-2xl text-ink/60">Your SVG is downloading. Meanwhile we're placing your new logo on business cards, apparel, drinkware and more — every mockup below is printable today.</p>
                </template>

                <div v-if="chosen && !preparing" class="mt-5 flex flex-wrap items-center gap-4 rounded-2xl border border-paper-300 bg-white p-4">
                    <img :src="chosen.url" alt="Your chosen logo" class="h-16 w-16 rounded-lg border border-paper-300 bg-white object-contain p-1.5" />
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold text-ink">Your download bundle</p>
                        <p class="text-xs text-ink/55">Vector SVG for print · high-res PNG for web and social</p>
                    </div>
                    <div class="flex gap-2">
                        <button class="rounded-full border border-brand-600 px-4 py-2 text-sm font-semibold text-brand-700 transition hover:bg-brand-50" @click="downloadSvg(chosen)">↓ SVG</button>
                        <button class="rounded-full border border-brand-600 px-4 py-2 text-sm font-semibold text-brand-700 transition hover:bg-brand-50" @click="downloadPng">↓ PNG</button>
                    </div>
                </div>
                <div v-if="galleryWaiting" class="mt-6 grid place-items-center rounded-2xl border border-dashed border-paper-300 bg-white py-12 text-center">
                    <div>
                        <div class="mx-auto h-8 w-8 animate-spin rounded-full border-2 border-brand-600 border-t-transparent"></div>
                        <p class="mt-3 text-sm text-ink/55">Placing your logo on products — first previews arrive in seconds.</p>
                    </div>
                </div>
                <p v-else-if="galleryEmpty" class="mt-6 rounded-2xl border border-paper-300 bg-white px-5 py-8 text-center text-sm text-ink/55">
                    We couldn't build previews this time — head to any product and upload your new logo in the designer.
                </p>
                <div v-show="!galleryWaiting && !galleryEmpty" class="mt-6 overflow-hidden rounded-2xl border border-paper-300 bg-white shadow-sm">
                    <div class="flex items-center justify-between gap-3 border-b border-paper-300 bg-paper-200/60 px-4 py-2.5">
                        <p class="text-xs font-semibold uppercase tracking-widest text-ink/50">Your logo, printed</p>
                        <span class="inline-flex items-center gap-1.5 text-[11px] font-medium text-brand-700">
                            <span class="inline-block h-1.5 w-1.5 animate-pulse rounded-full bg-brand-blue"></span>
                            generating live
                        </span>
                    </div>
                    <div class="p-3 sm:p-4">
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                            <div v-for="it in galleryItems" :key="it.key" class="overflow-hidden rounded-xl border border-paper-300 bg-white">
                                <div class="aspect-square bg-white">
                                    <img :src="it.img" :alt="it.label || 'Your logo on a product'" loading="lazy" class="h-full w-full object-cover" />
                                </div>
                                <p v-if="it.label" class="truncate px-2 py-1.5 text-center text-xs font-medium text-ink/70">{{ it.label }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- how it works -->
        <section class="bg-paper-200">
            <div class="mx-auto max-w-7xl px-6 py-14 sm:px-8 sm:py-16">
                <h2 class="font-display text-3xl font-bold tracking-tight sm:text-4xl">How the AI logo generator works</h2>
                <div class="mt-8 grid gap-5 md:grid-cols-3">
                    <div v-for="s in steps" :key="s.n" class="rounded-2xl border border-paper-300 bg-white p-6 shadow-sm">
                        <span class="grid h-10 w-10 place-items-center rounded-full bg-brand-600 font-display text-lg font-bold text-white">{{ s.n }}</span>
                        <h3 class="mt-4 font-display text-lg font-semibold text-ink">{{ s.title }}</h3>
                        <p class="mt-1.5 text-sm leading-relaxed text-ink/60">{{ s.text }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- industry examples: real output of this generator -->
        <section v-if="samples.length" class="mx-auto max-w-7xl px-6 py-14 sm:px-8 sm:py-16">
            <div class="mb-8 max-w-2xl">
                <p class="text-sm font-semibold uppercase tracking-widest text-brand-600">Made with this tool</p>
                <h2 class="mt-2 font-display text-3xl font-bold tracking-tight sm:text-4xl">Any business. Any style. Your logo.</h2>
                <p class="mt-2 text-ink/60">Every mark below came straight out of this generator — no retouching. From bakeries to consultancies, describe it and the AI designs for it.</p>
            </div>
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                <div v-for="s in samples" :key="s.label" class="group overflow-hidden rounded-2xl border border-paper-300 bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-lg">
                    <div class="aspect-square bg-white p-5">
                        <img :src="s.url" :alt="`AI-generated ${s.label} logo example`" loading="lazy" class="h-full w-full object-contain transition duration-300 group-hover:scale-105" />
                    </div>
                    <p class="border-t border-paper-300 bg-paper-200/50 py-2 text-center text-xs font-semibold uppercase tracking-wider text-ink/55">{{ s.label }}</p>
                </div>
            </div>
        </section>

        <!-- SEO prose + showcase -->
        <section class="bg-paper-200/60">
            <div class="mx-auto max-w-7xl px-6 py-14 sm:px-8 sm:py-16">
            <div class="grid items-center gap-10 lg:grid-cols-2">
                <div>
                    <h2 class="font-display text-3xl font-bold tracking-tight">A professional logo, without the agency price tag</h2>
                    <div class="mt-4 space-y-4 leading-relaxed text-ink/65">
                        <p>Your logo is the face of your business — it sits on every business card you hand out, every package you ship and every invoice you send. Our free AI logo maker gives small businesses what used to take a design agency weeks: distinctive, professional vector logos generated in seconds from a plain-language brief.</p>
                        <p>Unlike template libraries where thousands of businesses share the same mark, every logo here is generated fresh for your company name, industry and style. You get a true vector SVG file — the format print professionals use — so your logo stays razor sharp whether it's embroidered on a polo, printed on a 85×55&nbsp;mm card or stretched across a trade-show banner.</p>
                        <p>And because RunMyPrint is a print shop first, your new logo goes to work immediately: preview it on real products the moment you download it, drop it into our online designer, and have business cards, stickers, apparel or signage on your doorstep in days.</p>
                    </div>
                </div>
                <div class="overflow-hidden rounded-3xl border border-paper-300 shadow-xl shadow-ink/10">
                    <SmartImage :src="showcaseImage" alt="One logo applied across printed products — cards, mug, tote and apparel" />
                </div>
            </div>

            <div class="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <div v-for="f in features" :key="f.title" class="rounded-2xl border border-paper-300 bg-white p-5 shadow-sm">
                    <h3 class="flex items-center gap-2 font-semibold text-ink">
                        <svg class="h-4.5 w-4.5 shrink-0 text-brand-blue" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m5 13 4 4L19 7" stroke-linecap="round" stroke-linejoin="round" /></svg>
                        {{ f.title }}
                    </h3>
                    <p class="mt-1.5 text-sm leading-relaxed text-ink/60">{{ f.text }}</p>
                </div>
            </div>
            </div>
        </section>

        <!-- FAQ -->
        <section class="mx-auto max-w-4xl px-6 pb-16 sm:px-8">
            <h2 class="font-display text-3xl font-bold tracking-tight">AI logo maker — frequently asked questions</h2>
            <div class="mt-5 divide-y divide-paper-300 border-y border-paper-300">
                <details v-for="(f, i) in faq" :key="i" class="group py-4">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 text-[15px] font-medium text-ink">
                        {{ f.q }}
                        <svg class="h-5 w-5 shrink-0 text-ink/40 transition group-open:rotate-45" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M12 5v14M5 12h14" stroke-linecap="round" /></svg>
                    </summary>
                    <p class="mt-2.5 pr-8 text-sm leading-relaxed text-ink/60">{{ f.a }}</p>
                </details>
            </div>
            <div class="mt-10 rounded-3xl bg-navy p-8 text-center text-white sm:p-10">
                <h2 class="font-display text-2xl font-bold sm:text-3xl">Ready to meet your new logo?</h2>
                <p class="mx-auto mt-2 max-w-md text-white/65">Free, unlimited and yours to keep — from first idea to printed business cards in one sitting.</p>
                <a href="#builder" class="mt-6 inline-block rounded-full bg-brand-blue px-8 py-3.5 font-semibold text-white shadow-lg shadow-brand-blue/40 transition hover:bg-[#2f78e0]">Start designing free</a>
            </div>
        </section>
    </StoreLayout>
</template>

<style scoped>
.logo-prep-bar {
    width: 0;
    animation: logo-prep 10s linear forwards;
}
@keyframes logo-prep {
    to { width: 100%; }
}
</style>
