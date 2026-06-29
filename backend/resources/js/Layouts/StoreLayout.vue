<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppLogo from '../Components/AppLogo.vue';

const page = usePage();
const categories = computed(() => page.props.navCategories ?? []);
const threshold = computed(() => page.props.shop?.freeShippingThreshold ?? 50);
const flash = computed(() => page.props.flash?.success ?? null);
const mobileOpen = ref(false);
const year = new Date().getFullYear();
</script>

<template>
    <div class="flex min-h-screen flex-col bg-paper bg-grain">
        <!-- Announcement bar -->
        <div class="bg-brand-950 text-paper">
            <div class="mx-auto flex max-w-7xl items-center justify-center gap-2 px-6 py-2 text-center text-[13px] font-medium">
                <svg class="h-4 w-4 text-lime-accent" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                    <path d="M3 7h11v9H3zM14 10h4l3 3v3h-7M5.5 19a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3zM17.5 19a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3z" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
                <span>Free shipping on orders over <strong>${{ threshold }}</strong> · Design online or upload your own artwork</span>
            </div>
        </div>

        <!-- Header -->
        <header class="sticky top-0 z-40 border-b border-paper-300/80 bg-paper/85 backdrop-blur-md">
            <div class="mx-auto flex max-w-7xl items-center gap-4 px-6 py-3.5">
                <Link href="/" class="shrink-0"><AppLogo /></Link>

                <nav class="ml-4 hidden items-center gap-1 lg:flex">
                    <Link
                        v-for="c in categories"
                        :key="c.slug"
                        :href="`/category/${c.slug}`"
                        class="rounded-full px-3 py-2 text-sm font-medium text-ink/70 transition hover:bg-paper-200 hover:text-ink"
                    >
                        {{ c.name }}
                    </Link>
                </nav>

                <div class="ml-auto flex items-center gap-1.5">
                    <div class="hidden items-center rounded-full border border-paper-300 bg-white px-3 py-2 md:flex">
                        <svg class="h-4 w-4 text-ink/40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                            <circle cx="11" cy="11" r="7" /><path d="m21 21-4.3-4.3" stroke-linecap="round" />
                        </svg>
                        <input
                            type="text"
                            placeholder="Search products"
                            class="w-36 bg-transparent px-2 text-sm text-ink placeholder:text-ink/40 focus:outline-none"
                        />
                    </div>
                    <button class="grid h-10 w-10 place-items-center rounded-full text-ink/70 transition hover:bg-paper-200" aria-label="Account">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                            <circle cx="12" cy="8" r="4" /><path d="M4 20c0-4 4-6 8-6s8 2 8 6" stroke-linecap="round" />
                        </svg>
                    </button>
                    <Link href="/cart" class="relative grid h-10 w-10 place-items-center rounded-full text-ink/70 transition hover:bg-paper-200" aria-label="Cart">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                            <path d="M5 7h15l-1.5 9.5a2 2 0 0 1-2 1.5H8.5a2 2 0 0 1-2-1.7L5 4H3" stroke-linecap="round" stroke-linejoin="round" />
                            <circle cx="9" cy="20" r="1.4" fill="currentColor" /><circle cx="17" cy="20" r="1.4" fill="currentColor" />
                        </svg>
                    </Link>
                    <button class="grid h-10 w-10 place-items-center rounded-full text-ink/70 lg:hidden" aria-label="Menu" @click="mobileOpen = !mobileOpen">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M4 7h16M4 12h16M4 17h16" stroke-linecap="round" /></svg>
                    </button>
                </div>
            </div>

            <!-- Mobile nav -->
            <div v-if="mobileOpen" class="border-t border-paper-300 bg-paper px-6 py-3 lg:hidden">
                <Link
                    v-for="c in categories"
                    :key="c.slug"
                    :href="`/category/${c.slug}`"
                    class="block rounded-lg px-3 py-2 text-sm font-medium text-ink/80 hover:bg-paper-200"
                    @click="mobileOpen = false"
                >
                    {{ c.name }}
                </Link>
            </div>
        </header>

        <!-- Flash -->
        <div v-if="flash" class="bg-brand-50 text-brand-900">
            <div class="mx-auto max-w-7xl px-6 py-2.5 text-sm font-medium">✓ {{ flash }}</div>
        </div>

        <main class="flex-1">
            <slot />
        </main>

        <!-- Footer -->
        <footer class="mt-24 bg-brand-950 text-paper/80">
            <div class="mx-auto grid max-w-7xl gap-10 px-6 py-16 md:grid-cols-2 lg:grid-cols-4">
                <div class="lg:pr-6">
                    <div class="flex items-center gap-2.5 text-paper">
                        <span class="text-lime-accent">
                            <svg viewBox="0 0 32 32" class="h-7 w-7"><rect x="2" y="2" width="28" height="28" rx="8" fill="currentColor" opacity="0.15" /><rect x="8.5" y="7.5" width="15" height="17" rx="2.5" fill="currentColor" /></svg>
                        </span>
                        <span class="font-display text-lg font-semibold">RunMyPrint</span>
                    </div>
                    <p class="mt-4 max-w-xs text-sm leading-relaxed text-paper/55">
                        Premium custom printing for growing businesses. Design online or upload your artwork — we make it look sharp.
                    </p>
                    <form class="mt-6 flex max-w-xs overflow-hidden rounded-full border border-white/15 bg-white/5" @submit.prevent>
                        <input type="email" placeholder="Your email" class="w-full bg-transparent px-4 py-2.5 text-sm text-paper placeholder:text-paper/40 focus:outline-none" />
                        <button class="shrink-0 bg-lime-accent px-4 text-sm font-semibold text-ink">Join</button>
                    </form>
                </div>

                <div>
                    <h4 class="text-xs font-semibold uppercase tracking-widest text-paper/45">Products</h4>
                    <ul class="mt-4 space-y-2.5 text-sm">
                        <li v-for="c in categories" :key="c.slug">
                            <Link :href="`/category/${c.slug}`" class="text-paper/70 transition hover:text-lime-accent">{{ c.name }}</Link>
                        </li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-xs font-semibold uppercase tracking-widest text-paper/45">Company</h4>
                    <ul class="mt-4 space-y-2.5 text-sm text-paper/70">
                        <li><a href="#" class="transition hover:text-lime-accent">About us</a></li>
                        <li><a href="#" class="transition hover:text-lime-accent">Sustainability</a></li>
                        <li><a href="#" class="transition hover:text-lime-accent">Careers</a></li>
                        <li><a href="#" class="transition hover:text-lime-accent">Blog</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-xs font-semibold uppercase tracking-widest text-paper/45">Help</h4>
                    <ul class="mt-4 space-y-2.5 text-sm text-paper/70">
                        <li><a href="#" class="transition hover:text-lime-accent">Contact</a></li>
                        <li><a href="#" class="transition hover:text-lime-accent">Shipping &amp; delivery</a></li>
                        <li><a href="#" class="transition hover:text-lime-accent">Returns</a></li>
                        <li><a href="#" class="transition hover:text-lime-accent">FAQ</a></li>
                    </ul>
                </div>
            </div>
            <div class="border-t border-white/10">
                <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-3 px-6 py-6 text-xs text-paper/45 sm:flex-row">
                    <p>© {{ year }} RunMyPrint. All rights reserved.</p>
                    <p class="flex items-center gap-4">
                        <a href="#" class="hover:text-paper/70">Privacy</a>
                        <a href="#" class="hover:text-paper/70">Terms</a>
                        <span class="hidden sm:inline">Secure checkout · Stripe</span>
                    </p>
                </div>
            </div>
        </footer>
    </div>
</template>
