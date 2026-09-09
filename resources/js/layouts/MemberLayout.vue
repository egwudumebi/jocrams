<script setup>
import { computed, onMounted, ref } from 'vue';
import { RouterLink, RouterView, useRouter, useRoute } from 'vue-router';
import { ArrowRightOnRectangleIcon, Bars3Icon } from '@heroicons/vue/24/outline';
import MemberSidebar from '../components/member/MemberSidebar.vue';
import ConfirmDialog from '../components/layout/ConfirmDialog.vue';
import MobileNavDrawer from '../components/layout/MobileNavDrawer.vue';
import NotificationBell from '../components/NotificationBell.vue';
import SiteBanner from '../components/layout/SiteBanner.vue';
import { memberQuickLinks, memberRouteTitles } from '../config/memberNavigation';
import { useAuth } from '../composables/useAuth';

const router = useRouter();
const route = useRoute();
const { memberUser, logoutMember, fetchMemberProfile, isMemberAuthenticated } = useAuth();
const mobileNavOpen = ref(false);
const logoutConfirmOpen = ref(false);

onMounted(async () => {
    if (isMemberAuthenticated.value) {
        await fetchMemberProfile();
    }
});

function requestLogout() {
    logoutConfirmOpen.value = true;
}

function handleLogout() {
    logoutConfirmOpen.value = false;
    mobileNavOpen.value = false;
    logoutMember();
    router.push({ name: 'member.login' });
}

function closeMobileNav() {
    mobileNavOpen.value = false;
}

const pageTitle = computed(() => memberRouteTitles[route.name] || 'Member Portal');
</script>

<template>
    <div class="min-h-screen bg-surface-muted">
        <SiteBanner />
        <template v-if="$route.meta.guest">
            <RouterView />
        </template>
        <template v-else>
            <div class="flex min-h-screen">
                <MemberSidebar :member-user="memberUser" @logout="requestLogout" />

                <MobileNavDrawer :open="mobileNavOpen" title="Member Portal" @close="closeMobileNav">
                    <MemberSidebar
                        mobile
                        :member-user="memberUser"
                        @logout="requestLogout"
                        @navigate="closeMobileNav"
                    />
                </MobileNavDrawer>

                <ConfirmDialog
                    :open="logoutConfirmOpen"
                    title="Log out?"
                    message="You will need to sign in again to access the member portal."
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
                                <NotificationBell context="member" />
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
                                            v-for="link in memberQuickLinks"
                                            :key="link.to"
                                            :to="link.to"
                                            class="block rounded-lg px-3 py-2 text-sm hover:bg-slate-50"
                                        >
                                            {{ link.label }}
                                        </RouterLink>
                                    </div>
                                </details>
                            </div>

                            <div class="flex items-center gap-3">
                                <NotificationBell context="member" />
                                <div class="flex items-center gap-3 rounded-xl border border-slate-200 px-3 py-2">
                                    <span class="max-w-[10rem] truncate text-sm font-medium text-slate-700">{{ memberUser?.name || 'Member' }}</span>
                                    <button type="button" class="text-slate-400 hover:text-institutional" title="Log out" @click="requestLogout">
                                        <ArrowRightOnRectangleIcon class="size-5" />
                                    </button>
                                </div>
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
