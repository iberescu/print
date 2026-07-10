<script setup>
import { Head, Link } from '@inertiajs/vue3';
import StoreLayout from '../Layouts/StoreLayout.vue';
import ProductCard from '../Components/ProductCard.vue';
import SmartImage from '../Components/SmartImage.vue';
import HeroSlider from '../Components/HeroSlider.vue';

const props = defineProps({
    categories: { type: Array, default: () => [] },
    featured: { type: Array, default: () => [] },
    shopBy: { type: Array, default: () => [] },
    heroImage: { type: String, default: null },
    priceGuaranteeImage: { type: String, default: null },
    freeShippingThreshold: { type: Number, default: 100 },
});

const slides = [
    { eyebrow: 'Premium custom printing', title: 'Everything to launch your brand', text: 'Business cards, flyers, signage, stickers, apparel and more — designed online, printed beautifully, delivered fast.', cta: 'Browse products', href: '#bestsellers', image: props.heroImage },
    { eyebrow: 'New customer offer', title: 'Business cards from $10', text: '500 premium cards — design online in minutes or upload your own artwork.', cta: 'Shop business cards', href: '/category/business-cards', image: props.heroImage },
    { eyebrow: `Free shipping over $${props.freeShippingThreshold}`, title: 'Your logo, on everything', text: 'Use our free online designer and 200+ ready-made templates.', cta: 'Start designing', href: '/product/standard-business-cards', image: props.heroImage },
];

// Bespoke tool icons (64×64). Shared blue-tinted tile; brand blues only — the
// lone lime element is the Logo-maker star (design spec). Static trusted markup.
const tile = `
    <rect x="3" y="3" width="58" height="58" rx="7" fill="#eef2f9"/>
    <rect x="3.75" y="3.75" width="56.5" height="56.5" rx="6.4" fill="none" stroke="#2b3b55" stroke-opacity=".09" stroke-width="1.5"/>
    <path d="M60 42a19 19 0 0 1-18 18.9" fill="none" stroke="#2b3b55" stroke-opacity=".08" stroke-width="1.5"/>`;

