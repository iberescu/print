<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLogo from '../Components/AppLogo.vue';

const page = usePage();
const categories = computed(() => page.props.navCategories ?? []);
const threshold = computed(() => page.props.shop?.freeShippingThreshold ?? 50);
const flash = computed(() => page.props.flash?.success ?? null);
const cartCount = computed(() => page.props.cart?.count ?? 0);
const year = new Date().getFullYear();
</script>

<template>
    <div class="flex min-h-screen flex-col bg-paper">
        <!-- utility bar -->
        <div class="bg-brand-950 text-paper">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-2 text-[13px]">
                <span class="flex items-center gap-2 font-medium">
                    <svg class="h-4 w-4 text-lime-accent" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 7h11v9H3zM14 10h4l3 3v3h-7" stroke-linecap="round" stroke-linejoin="round" /></svg>
                    Free shipping on orders over ${{ threshold }}
                </span>
                <nav class="hidden items-center gap-5 text-paper/80 sm:flex">
                    <a href="#" class="hover:text-paper">Help center</a>
                    <a href="#" class="hover:text-paper">Track my order</a>
                    <a href="#" class="hover:text-paper">Sign in</a>
                </nav>
            </div>
        </div>

        <!-- header -->
        <header class="sticky top-0 z-40 border-b border-paper-300 bg-paper">
            <div class="mx-auto flex max-w-7xl items-center gap-4 px-6 py-4 sm:gap-6">
                <Link href="/" class="shrink-0"><AppLogo /></Link>
                <div class="flex flex-1 items-center border border-ink/20 bg-white px-4 py-2.5">
                    <svg class="h-5 w-5 text-ink/40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="11" cy="11" r="7" /><path d="m21 21-4.3-4.3" stroke-linecap="round" /></svg>
                    <input type="text" placeholder="What are you looking for?" class="w-full bg-transparent px-3 text-sm placeholder:text-ink/40 focus:outline-none" />
                </div>
                <button class="hidden h-11 w-11 place-items-center text-ink/70 hover:text-ink sm:grid" aria-label="Account">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="8" r="4" /><path d="M4 20c0-4 4-6 8-6s8 2 8 6" stroke-linecap="round" /></svg>
                </button>
                <Link href="/cart" class="relative grid h-11 w-11 place-items-center text-ink/70 hover:text-ink" aria-label="Cart">
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M5 7h15l-1.5 9.5a2 2 0 0 1-2 1.5H8.5a2 2 0 0 1-2-1.7L5 4H3" stroke-linecap="round" stroke-linejoin="round" /><circle cx="9" cy="20" r="1.4" fill="currentColor" /><circle cx="17" cy="20" r="1.4" fill="currentColor" /></svg>
                    <span v-if="cartCount" class="absolute right-1 top-1.5 grid h-4 min-w-[16px] place-items-center bg-brand-600 px-1 text-[10px] font-bold text-white">{{ cartCount }}</span>
                </Link>
            </div>
            <!-- category mega-nav -->
            <nav class="border-t border-paper-300">
                <div class="mx-auto flex max-w-7xl items-center gap-1 overflow-x-auto px-6">
                    <Link v-for="c in categories" :key="c.slug" :href="`/category/${c.slug}`"
                          class="whitespace-nowrap px-3 py-3 text-sm font-medium text-ink/75 transition hover:text-brand-700 hover:shadow-[inset_0_-3px_0_0_var(--color-brand-600)]">
                        {{ c.name }}
                    </Link>
                </div>
            </nav>
        </header>

        <div v-if="flash" class="bg-brand-50 text-brand-900">
            <div class="mx-auto max-w-7xl px-6 py-2.5 text-sm font-medium">✓ {{ flash }}</div>
        </div>

        <main class="flex-1"><slot /></main>

        <!-- newsletter band -->
        <section class="border-t border-paper-300 bg-paper-200">
            <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-5 px-6 py-10 md:flex-row">
                <div>
                    <h3 class="font-display text-2xl font-semibold text-ink">It's good to be on the list</h3>
                    <p class="mt-1 text-ink/60">Exclusive offers, design tips and new products — straight to your inbox.</p>
                </div>
                <form class="flex w-full max-w-md" @submit.prevent>
                    <input type="email" placeholder="Enter your email" class="w-full border border-ink/20 bg-white px-4 py-3 text-sm focus:outline-none" />
                    <button class="shrink-0 bg-brand-600 px-6 py-3 text-sm font-semibold text-white transition hover:bg-brand-700">Sign up</button>
                </form>
            </div>
        </section>

        <!-- footer -->
        <footer class="bg-brand-950 text-paper/75">
            <div class="mx-auto grid max-w-7xl gap-8 px-6 py-14 sm:grid-cols-2 lg:grid-cols-5">
                <div>
                    <div class="flex items-center gap-2 text-paper">
                        <img src="/storage/brand/logo.jpg" alt="RunMyPrint" class="h-8 w-8 object-cover" />
                        <span class="font-display text-lg font-semibold">RunMyPrint</span>
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
                    </ul>
                </div>
                <div>
                    <h4 class="text-xs font-semibold uppercase tracking-widest text-paper/45">Company</h4>
                    <ul class="mt-4 space-y-2.5 text-sm text-paper/70">
                        <li><a href="#" class="transition hover:text-lime-accent">About us</a></li>
                        <li><a href="#" class="transition hover:text-lime-accent">Sustainability</a></li>
                        <li><a href="#" class="transition hover:text-lime-accent">Careers</a></li>
                        <li><a href="#" class="transition hover:text-lime-accent">Affiliates</a></li>
                        <li><a href="#" class="transition hover:text-lime-accent">Blog</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-xs font-semibold uppercase tracking-widest text-paper/45">Help</h4>
                    <ul class="mt-4 space-y-2.5 text-sm text-paper/70">
                        <li><a href="#" class="transition hover:text-lime-accent">Contact us</a></li>
                        <li><a href="#" class="transition hover:text-lime-accent">Shipping &amp; delivery</a></li>
                        <li><a href="#" class="transition hover:text-lime-accent">Returns</a></li>
                        <li><a href="#" class="transition hover:text-lime-accent">File prep &amp; templates</a></li>
                        <li><a href="#" class="transition hover:text-lime-accent">FAQ</a></li>
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
            <div class="border-t border-white/10">
                <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-3 px-6 py-6 text-xs text-paper/45 sm:flex-row">
                    <p>© {{ year }} RunMyPrint. All rights reserved.</p>
                    <p class="flex items-center gap-4">
                        <a href="#" class="hover:text-paper/70">Privacy</a>
                        <a href="#" class="hover:text-paper/70">Terms</a>
                        <a href="#" class="hover:text-paper/70">Cookies</a>
                        <span class="hidden sm:inline">🔒 Secure checkout · Stripe</span>
                    </p>
                </div>
            </div>
        </footer>
    </div>
</template>
