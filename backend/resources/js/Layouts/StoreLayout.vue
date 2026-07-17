<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppLogo from '../Components/AppLogo.vue';
import SupportWidget from '../Components/SupportWidget.vue';
import NewsletterSignup from '../Components/NewsletterSignup.vue';

const page = usePage();
const categories = computed(() => page.props.navCategories ?? []);
const threshold = computed(() => page.props.shop?.freeShippingThreshold ?? 100);
const flash = computed(() => page.props.flash?.success ?? null);
const flashError = computed(() => page.props.flash?.error ?? null);
const cartCount = computed(() => page.props.cart?.count ?? 0);
const searchQ = ref('');
const goSearch = () => { const q = searchQ.value.trim(); if (q) router.get('/search', { q }); };

const user = computed(() => page.props.auth?.user ?? null);
const company = computed(() => page.props.shop?.company ?? {});
const year = new Date().getFullYear();
const mobileMenuOpen = ref(false);
const logout = () => router.post('/logout');

// Private Brand Store context (subdomain hosts): lock banner + the customer's
// brand colors repaint the shop's palette (same structure, their colors).
const brandStore = computed(() => page.props.brandStore ?? null);
const brandCss = computed(() => {
    const c = brandStore.value?.colors;
    if (!c?.primary) return '';
    const p = c.primary;
    const a = c.accent || c.primary;
    return `
.bg-brand-600,.bg-brand-700,.bg-navy,.bg-brand-blue{background-color:${p} !important}
.hover\\:bg-brand-700:hover,.hover\\:bg-brand-600:hover{background-color:color-mix(in srgb, ${p} 82%, black) !important}
.bg-brand-50{background-color:color-mix(in srgb, ${p} 8%, white) !important}
.text-brand-600,.text-brand-700{color:${p} !important}
.border-brand-600,.ring-brand-300{border-color:${p} !important;--tw-ring-color:color-mix(in srgb, ${p} 45%, white) !important}
.bg-lime-accent{background-color:${a} !important}
.text-lime-accent{color:${a} !important}
.focus\\:border-brand-400:focus{border-color:${p} !important}`;
});
</script>

