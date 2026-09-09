<script setup>
import { ref, onMounted } from 'vue';
import { RouterLink, RouterView } from 'vue-router';
import { Bars3Icon, LockClosedIcon } from '@heroicons/vue/24/outline';
import SiteBanner from '../components/layout/SiteBanner.vue';
import MobileNavDrawer from '../components/layout/MobileNavDrawer.vue';
import SiteFooter from '../components/layout/SiteFooter.vue';
import { useSiteBranding } from '../composables/useSiteBranding';

const mobileNavOpen = ref(false);
const { branding, loadBranding } = useSiteBranding();

const nav = [
    { to: '/', label: 'About' },
    { to: '/sicama', label: 'SICAMA' },
    { to: '/news', label: 'News' },
    { to: '/events', label: 'Events' },
    { to: '/members', label: 'Members' },
    { to: '/downloads', label: 'Library' },
    { to: '/journal', label: 'Journal' },
    { to: '/contact', label: 'Contact' },
];

onMounted(() => {
    loadBranding();
});

function closeMobileNav() {
    mobileNavOpen.value = false;
}
</script>

<template>
    <div class="flex min-h-screen flex-col bg-white">
        <SiteBanner />
        <header class="glass-header sticky top-0 z-50">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-3 px-4 py-3.5 sm:gap-6 sm:px-6 lg:px-8">
                <div class="flex min-w-0 items-center gap-2 sm:gap-3">
                    <button
                        type="button"
                        class="rounded-lg p-2 text-white/80 hover:bg-white/10 lg:hidden"
                        aria-label="Open navigation menu"
                        @click="mobileNavOpen = true"
                    >
                        <Bars3Icon class="size-6" />
                    </button>
                    <RouterLink to="/" class="group flex min-w-0 shrink-0 items-center gap-3">
                        <span class="flex size-10 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-white ring-1 ring-white/20 transition group-hover:bg-white/95">
                            <img
                                :src="branding?.parent_org?.logo_url || branding?.site_logo_url || '/images/sicama-logo.png'"
                                :alt="`${branding?.parent_org?.short_name || 'SICAMA'} logo`"
                                class="size-9 object-contain p-0.5"
                                @error="$event.target.src = '/images/sicama-logo.png'"
                            />
                        </span>
                        <span class="min-w-0 leading-tight">
                            <span class="block truncate font-display text-lg font-bold tracking-tight text-white">
                                {{ branding?.site_name || 'JOCRAMS' }}
                            </span>
                            <span class="hidden truncate text-[11px] text-white/70 sm:block">
                                {{ branding?.parent_org?.short_name || 'SICAMA' }}
                            </span>
                        </span>
                    </RouterLink>
                </div>

                <nav class="hidden flex-1 justify-center gap-1 lg:flex">
                    <RouterLink
                        v-for="item in nav"
                        :key="item.to"
                        :to="item.to"
                        class="rounded-lg px-3.5 py-2 text-sm font-medium text-white/75 transition hover:bg-white/10 hover:text-white xl:px-4"
                        active-class="!bg-white/10 !text-white"
                    >
                        {{ item.label }}
                    </RouterLink>
                </nav>

                <div class="flex shrink-0 items-center gap-2 sm:gap-3">
                    <RouterLink
                        to="/member/login"
                        class="hidden items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium text-white/80 transition hover:bg-white/10 hover:text-white sm:inline-flex"
                    >
                        <LockClosedIcon class="size-4" aria-hidden="true" />
                        <span class="hidden md:inline">Member Login</span>
                        <span class="md:hidden">Login</span>
                    </RouterLink>
                    <RouterLink to="/member/register" class="btn-gold !px-4 !py-2.5 text-sm sm:!px-5">
                        <span class="hidden sm:inline">Join Us / Register</span>
                        <span class="sm:hidden">Join</span>
                    </RouterLink>
                </div>
            </div>
        </header>

        <MobileNavDrawer
            :open="mobileNavOpen"
            :title="branding?.site_name || 'JOCRAMS'"
            panel-class="bg-institutional-dark text-white"
            header-class="border-white/10"
            title-class="text-white"
            close-button-class="text-white/80 hover:bg-white/10"
            @close="closeMobileNav"
        >
            <nav class="space-y-1 px-3 py-4">
                <RouterLink
                    v-for="item in nav"
                    :key="item.to"
                    :to="item.to"
                    class="block rounded-lg px-4 py-3 text-sm font-medium text-white/80 hover:bg-white/10 hover:text-white"
                    active-class="!bg-white/10 !text-white"
                    @click="closeMobileNav"
                >
                    {{ item.label }}
                </RouterLink>
            </nav>
            <div class="mt-auto space-y-2 border-t border-white/10 px-4 py-4">
                <RouterLink
                    to="/member/login"
                    class="flex items-center gap-2 rounded-lg px-4 py-3 text-sm font-medium text-white/80 hover:bg-white/10"
                    @click="closeMobileNav"
                >
                    <LockClosedIcon class="size-4" />
                    Member Login
                </RouterLink>
                <RouterLink
                    to="/member/register"
                    class="btn-gold block w-full text-center !py-3"
                    @click="closeMobileNav"
                >
                    Join Us / Register
                </RouterLink>
            </div>
        </MobileNavDrawer>

        <main class="flex-1 overflow-x-hidden">
            <RouterView />
        </main>

        <SiteFooter />
    </div>
</template>