const tools = [
    {
        title: 'Design services',
        text: 'Let our experts design it for you.',
        // palette + pen, bezier with vector control points underneath
        icon: `${tile}
            <path d="M14 51C22 44 34 56 50 46" fill="none" stroke="#398aff" stroke-width="2" stroke-linecap="round" stroke-opacity=".9"/>
            <path d="M14 51l8-7M50 46l-16 10" stroke="#647ba0" stroke-dasharray="2.2 2.2" stroke-width="1"/>
            <rect x="11.8" y="48.8" width="4.4" height="4.4" fill="#fff" stroke="#2b3b55" stroke-width="1.3"/>
            <rect x="47.8" y="43.8" width="4.4" height="4.4" fill="#fff" stroke="#2b3b55" stroke-width="1.3"/>
            <circle cx="22" cy="44" r="1.7" fill="#398aff"/>
            <circle cx="34" cy="56" r="1.7" fill="#398aff"/>
            <path d="M29.5 13.5c-9.4 0-17 6.6-17 14.7 0 8.2 5.9 12.4 11.3 12.4 2.2 0 3.2-1.6 3.2-3.2 0-1.8 1.3-2.9 3.1-2.9h5c5.3 0 10.4-3.3 10.4-9.4 0-6.4-6.7-11.6-16-11.6z" fill="#2b3b55"/>
            <circle cx="20.5" cy="27.5" r="2.4" fill="#fff"/>
            <circle cx="25" cy="21" r="2.4" fill="#398aff"/>
            <circle cx="33" cy="19.5" r="2.4" fill="#9cc6ff"/>
            <circle cx="39.5" cy="23.5" r="2.4" fill="#647ba0"/>
            <g transform="rotate(38 44 26)">
                <rect x="40.7" y="8" width="6.6" height="21" rx="2" fill="#398aff"/>
                <rect x="40.7" y="12.5" width="6.6" height="2.6" fill="#fff" opacity=".4"/>
                <path d="M40.7 29h6.6L44 35.8z" fill="#2b3b55"/>
                <path d="M44 29v3.6" stroke="#fff" stroke-width="1" opacity=".65"/>
            </g>`,
    },
    {
        title: 'Logo maker',
        text: 'Create a logo in minutes.',
        href: '/logo-maker',
        // layered shield, inner keyline, lime star + sparkles
        icon: `${tile}
            <path d="M32 10.5 48 16.2v10.6c0 10.7-7 17.9-16 21-9-3.1-16-10.3-16-21V16.2z" fill="#2b3b55"/>
            <path d="M32 10.5 48 16.2v10.6c0 10.7-7 17.9-16 21z" fill="#1c2738"/>
            <path d="M32 14.4l12.6 4.5v8.4c0 8.5-5.6 14.4-12.6 17.1-7-2.7-12.6-8.6-12.6-17.1v-8.4z" fill="none" stroke="#9cc6ff" stroke-width="1.2" stroke-opacity=".45"/>
            <path d="M32 20.8l3 6 6.6.6-5 4.4 1.5 6.5-6.1-3.4-6.1 3.4 1.5-6.5-5-4.4 6.6-.6z" fill="#c7f23d"/>
            <path d="M32 20.8l3 6 6.6.6-9.6 3.4z" fill="#fff" opacity=".3"/>
            <path d="M14.5 13.5l1 2.3 2.3 1-2.3 1-1 2.3-1-2.3-2.3-1 2.3-1z" fill="#398aff"/>
            <circle cx="50" cy="43.5" r="1.6" fill="#9cc6ff"/>
            <circle cx="13.5" cy="40" r="1.2" fill="#647ba0"/>`,
    },
    {
        title: 'Free templates',
        text: '200+ professional designs.',
        // three stacked document panels, folded corner, layout blocks
        icon: `${tile}
            <rect x="31" y="12.5" width="18" height="25" rx="2.4" fill="#8fa7cc"/>
            <rect x="24" y="17" width="18" height="25" rx="2.4" fill="#647ba0"/>
            <rect x="25" y="18" width="16" height="23" rx="1.8" fill="none" stroke="#fff" stroke-opacity=".25"/>
            <path d="M19.4 22.5H30l5.6 5.6v18.4a2.4 2.4 0 0 1-2.4 2.4H19.4a2.4 2.4 0 0 1-2.4-2.4V24.9a2.4 2.4 0 0 1 2.4-2.4z" fill="#2b3b55"/>
            <path d="M30 22.5l5.6 5.6h-4a1.6 1.6 0 0 1-1.6-1.6z" fill="#398aff"/>
            <rect x="20.5" y="31" width="7.5" height="6" rx="1" fill="#398aff"/>
            <rect x="30.2" y="31" width="2.4" height="2.4" rx=".8" fill="#9cc6ff"/>
            <rect x="30.2" y="34.6" width="2.4" height="2.4" rx=".8" fill="#9cc6ff" opacity=".6"/>
            <rect x="20.5" y="40" width="11.5" height="2.2" rx="1.1" fill="#fff"/>
            <rect x="20.5" y="43.8" width="8" height="2.2" rx="1.1" fill="#fff" opacity=".55"/>`,
    },
    {
        title: 'Order samples',
        text: 'Feel the quality before you buy.',
        // fan of five swatches on a rivet, front swatch textured
        icon: `${tile}
            <g transform="rotate(-34 32 45)"><rect x="27.6" y="15.5" width="8.8" height="27.5" rx="2" fill="#16233b"/></g>
            <g transform="rotate(-17 32 45)"><rect x="27.6" y="15.5" width="8.8" height="27.5" rx="2" fill="#2b3b55"/></g>
            <rect x="27.6" y="15.5" width="8.8" height="27.5" rx="2" fill="#398aff"/>
            <rect x="29.7" y="19" width="4.6" height="1.6" rx=".8" fill="#fff" opacity=".55"/>
            <g transform="rotate(17 32 45)"><rect x="27.6" y="15.5" width="8.8" height="27.5" rx="2" fill="#9cc6ff"/></g>
            <g transform="rotate(34 32 45)">
                <rect x="27.6" y="15.5" width="8.8" height="27.5" rx="2" fill="#fff" stroke="#c9d6ea"/>
                <path d="M30 20.5h4M30 23.5h4M30 26.5h4" stroke="#647ba0" stroke-width="1" opacity=".6"/>
            </g>
            <circle cx="32" cy="45" r="3.2" fill="#16233b"/>
            <circle cx="32" cy="45" r="3.2" fill="none" stroke="#9cc6ff" stroke-opacity=".6"/>
            <circle cx="31.1" cy="44.1" r=".9" fill="#fff" opacity=".8"/>`,
    },
];
</script>

