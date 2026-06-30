<script setup>
import { computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';

defineProps({ title: { type: String, default: '' } });

const page = usePage();
const user = computed(() => page.props.auth?.user ?? {});
const flash = computed(() => page.props.flash?.success ?? null);
const url = computed(() => page.url);

const isActive = (href) => (href === '/admin' ? url.value === '/admin' || url.value === '/admin/' : url.value.startsWith(href));
const logout = () => router.post('/admin/logout');
</script>

<template>
    <div class="flex min-h-screen bg-paper-200 text-ink">
        <!-- sidebar -->
        <aside class="fixed inset-y-0 left-0 hidden w-60 flex-col bg-navy text-paper md:flex">
            <div class="flex items-center gap-2 px-6 py-5">
                <span class="font-display text-lg font-bold tracking-tight text-white">runmyprint</span>
                <span class="rounded bg-lime-accent px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-navy">Admin</span>
            </div>
            <nav class="flex-1 space-y-1 px-3 py-2 text-sm">
                <Link href="/admin" class="flex items-center gap-3 rounded-lg px-3 py-2.5 font-medium transition" :class="isActive('/admin') ? 'bg-white/15 text-white' : 'text-paper/70 hover:bg-white/10 hover:text-white'">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/></svg>
                    Dashboard
                </Link>
                <Link href="/admin/orders" class="flex items-center gap-3 rounded-lg px-3 py-2.5 font-medium transition" :class="isActive('/admin/orders') ? 'bg-white/15 text-white' : 'text-paper/70 hover:bg-white/10 hover:text-white'">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M6 2h12v20l-3-2-3 2-3-2-3 2z" stroke-linejoin="round"/><path d="M9 8h6M9 12h6" stroke-linecap="round"/></svg>
                    Orders
                </Link>
                <Link href="/admin/customers" class="flex items-center gap-3 rounded-lg px-3 py-2.5 font-medium transition" :class="isActive('/admin/customers') ? 'bg-white/15 text-white' : 'text-paper/70 hover:bg-white/10 hover:text-white'">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="9" cy="8" r="3.2"/><path d="M3.5 20a5.5 5.5 0 0 1 11 0" stroke-linecap="round"/><path d="M16 5.5a3 3 0 0 1 0 5.8M17.5 20a5.5 5.5 0 0 0-3-4.9" stroke-linecap="round"/></svg>
                    Customers
                </Link>
                <Link href="/admin/products" class="flex items-center gap-3 rounded-lg px-3 py-2.5 font-medium transition" :class="isActive('/admin/products') ? 'bg-white/15 text-white' : 'text-paper/70 hover:bg-white/10 hover:text-white'">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M21 8 12 3 3 8l9 5 9-5z" stroke-linejoin="round"/><path d="M3 8v8l9 5 9-5V8" stroke-linejoin="round"/><path d="M12 13v8" /></svg>
                    Products
                </Link>
                <Link href="/admin/surfaces" class="flex items-center gap-3 rounded-lg px-3 py-2.5 font-medium transition" :class="isActive('/admin/surfaces') ? 'bg-white/15 text-white' : 'text-paper/70 hover:bg-white/10 hover:text-white'">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="3" y="3" width="18" height="18" rx="1"/><path d="M8 3v18M16 3v18M3 8h18M3 16h18" stroke-width="1.2"/></svg>
                    Surfaces
                </Link>
            </nav>
            <div class="border-t border-white/10 p-3">
                <a href="/" target="_blank" class="mb-1 flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-paper/60 transition hover:bg-white/10 hover:text-white">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M14 3h7v7M21 3l-9 9M19 14v6H4V5h6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    View store
                </a>
                <div class="flex items-center justify-between rounded-lg px-3 py-2">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-white">{{ user.name }}</p>
                        <p class="truncate text-xs text-paper/55">{{ user.email }}</p>
                    </div>
                    <button class="shrink-0 rounded-md p-1.5 text-paper/60 transition hover:bg-white/10 hover:text-white" title="Sign out" @click="logout">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M15 12H4M11 8l-4 4 4 4M19 4v16" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </button>
                </div>
            </div>
        </aside>

        <!-- main -->
        <div class="flex min-h-screen flex-1 flex-col md:pl-60">
            <header class="sticky top-0 z-20 flex items-center justify-between gap-4 border-b border-paper-300 bg-paper px-5 py-4 sm:px-8">
                <h1 class="font-display text-xl font-bold tracking-tight">{{ title }}</h1>
                <div class="flex items-center gap-3">
                    <slot name="actions" />
                </div>
            </header>

            <!-- mobile nav -->
            <nav class="flex gap-1 overflow-x-auto border-b border-paper-300 bg-paper px-3 py-2 text-sm md:hidden">
                <Link href="/admin" class="whitespace-nowrap rounded-md px-3 py-1.5 font-medium" :class="isActive('/admin') ? 'bg-brand-50 text-brand-700' : 'text-ink/60'">Dashboard</Link>
                <Link href="/admin/orders" class="whitespace-nowrap rounded-md px-3 py-1.5 font-medium" :class="isActive('/admin/orders') ? 'bg-brand-50 text-brand-700' : 'text-ink/60'">Orders</Link>
                <Link href="/admin/customers" class="whitespace-nowrap rounded-md px-3 py-1.5 font-medium" :class="isActive('/admin/customers') ? 'bg-brand-50 text-brand-700' : 'text-ink/60'">Customers</Link>
                <Link href="/admin/products" class="whitespace-nowrap rounded-md px-3 py-1.5 font-medium" :class="isActive('/admin/products') ? 'bg-brand-50 text-brand-700' : 'text-ink/60'">Products</Link>
                <Link href="/admin/surfaces" class="whitespace-nowrap rounded-md px-3 py-1.5 font-medium" :class="isActive('/admin/surfaces') ? 'bg-brand-50 text-brand-700' : 'text-ink/60'">Surfaces</Link>
            </nav>

            <div v-if="flash" class="border-b border-emerald-200 bg-emerald-50 px-5 py-2.5 text-sm font-medium text-emerald-800 sm:px-8">✓ {{ flash }}</div>

            <main class="flex-1 p-5 sm:p-8"><slot /></main>
        </div>
    </div>
</template>