<template>
    <div class="flex min-h-screen flex-col bg-paper">
        <component :is="'style'" v-if="brandCss">{{ brandCss }}</component>

        <!-- private brand store: the lock banner tops every page -->
        <div v-if="brandStore" class="no-round border-b border-black/20 bg-ink text-white">
            <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-center gap-x-3 gap-y-1 px-4 py-2 text-center text-[13px] sm:px-8">
                <span class="font-semibold">🔒 Private Brand Store for {{ brandStore.company }}</span>
                <span class="hidden text-white/60 sm:inline">·</span>
                <span class="text-white/75">Only authorized {{ brandStore.company }} employees can order.</span>
                <a :href="brandStore.mainShop" class="font-medium text-white underline underline-offset-2 hover:text-white/80">Visit the main shop</a>
            </div>
        </div>

        <!-- utility bar -->
        <div class="no-round bg-navy text-paper">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-2.5 text-[13px] sm:px-8">
                <span class="flex items-center gap-2.5">
                    <svg viewBox="0 0 24 24" class="h-5 w-auto shrink-0 text-white" fill="none" aria-hidden="true">
                        <path d="M12 2.4 4.9 5.1v6.1c0 4.5 3 7.9 7.1 9.4 4.1-1.5 7.1-4.9 7.1-9.4V5.1L12 2.4Z" fill="currentColor" />
                        <path d="m8.7 11.9 2.3 2.4 4.4-5" stroke="#2b3b55" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    <span class="text-white"><span class="font-semibold">Best price</span> guarantee</span>
                </span>
                <nav class="hidden items-center gap-5 text-paper/80 sm:flex">
                    <a href="#" class="hover:text-paper">Help center</a>
                    <template v-if="user">
                        <Link href="/account" class="hover:text-paper">My orders</Link>
                        <button class="hover:text-paper" @click="logout">Sign out</button>
                    </template>
                    <template v-else>
                        <Link href="/account" class="hover:text-paper">Track my order</Link>
                        <Link href="/login" class="hover:text-paper">Sign in</Link>
                    </template>
                </nav>
            </div>
        </div>

        <!-- header -->
        <header class="sticky top-0 z-40 border-b border-paper-300 bg-paper">
            <div class="mx-auto flex max-w-7xl items-center gap-3 px-4 py-4 sm:gap-8 sm:px-8 sm:py-5">
                <!-- mobile: hamburger and the icon group are equal flex-1 wings, so the logo sits dead-centre -->
                <div class="flex flex-1 items-center md:hidden">
                    <button class="grid h-10 w-10 shrink-0 place-items-center text-ink/80 hover:text-ink" aria-label="Open menu" @click="mobileMenuOpen = true">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M4 7h16M4 12h16M4 17h16" stroke-linecap="round" /></svg>
                    </button>
                </div>
                <Link href="/" class="shrink-0"><AppLogo featured /></Link>
                <div class="hidden flex-1 items-center border border-ink/20 bg-white px-4 py-2.5 md:flex">
                    <svg class="h-5 w-5 text-ink/40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="11" cy="11" r="7" /><path d="m21 21-4.3-4.3" stroke-linecap="round" /></svg>
                    <input v-model="searchQ" type="text" placeholder="What are you looking for?" class="w-full bg-transparent px-3 text-sm placeholder:text-ink/40 focus:outline-none" @keydown.enter="goSearch" />
                </div>
                <div class="flex flex-1 items-center justify-end gap-1 md:flex-none">
                    <Link :href="user ? '/account' : '/login'" class="hidden h-11 w-11 place-items-center text-ink/70 hover:text-ink sm:grid" :aria-label="user ? 'My account' : 'Sign in'">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="8" r="4" /><path d="M4 20c0-4 4-6 8-6s8 2 8 6" stroke-linecap="round" /></svg>
                    </Link>
                    <Link href="/cart" class="relative grid h-11 w-11 place-items-center text-ink/70 hover:text-ink" aria-label="Cart">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M5 7h15l-1.5 9.5a2 2 0 0 1-2 1.5H8.5a2 2 0 0 1-2-1.7L5 4H3" stroke-linecap="round" stroke-linejoin="round" /><circle cx="9" cy="20" r="1.4" fill="currentColor" /><circle cx="17" cy="20" r="1.4" fill="currentColor" /></svg>
                        <span v-if="cartCount" class="absolute right-1 top-1.5 grid h-4 min-w-[16px] place-items-center bg-brand-600 px-1 text-[10px] font-bold text-white">{{ cartCount }}</span>
                    </Link>
                </div>
            </div>
            <!-- mobile search row -->
            <div class="px-4 pb-3 md:hidden">
                <div class="flex items-center border border-ink/20 bg-white px-4 py-2.5">
                    <svg class="h-5 w-5 text-ink/40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="11" cy="11" r="7" /><path d="m21 21-4.3-4.3" stroke-linecap="round" /></svg>
                    <input v-model="searchQ" type="text" placeholder="What are you looking for?" class="w-full bg-transparent px-3 text-sm placeholder:text-ink/40 focus:outline-none" @keydown.enter="goSearch" />
                </div>
            </div>
            <!-- category mega-nav (desktop) — trimmed so it never scrolls; the
                 dropped categories stay reachable via the mobile menu & tiles -->
            <nav class="hidden md:block">
                <div class="mx-auto flex max-w-7xl items-center gap-1 overflow-x-auto px-6">
                    <Link v-for="c in categories.filter((x) => !['accessories', 'other'].includes(x.slug))" :key="c.slug" :href="`/category/${c.slug}`"
                          style="border-radius: 0 !important"
                          class="whitespace-nowrap px-3 py-3 text-sm tracking-[1px] text-ink/75 transition hover:text-brand-700 hover:shadow-[inset_0_-2px_0_0_var(--color-brand-blue)]">
                        {{ c.name }}
                    </Link>
                    <Link href="/logo-maker"
                          style="border-radius: 0 !important"
                          class="whitespace-nowrap px-3 py-3 text-sm font-semibold tracking-[1px] text-brand-blue transition hover:text-brand-700 hover:shadow-[inset_0_-2px_0_0_var(--color-brand-blue)]">
                        ✦ Logo Maker
                    </Link>
                </div>
            </nav>
        </header>

        <!-- mobile slide-out menu -->
        <Transition
            enter-active-class="transition duration-200 ease-out" enter-from-class="opacity-0"
            leave-active-class="transition duration-150 ease-in" leave-to-class="opacity-0">
            <div v-if="mobileMenuOpen" class="fixed inset-0 z-50 md:hidden">
                <div class="absolute inset-0 bg-ink/50" @click="mobileMenuOpen = false"></div>
                <div class="absolute left-0 top-0 flex h-full w-80 max-w-[85%] flex-col bg-paper shadow-2xl">
                    <div class="flex items-center justify-between border-b border-paper-300 px-5 py-4">
                        <Link href="/" @click="mobileMenuOpen = false"><AppLogo /></Link>
                        <button class="grid h-9 w-9 place-items-center text-ink/60 hover:text-ink" aria-label="Close menu" @click="mobileMenuOpen = false">
                            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m6 6 12 12M18 6 6 18" stroke-linecap="round" /></svg>
                        </button>
                    </div>
                    <nav class="flex-1 overflow-auto py-2">
                        <p class="px-5 pb-1 pt-3 text-xs font-semibold uppercase tracking-widest text-ink/40">Shop</p>
                        <Link v-for="c in categories" :key="c.slug" :href="`/category/${c.slug}`" @click="mobileMenuOpen = false"
                              class="block px-5 py-3 text-[15px] font-medium text-ink/80 transition hover:bg-paper-200 hover:text-brand-700">
                            {{ c.name }}
                        </Link>
                        <Link href="/logo-maker" @click="mobileMenuOpen = false"
                              class="block px-5 py-3 text-[15px] font-semibold text-brand-blue transition hover:bg-paper-200 hover:text-brand-700">
                            ✦ Logo Maker
                        </Link>
                        <div class="my-2 border-t border-paper-300"></div>
                        <a href="#" class="block px-5 py-3 text-[15px] text-ink/70 transition hover:bg-paper-200">Help center</a>
                        <Link href="/account" class="block px-5 py-3 text-[15px] text-ink/70 transition hover:bg-paper-200" @click="mobileMenuOpen = false">{{ user ? 'My orders' : 'Track my order' }}</Link>
                        <Link v-if="!user" href="/login" class="block px-5 py-3 text-[15px] text-ink/70 transition hover:bg-paper-200" @click="mobileMenuOpen = false">Sign in</Link>
                        <button v-else class="block w-full px-5 py-3 text-left text-[15px] text-ink/70 transition hover:bg-paper-200" @click="logout">Sign out</button>
                    </nav>
                    <p class="flex items-center gap-2 border-t border-paper-300 px-5 py-4 text-sm font-medium text-brand-700">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 7h11v9H3zM14 10h4l3 3v3h-7" stroke-linecap="round" stroke-linejoin="round" /></svg>
                        Free shipping over ${{ threshold }}
                    </p>
                </div>
            </div>
        </Transition>

        <div v-if="flash" class="bg-brand-50 text-brand-900">
            <div class="mx-auto max-w-7xl px-6 py-2.5 text-sm font-medium">✓ {{ flash }}</div>
        </div>
        <div v-if="flashError" class="bg-red-50 text-red-800">
            <div class="mx-auto max-w-7xl px-6 py-2.5 text-sm font-medium">⚠ {{ flashError }}</div>
        </div>

        <main class="flex-1"><slot /></main>

        <!-- newsletter band -->
        <section class="border-t border-paper-300 bg-paper-200">
            <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-5 px-6 py-10 md:flex-row">
                <div>
                    <h3 class="font-display text-2xl font-semibold text-ink">It's good to be on the list</h3>
                    <p class="mt-1 text-ink/60">Exclusive offers, design tips and new products — straight to your inbox.</p>
                </div>
                <NewsletterSignup source="footer" cta="Sign up" class="w-full max-w-md" />
            </div>
        </section>

        <!-- footer -->
        <footer class="bg-navy text-paper/75">
            <div class="mx-auto grid max-w-7xl gap-8 px-6 py-14 sm:grid-cols-2 lg:grid-cols-5">
                <div>
                    <div class="flex items-center gap-2 text-paper">
                        <img src="/storage/brand/logo.svg" alt="runmyprint" class="h-14 w-auto" />
                    </div>
                    <p class="mt-4 text-sm leading-relaxed text-paper/55">Premium custom printing for growing businesses.</p>
                    <div class="mt-5 flex gap-2.5">
                        <a v-for="n in ['in','f','X','▶']" :key="n" href="#" class="grid h-9 w-9 place-items-center border border-white/15 text-xs font-semibold text-paper/70 transition hover:border-lime-accent hover:text-lime-accent">{{ n }}</a>
                    </div>
                </div>
                <div>
                    <h4 class="text-xs font-semibold uppercase tracking-widest text-paper/45">Products</h4>
                    <ul class="mt-4 space-y-2.5 text-sm">
                        <li v-for="c in categories" :key="c.slug"><Link :href="`/category/${c.slug}`" class="text-paper/70 transition hover:text-lime-accent">{{ c.name }}</Link></li>
                        <li><Link href="/logo-maker" class="text-paper/70 transition hover:text-lime-accent">Free logo maker</Link></li>
                        <li><Link href="/qr-code-generator" class="text-paper/70 transition hover:text-lime-accent">Free QR code generator</Link></li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-xs font-semibold uppercase tracking-widest text-paper/45">Company</h4>
                    <ul class="mt-4 space-y-2.5 text-sm text-paper/70">
                        <li><a href="#" class="transition hover:text-lime-accent">About us</a></li>
                        <li><a href="#" class="transition hover:text-lime-accent">Sustainability</a></li>
                        <li><a href="#" class="transition hover:text-lime-accent">Careers</a></li>
                        <li><a href="/affiliates" class="transition hover:text-lime-accent">Affiliates</a></li>
                        <li><a href="#" class="transition hover:text-lime-accent">Blog</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-xs font-semibold uppercase tracking-widest text-paper/45">Help</h4>
                    <ul class="mt-4 space-y-2.5 text-sm text-paper/70">
                        <li><a :href="`mailto:${company.email}`" class="transition hover:text-lime-accent">Contact us</a></li>
                        <li><Link href="/shipping" class="transition hover:text-lime-accent">Shipping &amp; delivery</Link></li>
                        <li><Link href="/returns" class="transition hover:text-lime-accent">Returns</Link></li>
                        <li><Link href="/faq" class="transition hover:text-lime-accent">FAQ</Link></li>
                        <li><Link href="/terms" class="transition hover:text-lime-accent">Terms</Link></li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-xs font-semibold uppercase tracking-widest text-paper/45">Account</h4>
                    <ul class="mt-4 space-y-2.5 text-sm text-paper/70">
                        <li><a href="#" class="transition hover:text-lime-accent">Sign in</a></li>
                        <li><a href="#" class="transition hover:text-lime-accent">My orders</a></li>
                        <li><Link href="/cart" class="transition hover:text-lime-accent">Cart</Link></li>
                        <li><a href="#" class="transition hover:text-lime-accent">Saved designs</a></li>
                    </ul>
                </div>
            </div>
            <!-- supported payments -->
            <div class="border-t border-white/10">
                <div class="mx-auto flex max-w-7xl flex-wrap items-center gap-x-4 gap-y-3 px-6 py-5 sm:px-8">
                    <span class="text-[11px] font-semibold uppercase tracking-widest text-paper/45">We accept</span>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="grid h-8 w-12 place-items-center rounded-md bg-white shadow-sm"><span class="text-[13px] font-extrabold italic tracking-tighter text-[#1a1f71]">VISA</span></span>
                        <span class="grid h-8 w-12 place-items-center rounded-md bg-white shadow-sm" aria-label="Mastercard"><svg viewBox="0 0 40 24" class="h-4"><circle cx="16" cy="12" r="8" fill="#eb001b" /><circle cx="24" cy="12" r="8" fill="#f79e1b" fill-opacity="0.9" /></svg></span>
                        <span class="grid h-8 w-14 place-items-center rounded-md bg-white shadow-sm"><span class="text-[12px] font-extrabold tracking-tight"><span class="text-[#003087]">Pay</span><span class="text-[#0070e0]">Pal</span></span></span>
                        <span class="flex h-8 w-16 items-center justify-center gap-0.5 rounded-md bg-white shadow-sm" aria-label="Apple Pay"><svg viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="#000"><path d="M17.05 12.04c-.03-2.6 2.12-3.85 2.22-3.91-1.21-1.77-3.09-2.01-3.76-2.04-1.6-.16-3.12.94-3.93.94-.81 0-2.06-.92-3.39-.89-1.74.03-3.35 1.01-4.24 2.57-1.81 3.14-.46 7.78 1.3 10.33.86 1.25 1.88 2.65 3.22 2.6 1.29-.05 1.78-.83 3.34-.83 1.56 0 2 .83 3.37.81 1.39-.03 2.27-1.27 3.12-2.53.98-1.45 1.39-2.85 1.41-2.92-.03-.01-2.7-1.04-2.73-4.11z" /><path d="M14.6 4.6c.71-.86 1.19-2.06 1.06-3.25-1.02.04-2.26.68-2.99 1.54-.66.76-1.23 1.98-1.08 3.14 1.14.09 2.3-.58 3.01-1.43z" /></svg><span class="text-[11px] font-semibold text-black">Pay</span></span>
                    </div>
                </div>
            </div>
            <div class="border-t border-white/10">
                <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-3 px-6 py-6 text-xs text-paper/45 sm:flex-row">
                    <p>© {{ year }} {{ company.brand || 'RunMyPrint' }} · {{ company.name }} · {{ company.address }}</p>
                    <p class="flex items-center gap-4">
                        <Link href="/terms" class="hover:text-paper/70">Terms</Link>
                        <Link href="/faq" class="hover:text-paper/70">FAQ</Link>
                        <span class="hidden sm:inline">🔒 Secure checkout · Stripe</span>
                    </p>
                </div>
            </div>
        </footer>

        <SupportWidget />
    </div>
</template>
