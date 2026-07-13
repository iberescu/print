<script setup>
import { ref, computed, onMounted, onBeforeUnmount, nextTick } from 'vue';
import { Head } from '@inertiajs/vue3';
import StoreLayout from '../Layouts/StoreLayout.vue';

const props = defineProps({
    pqsg: { type: Object, default: () => ({}) },
    useCases: { type: Object, default: () => ({}) },
});

// ---- payload building --------------------------------------------------------
const TYPES = [
    { key: 'url', label: 'Website' },
    { key: 'vcard', label: 'Contact card' },
    { key: 'email', label: 'Email' },
    { key: 'phone', label: 'Phone' },
];
const type = ref('url');
const f = ref({ url: '', name: '', company: '', phone: '', email: '', site: '', subject: '' });

const payload = computed(() => {
    const v = f.value;
    if (type.value === 'url') {
        const u = v.url.trim();
        return u ? (/^https?:\/\//i.test(u) ? u : 'https://' + u) : '';
    }
    if (type.value === 'email') {
        const e = v.email.trim();
        return e ? 'mailto:' + e + (v.subject.trim() ? '?subject=' + encodeURIComponent(v.subject.trim()) : '') : '';
    }
    if (type.value === 'phone') {
        const p = v.phone.trim();
        return p ? 'tel:' + p.replace(/[^\d+]/g, '') : '';
    }
    // vCard 3.0 — widely scannable
    if (!v.name.trim()) return '';
    const site = v.site.trim() ? (/^https?:\/\//i.test(v.site.trim()) ? v.site.trim() : 'https://' + v.site.trim()) : '';
    return [
        'BEGIN:VCARD', 'VERSION:3.0',
        'FN:' + v.name.trim(),
        v.company.trim() ? 'ORG:' + v.company.trim() : null,
        v.phone.trim() ? 'TEL:' + v.phone.trim() : null,
        v.email.trim() ? 'EMAIL:' + v.email.trim() : null,
        site ? 'URL:' + site : null,
        'END:VCARD',
    ].filter(Boolean).join('\n');
});

// ---- styling: module shape, foreground colour, centre logo --------------------
const STYLES = [
    { key: 'square', label: 'Square', icon: '/img/qr-style-square.webp' },
    { key: 'rounded', label: 'Rounded', icon: '/img/qr-style-rounded.webp' },
    { key: 'dots', label: 'Dots', icon: '/img/qr-style-dots.webp' },
];
const COLORS = ['000000', '2b3b55', '1d4ed8', '166534', '7f1d1d'];
const style = ref('square');
const color = ref('000000');
const logoUrl = ref(null);   // public URL of the uploaded logo (server holds it in the session)
const logoV = ref(0);        // bumps preview URLs so the browser refetches after (re)upload
const logoBusy = ref(false);
const logoInput = ref(null);

const xsrf = () => decodeURIComponent((document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/) || [])[1] || '');

async function uploadLogo(e) {
    const file = e.target.files?.[0];
    e.target.value = '';
    if (!file || logoBusy.value) return;
    logoBusy.value = true;
    try {
        const fd = new FormData();
        fd.append('logo', file);
        const r = await fetch('/qr/logo', { method: 'POST', headers: { Accept: 'application/json', 'X-XSRF-TOKEN': xsrf() }, body: fd });
        if (!r.ok) throw new Error('upload failed');
        logoUrl.value = (await r.json()).url;
        logoV.value++;
    } catch (err) {
        alert('Could not read that image — try a PNG or JPG under 2 MB.');
    } finally { logoBusy.value = false; }
}

const styleParams = computed(() => {
    let p = `&style=${style.value}&color=${color.value}`;
    if (logoUrl.value) p += `&logo=1&v=${logoV.value}`;
    return p;
});

const previewSrc = computed(() => payload.value
    ? `/qr/image?data=${encodeURIComponent(payload.value)}&format=svg&size=480${styleParams.value}`
    : null);

// ---- downloads (server attachment — anchor downloads navigate away on iOS) ----
// A fresh brand capture gets a 10 s "preparing" hold before the file lands:
// the engine gets a head start and the gallery below builds while they wait.
// Re-downloading the same code skips the theater — nothing new is coming.
const preparing = ref(null); // 'svg' | 'png' while the hold runs
let prepareTimer = null;

function download(format) {
    if (!payload.value || preparing.value) return;
    const fresh = brandSig() !== null && brandSig() !== lastCaptureSig;
    registerCapture();
    const href = `/qr/image?data=${encodeURIComponent(payload.value)}&format=${format}&size=1200&download=1${styleParams.value}`;
    if (!fresh) return window.location.assign(href);
    preparing.value = format;
    prepareTimer = setTimeout(() => {
        preparing.value = null;
        window.location.assign(href);
    }, 10000);
}
onBeforeUnmount(() => { if (prepareTimer) clearTimeout(prepareTimer); });

// ---- hand the brand signal to the upsell engine, then show the gallery --------
// Every download sends the CURRENT url/logo — only exact repeats are skipped
// (SVG then PNG of the same code shouldn't rebuild an identical gallery).
const galleryKey = ref(null);
const galleryItems = ref([]);   // [{key, img, label, product}] streamed from /pqsg/feed
const galleryWaiting = ref(true);
const galleryEmpty = ref(false);
let pqsgTimer = null;
let lastCaptureSig = null;

/** The QR itself is the signal — the QR image (+ centre logo, if any) goes on the
 *  products. Null only before there's a QR; the same QR+logo+style is deduped. */
function brandSig() {
    if (!payload.value) return null;
    return [payload.value, logoUrl.value ? 'L' + logoV.value : '', style.value, color.value].join('|');
}

async function registerCapture() {
    const sig = brandSig();
    if (sig === null || sig === lastCaptureSig) return; // no QR yet, or same QR already building
    lastCaptureSig = sig;
    try {
        // Send the RENDERED QR image so the engine can place it on the products.
        const qrRes = await fetch(`/qr/image?data=${encodeURIComponent(payload.value)}&format=png&size=1200${styleParams.value}`, { headers: { Accept: 'image/png' } });
        const qrBlob = await qrRes.blob();
        const v = f.value;
        const fd = new FormData();
        fd.append('qr', qrBlob, 'qr.png');
        const url = type.value === 'url' ? v.url.trim() : (type.value === 'vcard' ? v.site.trim() : '');
        const email = type.value === 'email' ? v.email.trim() : (type.value === 'vcard' ? v.email.trim() : '');
        if (url) fd.append('url', url);
        if (email) fd.append('email', email);
        if (logoUrl.value) fd.append('logo', '1'); // centre logo → logo on the merch products too

        const r = await fetch('/qr/capture', {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-XSRF-TOKEN': xsrf() }, // no Content-Type — the browser sets the multipart boundary
            body: fd,
        });
        const { key } = await r.json();
        if (key) {
            galleryKey.value = key;
            galleryItems.value = [];
            galleryWaiting.value = true;
            galleryEmpty.value = false;
            nextTick(() => initGallery(key));
            setTimeout(() => document.getElementById('qr-gallery')?.scrollIntoView({ behavior: 'smooth', block: 'start' }), 250);
        } else {
            lastCaptureSig = null;
        }
    } catch (e) { lastCaptureSig = null; /* the gallery is a bonus — downloads still work; retry on next press */ }
}

// Same approach as the designer / logo-maker "your logo on products": poll our
// internal feed proxy and render native product cards as the engine finishes each.
function initGallery(key) {
    if (pqsgTimer) { clearTimeout(pqsgTimer); pqsgTimer = null; } // a rebrand mid-poll must not race two polls
    galleryItems.value = [];
    galleryWaiting.value = true;
    galleryEmpty.value = false;

    const deadline = Date.now() + 4 * 60 * 1000; // results stream ~1 min; give up quietly after 4
    const poll = async () => {
        if (galleryKey.value !== key) return; // superseded by a newer QR
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
        pqsgTimer = setTimeout(poll, 1000);
    };
    poll();
}
onBeforeUnmount(() => { if (pqsgTimer) clearTimeout(pqsgTimer); });

// ---- SEO content ----------------------------------------------------------------
const steps = [
    { t: 'Pick what to share', d: 'A website link, a full contact card (vCard), a pre-addressed email or a tap-to-call phone number.' },
    { t: 'Make it yours', d: 'The code redraws live as you type. Pick rounded dots, a brand colour, or drop your logo in the middle.' },
    { t: 'Download free', d: 'Vector SVG for print or 1200 px PNG for screens. No account, no watermark, no expiry.' },
    { t: 'Put it on print', d: 'Add it to business cards, flyers, stickers or signs in our online designer — we print and ship.' },
];

const printUseCases = [
    {
        img: 'card', title: 'Business cards', href: '/product/standard-business-cards',
        d: 'A vCard QR on the back saves your name, number and email straight into their phone — no typing, no lost cards. 50 cards from $7.50.',
        cta: 'Print QR business cards',
    },
    {
        img: 'menu', title: 'Menus, flyers & table tents', href: '/category/marketing-materials',
        d: 'Link tables to your menu, booking page or weekly specials. Update the page behind the link anytime — the printed code keeps working.',
        cta: 'Browse marketing prints',
    },
    {
        img: 'sticker', title: 'Stickers & packaging labels', href: '/category/stickers-labels',
        d: 'Seal boxes and jars with a QR sticker that sends buyers to care instructions, reordering or a review page. Rolls or sheets, any shape.',
        cta: 'Shop stickers & labels',
    },
    {
        img: 'signage', title: 'Posters, signs & windows', href: '/category/signs-banners',
        d: 'The SVG is vector, so the same code stays razor sharp from a shelf tag to a storefront window poster passers-by can scan from the sidewalk.',
        cta: 'See signs & posters',
    },
];

const faq = [
    { q: 'Is the QR code generator really free?', a: 'Yes — unlimited QR codes, free SVG and PNG downloads, no account and no watermark. You only pay if you print products with us.' },
    { q: 'Do the QR codes expire?', a: 'Never. These are static QR codes: the destination is encoded in the pattern itself, so they work forever and do not depend on our servers.' },
    { q: 'What can a QR code point to?', a: 'Your website, a full contact card (vCard) that saves straight into the phone, a pre-addressed email, or a tap-to-call phone number.' },
    { q: 'What is a vCard QR code?', a: 'A QR code that contains a complete contact card — name, company, phone, email and website. Scanning it opens “Add to contacts” on the phone, filled in and ready to save. It is the single most useful thing to print on the back of a business card.' },
    { q: 'Which file should I use for print?', a: 'The SVG — it is a true vector, so it stays razor sharp at any size, from a business card corner to a poster. The PNG (1200 px) is great for screens and documents.' },
    { q: 'Will it scan reliably?', a: 'Yes — print it at least 2 × 2 cm (about 0.8 in) with good contrast and a clear margin around it, and any modern phone camera reads it instantly. Always test-scan a proof before a big print run.' },
    { q: 'Can I make a colored or branded QR code?', a: 'Yes — choose square, rounded or dot modules, pick any colour, and upload your logo to sit in the middle of the code. When a logo is added we raise the error correction to level H, so the pattern stays scannable. One rule: keep the code dark on a light background.' },
    { q: 'Static vs dynamic QR codes — which is this?', a: 'Static. The destination is baked into the pattern, which is why it is free forever with no subscription. The trade-off: the encoded link itself cannot be edited after printing — so point it at a page you control and change that page whenever you like.' },
    { q: 'Can I track how many people scan my code?', a: 'Static codes do not report scans by themselves. Add UTM parameters to the link you encode (e.g. ?utm_source=qr&utm_medium=print) and your analytics will count every scan as a visit.' },
    { q: 'Can I put my QR code on printed products?', a: 'That is what we do best. Business cards with QR codes, stickers, table tents, flyers, posters — design online (the editor has a built-in QR button) and we print and ship it.' },
];

let ldEl = null;
let prevDesc = null;
onMounted(() => {
    const meta = document.querySelector('meta[name="description"]');
    const desc = 'Free QR code generator: create website, vCard contact, email or phone QR codes and download print-ready SVG or PNG — no signup, no watermark, never expires. Then print it on business cards, flyers, stickers and signs.';
    if (meta) { prevDesc = meta.getAttribute('content'); meta.setAttribute('content', desc); }
    ldEl = document.createElement('script');
    ldEl.type = 'application/ld+json';
    ldEl.text = JSON.stringify([
        { '@context': 'https://schema.org', '@type': 'WebApplication', name: 'RunMyPrint QR Code Generator', applicationCategory: 'UtilitiesApplication', operatingSystem: 'Web', url: window.location.href, description: desc },
        { '@context': 'https://schema.org', '@type': 'HowTo', name: 'How to create a free QR code', step: steps.map((s, i) => ({ '@type': 'HowToStep', position: i + 1, name: s.t, text: s.d })) },
        { '@context': 'https://schema.org', '@type': 'FAQPage', mainEntity: faq.map((x) => ({ '@type': 'Question', name: x.q, acceptedAnswer: { '@type': 'Answer', text: x.a } })) },
    ]);
    document.head.appendChild(ldEl);
});
onBeforeUnmount(() => {
    if (ldEl) ldEl.remove();
    const meta = document.querySelector('meta[name="description"]');
    if (meta && prevDesc !== null) meta.setAttribute('content', prevDesc);
});

const inputCls = 'mt-1.5 w-full rounded-xl border border-paper-300 bg-white px-3.5 py-2.5 text-sm focus:border-brand-400 focus:outline-none';
</script>

<template>
    <Head title="Free QR Code Generator — Website, vCard, Email & Phone" />
    <StoreLayout>
        <!-- hero: the tool itself -->
        <section class="relative isolate overflow-hidden bg-gradient-to-br from-navy via-navy to-navy-950 text-white">
            <svg class="pointer-events-none absolute inset-0 h-full w-full" viewBox="0 0 800 420" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
                <g fill="#9cc6ff" fill-opacity="0.1">
                    <rect x="40" y="40" width="14" height="14"/><rect x="60" y="40" width="14" height="14"/><rect x="40" y="60" width="14" height="14"/>
                    <rect x="740" y="60" width="14" height="14"/><rect x="720" y="80" width="14" height="14"/><rect x="740" y="100" width="14" height="14"/>
                    <rect x="80" y="340" width="14" height="14"/><rect x="60" y="360" width="14" height="14"/><rect x="100" y="360" width="14" height="14"/>
                </g>
            </svg>
            <div class="relative mx-auto max-w-7xl px-6 py-12 sm:px-8 sm:py-16">
                <div class="max-w-2xl">
                    <p class="text-sm font-semibold uppercase tracking-widest text-[#9cc6ff]">Free QR code generator</p>
                    <h1 class="mt-3 font-display text-4xl font-extrabold leading-[1.05] tracking-tight sm:text-5xl">Make a QR code in seconds.<br />Print it on anything.</h1>
                    <p class="mt-4 max-w-lg text-lg text-white/70">Website, contact card, email or phone — with your logo in the middle, rounded dots or brand colours. Free vector SVG and high-res PNG, no signup, no watermark, never expires.</p>
                </div>

                <div class="mt-8 grid gap-6 rounded-3xl bg-white p-6 text-ink shadow-2xl shadow-black/30 sm:p-8 lg:grid-cols-[1fr_320px]">
                    <div>
                        <div class="flex flex-wrap gap-2">
                            <button v-for="t in TYPES" :key="t.key" type="button" @click="type = t.key"
                                    class="rounded-full border px-4 py-1.5 text-sm transition"
                                    :class="type === t.key ? 'border-brand-600 bg-brand-600 text-white' : 'border-paper-300 bg-white text-ink/70 hover:border-ink/30'">
                                {{ t.label }}
                            </button>
                        </div>

                        <div class="mt-5 grid gap-4 sm:grid-cols-2">
                            <template v-if="type === 'url'">
                                <div class="sm:col-span-2">
                                    <label class="text-xs font-semibold uppercase tracking-wide text-ink/55">Website address</label>
                                    <input v-model="f.url" type="text" placeholder="yourcompany.com" :class="inputCls" />
                                </div>
                            </template>
                            <template v-else-if="type === 'vcard'">
                                <div><label class="text-xs font-semibold uppercase tracking-wide text-ink/55">Full name *</label><input v-model="f.name" type="text" placeholder="Alex Carter" :class="inputCls" /></div>
                                <div><label class="text-xs font-semibold uppercase tracking-wide text-ink/55">Company</label><input v-model="f.company" type="text" :class="inputCls" /></div>
                                <div><label class="text-xs font-semibold uppercase tracking-wide text-ink/55">Phone</label><input v-model="f.phone" type="text" :class="inputCls" /></div>
                                <div><label class="text-xs font-semibold uppercase tracking-wide text-ink/55">Email</label><input v-model="f.email" type="email" :class="inputCls" /></div>
                                <div class="sm:col-span-2"><label class="text-xs font-semibold uppercase tracking-wide text-ink/55">Website</label><input v-model="f.site" type="text" placeholder="yourcompany.com" :class="inputCls" /></div>
                            </template>
                            <template v-else-if="type === 'email'">
                                <div><label class="text-xs font-semibold uppercase tracking-wide text-ink/55">Email address</label><input v-model="f.email" type="email" placeholder="hello@yourcompany.com" :class="inputCls" /></div>
                                <div><label class="text-xs font-semibold uppercase tracking-wide text-ink/55">Subject <span class="normal-case text-ink/35">(optional)</span></label><input v-model="f.subject" type="text" :class="inputCls" /></div>
                            </template>
                            <template v-else>
                                <div class="sm:col-span-2"><label class="text-xs font-semibold uppercase tracking-wide text-ink/55">Phone number</label><input v-model="f.phone" type="text" placeholder="+1 555 123 4567" :class="inputCls" /></div>
                            </template>
                        </div>

                        <!-- styling: shape, colour, centre logo -->
                        <div class="mt-5 flex flex-wrap items-center gap-x-5 gap-y-3 border-t border-paper-300 pt-4">
                            <div class="flex items-center gap-1.5">
                                <span class="mr-1 text-xs font-semibold uppercase tracking-wide text-ink/55">Style</span>
                                <button v-for="s in STYLES" :key="s.key" type="button" @click="style = s.key" :title="s.label"
                                        class="flex items-center gap-1.5 rounded-full border py-1 pl-1.5 pr-3 text-xs font-medium transition"
                                        :class="style === s.key ? 'border-brand-600 bg-brand-50 text-brand-700 ring-2 ring-brand-600/20' : 'border-paper-300 text-ink/60 hover:border-ink/30'">
                                    <img :src="s.icon" :alt="s.label + ' modules'" class="h-6 w-6 rounded" />
                                    {{ s.label }}
                                </button>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <span class="mr-1 text-xs font-semibold uppercase tracking-wide text-ink/55">Color</span>
                                <button v-for="c in COLORS" :key="c" type="button" @click="color = c" :aria-label="'#' + c"
                                        class="h-6 w-6 rounded-full border-2 transition"
                                        :class="color === c ? 'border-brand-blue ring-2 ring-brand-blue/30' : 'border-white shadow'"
                                        :style="{ backgroundColor: '#' + c }"></button>
                                <input :value="'#' + color" @input="color = $event.target.value.replace('#', '')" type="color"
                                       class="h-6 w-6 cursor-pointer rounded-full border border-paper-300 bg-white p-0.5" title="Custom color" />
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-semibold uppercase tracking-wide text-ink/55">Logo</span>
                                <button v-if="!logoUrl" type="button" :disabled="logoBusy" @click="logoInput.click()"
                                        class="rounded-full border border-dashed border-paper-300 px-3 py-1 text-xs font-medium text-ink/60 transition hover:border-ink/30 disabled:opacity-50">
                                    {{ logoBusy ? 'Uploading…' : '+ Add logo in the middle' }}
                                </button>
                                <template v-else>
                                    <img :src="logoUrl" alt="Your logo" class="h-7 w-7 rounded border border-paper-300 bg-white object-contain p-0.5" />
                                    <button type="button" class="text-xs text-ink/50 underline hover:text-ink" @click="logoInput.click()">Change</button>
                                    <button type="button" class="text-xs text-ink/50 underline hover:text-ink" @click="logoUrl = null">Remove</button>
                                </template>
                                <input ref="logoInput" type="file" accept="image/png,image/jpeg,image/webp,image/svg+xml" class="hidden" @change="uploadLogo" />
                            </div>
                        </div>

                        <div class="mt-6 flex flex-wrap gap-3">
                            <button type="button" :disabled="!payload || preparing" @click="download('svg')"
                                    class="rounded-full bg-brand-blue px-7 py-3 font-semibold text-white shadow-lg shadow-brand-blue/30 transition hover:bg-[#2f78e0] disabled:cursor-not-allowed disabled:opacity-40">
                                {{ preparing === 'svg' ? 'Preparing…' : '↓ Download SVG' }}
                            </button>
                            <button type="button" :disabled="!payload || preparing" @click="download('png')"
                                    class="rounded-full border border-brand-600 px-7 py-3 font-semibold text-brand-700 transition hover:bg-brand-50 disabled:cursor-not-allowed disabled:opacity-40">
                                {{ preparing === 'png' ? 'Preparing…' : '↓ Download PNG' }}
                            </button>
                        </div>
                        <p class="mt-3 text-xs text-ink/45">SVG scales to any print size. PNG exports at 1200 px. Static codes — they never expire.{{ logoUrl ? ' Logo added: error correction bumped to level H so it still scans.' : '' }}</p>
                    </div>

                    <div class="grid place-items-center rounded-2xl border border-paper-300 bg-paper-200/50 p-6">
                        <img v-if="previewSrc" :src="previewSrc" alt="Your QR code preview" class="w-full max-w-[260px]" />
                        <p v-else class="max-w-[220px] text-center text-sm text-ink/45">Fill in the details — your QR code appears here instantly.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- post-download gallery: their brand on products -->
        <section v-if="galleryKey || preparing" id="qr-gallery" class="mx-auto max-w-7xl scroll-mt-52 px-6 py-10 sm:px-8">
            <div class="rounded-3xl border border-paper-300 bg-paper-200/50 p-6 sm:p-8">
                <template v-if="preparing">
                    <div class="flex items-center justify-between gap-4">
                        <h2 class="font-display text-2xl font-bold tracking-tight sm:text-3xl">Preparing your file…</h2>
                        <span class="h-5 w-5 shrink-0 animate-spin rounded-full border-2 border-brand-blue border-t-transparent"></span>
                    </div>
                    <p class="mt-2 max-w-2xl text-ink/60">Your {{ preparing.toUpperCase() }} download starts in a few seconds — meanwhile we're placing your brand on real printed products below.</p>
                    <div class="mt-4 h-2 overflow-hidden rounded-full bg-white">
                        <div class="qr-prep-bar h-full rounded-full bg-brand-blue"></div>
                    </div>
                </template>
                <template v-else>
                    <h2 class="font-display text-2xl font-bold tracking-tight sm:text-3xl">🎉 Your QR is ready — now see your brand printed</h2>
                    <p class="mt-2 max-w-2xl text-ink/60">While you were downloading, we placed your brand on real products — QR business cards, stickers, apparel and more. Every mockup below is printable today.</p>
                </template>
                <div v-if="galleryWaiting" class="mt-6 grid place-items-center rounded-2xl border border-dashed border-paper-300 bg-white py-12 text-center">
                    <div>
                        <div class="mx-auto h-8 w-8 animate-spin rounded-full border-2 border-brand-600 border-t-transparent"></div>
                        <p class="mt-3 text-sm text-ink/55">Building previews from your site — first ones arrive in seconds.</p>
                    </div>
                </div>
                <p v-else-if="galleryEmpty" class="mt-6 rounded-2xl border border-paper-300 bg-white px-5 py-8 text-center text-sm text-ink/55">
                    We couldn't build previews this time — head to any product and add your QR in the designer.
                </p>
                <div v-show="!galleryWaiting && !galleryEmpty" class="mt-6 overflow-hidden rounded-2xl border border-paper-300 bg-white shadow-sm">
                    <div class="p-3 sm:p-4">
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                            <div v-for="it in galleryItems" :key="it.key" class="overflow-hidden rounded-xl border border-paper-300 bg-white">
                                <div class="aspect-square bg-white">
                                    <img :src="it.img" :alt="it.label || 'Your QR code on a product'" loading="lazy" class="h-full w-full object-cover" />
                                </div>
                                <p v-if="it.label" class="truncate px-2 py-1.5 text-center text-xs font-medium text-ink/70">{{ it.label }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- how it works -->
        <section class="mx-auto max-w-7xl px-6 py-14 sm:px-8">
            <h2 class="font-display text-3xl font-bold tracking-tight">How to make a QR code</h2>
            <p class="mt-2 max-w-2xl text-ink/60">Four steps, under a minute, nothing to install.</p>
            <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <div v-for="(s, i) in steps" :key="i" class="rounded-2xl border border-paper-300 bg-white p-6 shadow-sm">
                    <span class="grid h-9 w-9 place-items-center rounded-full bg-brand-blue/10 font-display text-sm font-bold text-brand-blue">{{ i + 1 }}</span>
                    <h3 class="mt-3.5 font-display text-lg font-semibold text-ink">{{ s.t }}</h3>
                    <p class="mt-1.5 text-sm leading-relaxed text-ink/60">{{ s.d }}</p>
                </div>
            </div>
        </section>

        <!-- put your QR on print (the reason this page exists) -->
        <section class="bg-paper-200/60">
            <div class="mx-auto max-w-7xl px-6 py-14 sm:px-8">
                <h2 class="font-display text-3xl font-bold tracking-tight">Put your QR code on print</h2>
                <p class="mt-2 max-w-2xl text-ink/60">A QR code in a folder does nothing. On a card, a table, a box or a window it works around the clock — and printing it is our whole job.</p>
                <div class="mt-8 grid gap-6 md:grid-cols-2">
                    <a v-for="u in printUseCases" :key="u.title" :href="u.href"
                       class="group overflow-hidden rounded-3xl border border-paper-300 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg">
                        <img v-if="useCases[u.img]" :src="useCases[u.img]" :alt="u.title + ' with a QR code'" loading="lazy"
                             class="aspect-[16/9] w-full object-cover transition duration-300 group-hover:scale-[1.02]" />
                        <div class="p-6">
                            <h3 class="font-display text-xl font-semibold text-ink">{{ u.title }}</h3>
                            <p class="mt-2 text-sm leading-relaxed text-ink/60">{{ u.d }}</p>
                            <span class="mt-3.5 inline-flex items-center gap-1.5 text-sm font-semibold text-brand-700">{{ u.cta }} <span aria-hidden="true" class="transition group-hover:translate-x-0.5">→</span></span>
                        </div>
                    </a>
                </div>
            </div>
        </section>

        <!-- built into the designer -->
        <section class="mx-auto max-w-7xl px-6 py-14 sm:px-8">
            <div class="flex flex-col items-start justify-between gap-6 rounded-3xl bg-navy p-8 text-white sm:p-10 lg:flex-row lg:items-center">
                <div class="max-w-2xl">
                    <p class="text-sm font-semibold uppercase tracking-widest text-[#9cc6ff]">Built into the online designer</p>
                    <h2 class="mt-2 font-display text-2xl font-bold sm:text-3xl">No juggling files — add a QR while you design</h2>
                    <p class="mt-2.5 text-white/65">Every product editor has a <span class="whitespace-nowrap rounded bg-white/10 px-1.5 py-0.5 font-medium text-white">▦ QR code</span> button: type the link or contact details, and the code drops onto your card, flyer or sticker already print-ready. Position it, resize it, done.</p>
                </div>
                <a href="/product/standard-business-cards" class="shrink-0 rounded-full bg-brand-blue px-8 py-3.5 font-semibold text-white shadow-lg shadow-brand-blue/40 transition hover:bg-[#2f78e0]">Open the designer</a>
            </div>
        </section>

        <!-- print know-how (SEO prose) -->
        <section class="mx-auto max-w-7xl px-6 pb-14 sm:px-8">
            <div class="grid gap-10 lg:grid-cols-[380px_1fr]">
                <div>
                    <h2 class="font-display text-3xl font-bold tracking-tight">QR codes that scan every time</h2>
                    <p class="mt-3 leading-relaxed text-ink/60">A QR code only earns its place on print if it scans on the first try. We print thousands of them — these four rules are what separate a code that works from one that gets ignored.</p>
                </div>
                <div class="grid gap-x-8 gap-y-6 sm:grid-cols-2">
                    <div>
                        <h3 class="font-display text-base font-semibold text-ink">Print it big enough</h3>
                        <p class="mt-1.5 text-sm leading-relaxed text-ink/60">2 × 2 cm (0.8 in) is the floor for arm's-length scans like business cards. For posters, size it for the distance: roughly one tenth of how far away people stand.</p>
                    </div>
                    <div>
                        <h3 class="font-display text-base font-semibold text-ink">Keep the contrast strong</h3>
                        <p class="mt-1.5 text-sm leading-relaxed text-ink/60">Phone cameras want a dark code on a light background. Black on white is bulletproof; light-on-dark and low-contrast brand colours are where scans start failing.</p>
                    </div>
                    <div>
                        <h3 class="font-display text-base font-semibold text-ink">Respect the quiet zone</h3>
                        <p class="mt-1.5 text-sm leading-relaxed text-ink/60">Leave a clear margin around the code — at least the width of four modules. Text or graphics crowding the edge is the most common reason a printed code will not read.</p>
                    </div>
                    <div>
                        <h3 class="font-display text-base font-semibold text-ink">Test before the full run</h3>
                        <p class="mt-1.5 text-sm leading-relaxed text-ink/60">Scan the design on screen and again on the first printed proof, with more than one phone. Ten seconds of testing protects a thousand printed pieces.</p>
                    </div>
                    <p class="text-sm leading-relaxed text-ink/60 sm:col-span-2">
                        One more thing worth knowing: the codes made here are <strong class="font-semibold text-ink">static</strong>. The destination is encoded in the pattern itself — no subscription, no middleman redirect, and the code never expires or breaks if we go to lunch. The flip side is that the encoded link can't be edited after printing, so point it at a page you control (your site, your menu page) and update that page as often as you like.
                    </p>
                </div>
            </div>
        </section>

        <!-- logo cross-sell -->
        <section class="mx-auto max-w-7xl px-6 pb-14 sm:px-8">
            <a href="/logo-maker" class="group flex flex-col items-start justify-between gap-4 rounded-3xl border border-paper-300 bg-white p-7 shadow-sm transition hover:shadow-md sm:flex-row sm:items-center">
                <div>
                    <h2 class="font-display text-xl font-semibold text-ink">Need a logo to go next to that QR code?</h2>
                    <p class="mt-1 text-sm text-ink/60">Our free AI logo maker designs a vector logo in seconds — pair them on your card, label or sign.</p>
                </div>
                <span class="shrink-0 rounded-full border border-brand-600 px-6 py-2.5 text-sm font-semibold text-brand-700 transition group-hover:bg-brand-50">Try the free logo maker →</span>
            </a>
        </section>

        <!-- FAQ -->
        <section class="mx-auto max-w-4xl px-6 pb-16 sm:px-8">
            <h2 class="font-display text-3xl font-bold tracking-tight">QR code generator — common questions</h2>
            <div class="mt-5 divide-y divide-paper-300 border-y border-paper-300">
                <details v-for="(x, i) in faq" :key="i" class="group py-4">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 text-[15px] font-medium text-ink">
                        {{ x.q }}
                        <svg class="h-5 w-5 shrink-0 text-ink/40 transition group-open:rotate-45" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M12 5v14M5 12h14" stroke-linecap="round" /></svg>
                    </summary>
                    <p class="mt-2.5 pr-8 text-sm leading-relaxed text-ink/60">{{ x.a }}</p>
                </details>
            </div>
            <div class="mt-10 rounded-3xl bg-navy p-8 text-center text-white sm:p-10">
                <h2 class="font-display text-2xl font-bold sm:text-3xl">Put your QR to work</h2>
                <p class="mx-auto mt-2 max-w-md text-white/65">Business cards with a scan-to-save contact, from $7.50 — designed online, printed by us.</p>
                <a href="/product/standard-business-cards" class="mt-6 inline-block rounded-full bg-brand-blue px-8 py-3.5 font-semibold text-white shadow-lg shadow-brand-blue/40 transition hover:bg-[#2f78e0]">Print QR business cards</a>
            </div>
        </section>
    </StoreLayout>
</template>

<style scoped>
.qr-prep-bar {
    width: 0;
    animation: qr-prep 10s linear forwards;
}
@keyframes qr-prep {
    to { width: 100%; }
}
</style>