<template>
    <Head title="Custom Printing for Business" />
    <StoreLayout>
        <HeroSlider :slides="slides" />

        <!-- shop by product: one tile per popular product type -->
        <section v-if="shopBy.length" class="mx-auto max-w-7xl px-6 py-10 sm:px-8 sm:py-14">
            <div class="mb-6 flex items-end justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-widest text-brand-600">Shop by product</p>
                    <h2 class="mt-2 font-display text-2xl font-bold tracking-tight sm:text-3xl">What will you print today?</h2>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 sm:gap-5 md:grid-cols-4 lg:grid-cols-6">
                <Link
                    v-for="t in shopBy" :key="t.label" :href="t.href"
                    class="group flex flex-col overflow-hidden rounded-2xl border border-paper-300 bg-white shadow-sm transition duration-300 hover:-translate-y-1.5 hover:shadow-[0_28px_55px_-28px_rgba(43,59,85,0.55)]"
                >
                    <div class="aspect-square overflow-hidden bg-paper-200">
                        <SmartImage :src="t.image" :alt="t.label" class="transition duration-500 group-hover:scale-105" />
                    </div>
                    <div class="flex flex-1 flex-col p-3.5">
                        <h3 class="font-display text-sm font-semibold leading-snug text-ink sm:text-base">{{ t.label }}</h3>
                        <p class="mt-0.5 text-xs text-ink/60 sm:text-sm">
                            From <span class="font-semibold text-brand-700">${{ Number(t.fromPrice).toFixed(2) }}</span>
                        </p>
                    </div>
                </Link>
            </div>
        </section>

        <!-- bestselling products -->
        <section id="bestsellers" class="bg-paper-200">
            <div class="mx-auto max-w-7xl px-6 py-16 sm:px-8 sm:py-20">
                <div class="mb-9 flex items-end justify-between">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-widest text-brand-600">Most popular</p>
                        <h2 class="mt-2 font-display text-3xl font-bold tracking-tight sm:text-4xl">Bestselling products</h2>
                    </div>
                    <Link href="/category/business-cards" class="hidden text-sm font-semibold text-brand-700 hover:underline sm:block">See all →</Link>
                </div>
                <div class="grid grid-cols-2 gap-6 md:grid-cols-4">
                    <ProductCard v-for="p in featured" :key="p.slug" :product="p" />
                </div>
            </div>
        </section>

        <!-- samples / promo banner — print-studio look: crop marks, registration
             circle, halftone dots and a bezier with control points, all brand blue -->
        <section class="mx-auto max-w-7xl px-6 py-16 sm:px-8 sm:py-20">
            <div class="relative grid items-stretch overflow-hidden rounded-3xl bg-gradient-to-br from-navy via-navy to-navy-950 text-white shadow-2xl shadow-navy/20 md:grid-cols-2">
                <svg class="pointer-events-none absolute inset-0 h-full w-full" viewBox="0 0 800 420" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
                    <defs>
                        <radialGradient id="sampleGlow" cx="0.5" cy="0.5" r="0.5">
                            <stop offset="0" stop-color="#398aff" stop-opacity="0.22" />
                            <stop offset="1" stop-color="#398aff" stop-opacity="0" />
                        </radialGradient>
                        <pattern id="sampleDots" width="13" height="13" patternUnits="userSpaceOnUse">
                            <circle cx="2" cy="2" r="1.25" fill="#398aff" />
                        </pattern>
                        <linearGradient id="sampleDotsFade" x1="0" y1="1" x2="0.9" y2="0.1">
                            <stop offset="0" stop-color="#fff" stop-opacity="0.55" />
                            <stop offset="1" stop-color="#fff" stop-opacity="0" />
                        </linearGradient>
                        <mask id="sampleDotsMask"><rect x="0" y="225" width="270" height="195" fill="url(#sampleDotsFade)" /></mask>
                    </defs>
                    <circle cx="170" cy="120" r="270" fill="url(#sampleGlow)" />
                    <!-- concentric press cylinders, upper edge of the copy column -->
                    <g fill="none" stroke="#9cc6ff">
                        <circle cx="430" cy="-70" r="120" stroke-opacity="0.18" stroke-width="1.5" />
                        <circle cx="430" cy="-70" r="165" stroke-opacity="0.12" />
                        <circle cx="430" cy="-70" r="210" stroke-opacity="0.08" stroke-dasharray="4 6" />
                        <circle cx="430" cy="-70" r="255" stroke-opacity="0.05" />
                    </g>
                    <!-- halftone fade, bottom left -->
                    <rect x="0" y="225" width="270" height="195" fill="url(#sampleDots)" mask="url(#sampleDotsMask)" opacity="0.6" />
                    <!-- crop marks -->
                    <g stroke="#ffffff" stroke-opacity="0.25" stroke-width="1.5" stroke-linecap="round">
                        <path d="M30 14v14M14 30h14" />
                        <path d="M770 14v14M786 30h-14" />
                        <path d="M30 406v-14M14 390h14" />
                        <path d="M770 406v-14M786 390h-14" />
                    </g>
                    <!-- registration mark -->
                    <g transform="translate(335 345)" fill="none" stroke="#9cc6ff" stroke-opacity="0.4">
                        <circle r="11" />
                        <circle r="4" stroke-opacity="0.7" />
                        <path d="M-17 0h34M0 -17v34" />
                    </g>
                    <!-- bezier path with vector control points -->
                    <g>
                        <path d="M42 332C128 292 214 366 306 318" fill="none" stroke="#398aff" stroke-opacity="0.55" stroke-width="2" stroke-linecap="round" />
                        <path d="M42 332 128 292M306 318 214 366" stroke="#9cc6ff" stroke-opacity="0.35" stroke-dasharray="3 3" />
                        <rect x="38" y="328" width="8" height="8" fill="#0e1420" stroke="#9cc6ff" stroke-opacity="0.8" />
                        <rect x="302" y="314" width="8" height="8" fill="#0e1420" stroke="#9cc6ff" stroke-opacity="0.8" />
                        <circle cx="128" cy="292" r="2.6" fill="#398aff" />
                        <circle cx="214" cy="366" r="2.6" fill="#398aff" />
                    </g>
                </svg>

                <div class="relative p-10 sm:p-16">
                    <p class="text-sm font-semibold uppercase tracking-widest text-[#9cc6ff]">Not sure yet?</p>
                    <h2 class="mt-4 font-display text-3xl font-bold leading-tight sm:text-4xl">Order a free sample pack</h2>
                    <p class="mt-4 max-w-md text-white/70">Feel our paper stocks and finishes before you buy — premium quality you can hold.</p>
                    <ul class="mt-6 flex max-w-md flex-wrap gap-x-5 gap-y-2.5 text-sm text-white/75">
                        <li v-for="f in ['Premium paper stocks', 'Foil & soft-touch finishes', 'Shipped free']" :key="f" class="flex items-center gap-2">
                            <svg class="h-4 w-4 shrink-0" viewBox="0 0 16 16" aria-hidden="true">
                                <circle cx="8" cy="8" r="7" fill="none" stroke="#398aff" stroke-width="1.5" />
                                <path d="m5 8.2 2 2L11 6" fill="none" stroke="#9cc6ff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            {{ f }}
                        </li>
                    </ul>
                    <a href="#bestsellers" class="mt-8 inline-flex items-center gap-2 rounded-full bg-brand-blue px-8 py-4 font-semibold text-white shadow-lg shadow-brand-blue/30 transition hover:bg-[#2f78e0]">
                        Get free samples
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6" stroke-linecap="round" stroke-linejoin="round" /></svg>
                    </a>
                </div>

                <div class="relative aspect-[16/9] md:aspect-auto">
                    <SmartImage :src="heroImage" alt="Sample pack" />
                    <div class="absolute inset-0 bg-gradient-to-b from-navy/70 via-navy/15 to-transparent md:bg-gradient-to-r md:from-navy/70 md:via-navy/10 md:to-transparent"></div>
                    <!-- rotating $0 stamp on the seam -->
                    <svg class="absolute -left-8 top-1/2 hidden h-28 w-28 -translate-y-1/2 md:block" viewBox="0 0 110 110" aria-hidden="true">
                        <circle cx="55" cy="55" r="52" fill="#0e1420" fill-opacity="0.88" stroke="#398aff" stroke-width="1.5" />
                        <circle cx="55" cy="55" r="43" fill="none" stroke="#9cc6ff" stroke-opacity="0.5" stroke-dasharray="2 4" />
                        <defs><path id="stampArc" d="M55 20a35 35 0 1 1-.01 0z" fill="none" /></defs>
                        <g font-size="7.6" letter-spacing="1.4" fill="#cfe2ff" font-weight="600">
                            <text><textPath href="#stampArc">FREE SAMPLE PACK • FEEL THE PAPER •</textPath></text>
                            <animateTransform attributeName="transform" type="rotate" from="0 55 55" to="360 55 55" dur="40s" repeatCount="indefinite" />
                        </g>
                        <text x="55" y="62" text-anchor="middle" font-size="21" font-weight="700" fill="#fff">$0</text>
                    </svg>
                </div>
            </div>
        </section>

        <!-- tools to grow your business — bespoke vector icons on blue-tinted
             tiles, registration-mark corner flourish on every card -->
        <section class="mx-auto max-w-7xl px-6 py-14 sm:px-8 sm:py-16">
            <div class="mb-10">
                <p class="text-sm font-semibold uppercase tracking-widest text-brand-600">Everything you need</p>
                <h2 class="mt-2 font-display text-3xl font-bold tracking-tight sm:text-4xl">Tools to grow your business</h2>
                <svg class="mt-3.5 h-4 w-44 text-brand-blue" viewBox="0 0 176 16" fill="none" aria-hidden="true">
                    <path d="M4 12C42 2 92 15 172 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                    <path d="M4 12 24 6M172 6l-20 4" stroke="#92a3bd" stroke-dasharray="2.5 2.5" />
                    <rect x="1" y="9.5" width="5.5" height="5.5" fill="#fff" stroke="#2b3b55" stroke-width="1.2" />
                    <rect x="169.5" y="3" width="5.5" height="5.5" fill="#fff" stroke="#2b3b55" stroke-width="1.2" />
                    <circle cx="90" cy="9" r="2.2" fill="currentColor" />
                </svg>
            </div>
            <div class="grid grid-cols-2 gap-6 md:grid-cols-4">
                <component :is="tool.href ? Link : 'div'" v-for="tool in tools" :key="tool.title" :href="tool.href" class="group relative overflow-hidden rounded-2xl border border-paper-300 bg-white p-7 shadow-sm transition duration-300 hover:-translate-y-1.5 hover:border-brand-300 hover:shadow-xl">
                    <svg class="absolute -right-5 -top-5 h-24 w-24 text-brand-600 opacity-[0.06] transition duration-500 group-hover:rotate-12 group-hover:opacity-10" viewBox="0 0 96 96" fill="none" aria-hidden="true">
                        <circle cx="48" cy="48" r="29" stroke="currentColor" stroke-width="1.5" />
                        <circle cx="48" cy="48" r="41" stroke="currentColor" stroke-dasharray="3 5" />
                        <path d="M48 12v12M48 84V72M12 48h12M84 48H72" stroke="currentColor" stroke-width="1.5" />
                    </svg>
                    <svg viewBox="0 0 64 64" class="relative h-16 w-16 transition duration-300 group-hover:scale-105" aria-hidden="true" v-html="tool.icon"></svg>
                    <h3 class="relative mt-5 font-semibold text-ink">{{ tool.title }}</h3>
                    <p class="relative mt-1.5 text-sm text-ink/55">{{ tool.text }}</p>
                </component>
            </div>
        </section>

        <!-- best price guarantee -->
        <section class="mt-16 border-t border-paper-300 bg-paper-200 sm:mt-20">
            <div class="mx-auto grid max-w-7xl items-center gap-10 px-6 py-16 sm:px-8 sm:py-20 lg:grid-cols-2 lg:gap-16">
                <div class="order-last overflow-hidden rounded-3xl border border-paper-300 bg-white shadow-sm lg:order-first">
                    <img v-if="priceGuaranteeImage" :src="priceGuaranteeImage" alt="Best Price Guarantee" loading="lazy" class="w-full object-cover" />
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-widest text-brand-700/70">Our promise</p>
                    <h2 class="mt-2 font-display text-3xl font-bold tracking-tight sm:text-4xl">Best Price Guarantee</h2>
                    <p class="mt-4 max-w-xl text-lg text-ink/65">Find the same product for less somewhere else? We'll match it. Premium print at the best price — guaranteed, with no compromise on quality.</p>
                    <ul class="mt-6 space-y-3">
                        <li v-for="point in ['We\'ll match any competitor\'s price on a like-for-like order', 'Backed by our 100% satisfaction guarantee — love it or we reprint', 'Fast turnaround with next-day options']" :key="point" class="flex items-start gap-3 text-ink/80">
                            <svg class="mt-0.5 h-5 w-5 shrink-0 text-brand-600" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="10" cy="10" r="8.25" /><path d="m6.5 10.2 2.2 2.2 4.8-4.8" stroke-linecap="round" stroke-linejoin="round" /></svg>
                            <span>{{ point }}</span>
                        </li>
                    </ul>
                    <Link href="/category/business-cards" class="mt-8 inline-flex items-center gap-2 rounded-full bg-brand-600 px-6 py-3.5 font-semibold text-white shadow-lg shadow-brand-600/20 transition hover:bg-brand-700">
                        Start your order
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round" /></svg>
                    </Link>
                </div>
            </div>
        </section>
    </StoreLayout>
</template>
