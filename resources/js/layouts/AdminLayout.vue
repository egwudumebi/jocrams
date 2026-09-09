<script setup>
import { computed, onMounted, ref } from 'vue';
import { RouterLink, RouterView, useRouter, useRoute } from 'vue-router';
import {
    ArrowRightOnRectangleIcon,
    Bars3Icon,
    MagnifyingGlassIcon,
} from '@heroicons/vue/24/outline';
import AdminSidebar from '../components/admin/AdminSidebar.vue';
import ConfirmDialog from '../components/layout/ConfirmDialog.vue';
import MobileNavDrawer from '../components/layout/MobileNavDrawer.vue';
import NotificationBell from '../components/NotificationBell.vue';
import SiteBanner from '../components/layout/SiteBanner.vue';
import { adminQuickLinks, adminRouteTitles } from '../config/adminNavigation';
import { useAuth } from '../composables/useAuth';

const router = useRouter();
const route = useRoute();
const { adminUser, logoutAdmin, fetchAdminProfile, isAdminAuthenticated } = useAuth();
const mobileNavOpen = ref(false);
const logoutConfirmOpen = ref(false);

onMounted(async () => {
    if (isAdminAuthenticated.value) {
        await fetchAdminProfile();
    }
});

function requestLogout() {
    logoutConfirmOpen.value = true;
}

function handleLogout() {
    logoutConfirmOpen.value = false;
    mobileNavOpen.value = false;
    logoutAdmin();
    router.push({ name: 'admin.login' });
}

function closeMobileNav() {
    mobileNavOpen.value = false;
}

const pageTitle = computed(() => adminRouteTitles[route.name] || 'Admin');
</script>

<template>
    <div class="min-h-screen bg-surface-muted">
        <SiteBanner />
        <template v-if="$route.meta.guest">
            <RouterView />
        </template>
        <template v-else>
            <div class="flex min-h-screen">
                <AdminSidebar :admin-user="adminUser" @logout="requestLogout" />

                <MobileNavDrawer :open="mobileNavOpen" title="Admin Menu" @close="closeMobileNav">
                    <AdminSidebar
                        mobile
                        :admin-user="adminUser"
                        @logout="requestLogout"
                        @navigate="closeMobileNav"
                    />
                </MobileNavDrawer>

                <ConfirmDialog
                    :open="logoutConfirmOpen"
                    title="Log out?"
                    message="You will need to sign in again to access the admin portal."
                    confirm-label="Log out"
                    cancel-label="Stay signed in"
                    @confirm="handleLogout"
                    @cancel="logoutConfirmOpen = false"
                />

                <div class="flex min-w-0 flex-1 flex-col">
                    <header class="sticky top-0 z-20 border-b border-slate-200/80 bg-white/95 backdrop-blur">
                        <div class="flex items-center gap-2 px-4 py-3 lg:hidden">
                            <button
                                type="button"
                                class="inline-flex size-10 shrink-0 items-center justify-center rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50"
                                aria-label="Open navigation menu"
                                @click="mobileNavOpen = true"
                            >
                                <Bars3Icon class="size-5" />
                            </button>

                            <h1 class="min-w-0 flex-1 truncate font-display text-lg font-bold text-slate-900">
                                {{ pageTitle }}
                            </h1>

                            <div class="flex shrink-0 items-center gap-1.5">
                                <NotificationBell context="admin" />
                                <button
                                    type="button"
                                    class="inline-flex size-10 items-center justify-center rounded-xl border border-slate-200 text-slate-500 hover:bg-slate-50 hover:text-red-600"
                                    title="Log out"
                                    aria-label="Log out"
                                    @click="requestLogout"
                                >
                                    <ArrowRightOnRectangleIcon class="size-5" />
                                </button>
                            </div>
                        </div>

                        <div class="hidden items-center justify-between gap-4 px-6 py-4 lg:flex">
                            <div class="flex min-w-0 items-center gap-4">
                                <h1 class="min-w-0 truncate font-display text-2xl font-bold text-slate-900">
                                    {{ pageTitle }}
                                </h1>
                                <details class="relative">
                                    <summary class="cursor-pointer list-none rounded-lg border border-slate-200 px-3 py-1.5 text-sm text-slate-600 hover:bg-slate-50">
                                        Quick access
                                    </summary>
                                    <div class="absolute left-0 z-30 mt-2 w-56 rounded-xl border border-slate-200 bg-white p-2 shadow-lg">
                                        <RouterLink
                                            v-for="link in adminQuickLinks"
                                            :key="link.to"
                                            :to="link.to"
                                            class="block rounded-lg px-3 py-2 text-sm hover:bg-slate-50"
                                        >
                                            {{ link.label }}
                                        </RouterLink>
                                    </div>
                                </details>
                            </div>

                            <div class="flex min-w-0 flex-1 items-center justify-end gap-3">
                                <div class="relative hidden min-w-0 flex-1 lg:block lg:max-w-md">
                                    <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 size-5 -translate-y-1/2 text-slate-400" />
                                    <input
                                        type="search"
                                        placeholder="Search members, events..."
                                        class="input !rounded-xl !border-slate-200 !py-2.5 !pl-10 !pr-4"
                                    />
                                </div>
                                <NotificationBell context="admin" />
                                <div class="flex items-center gap-3 rounded-xl border border-slate-200 px-3 py-2">
                                    <span class="max-w-[10rem] truncate text-sm font-medium text-slate-700">{{ adminUser?.name || 'Admin User' }}</span>
                                    <button type="button" class="text-slate-400 hover:text-institutional" title="Log out" @click="requestLogout">
                                        <ArrowRightOnRectangleIcon class="size-5" />
                                    </button>
                                </div>
                                <RouterLink to="/" target="_blank" class="text-sm font-medium text-institutional hover:underline">
                                    Public site ↗
                                </RouterLink>
                            </div>
                        </div>
                    </header>

                    <main class="flex-1 overflow-x-hidden p-4 sm:p-6 lg:p-8">
                        <RouterView />
                    </main>
                </div>
            </div>
        </template>
    </div>
</template>
