<script setup>
import { RouterLink, useRoute } from 'vue-router';
import {
    ArrowDownTrayIcon,
    CalendarDaysIcon,
    CreditCardIcon,
    DocumentTextIcon,
    HomeIcon,
    IdentificationIcon,
    LifebuoyIcon,
    NewspaperIcon,
    UserCircleIcon,
} from '@heroicons/vue/24/outline';
import { memberNavLinks } from '../../config/memberNavigation';

defineProps({
    memberUser: {
        type: Object,
        default: null,
    },
    mobile: {
        type: Boolean,
        default: false,
    },
});

defineEmits(['logout', 'navigate']);

const route = useRoute();

const linkIcons = {
    'member.dashboard': HomeIcon,
    'member.applications': NewspaperIcon,
    'member.events': CalendarDaysIcon,
    'member.payments': CreditCardIcon,
    'member.credentials': IdentificationIcon,
    'member.downloads': ArrowDownTrayIcon,
    'member.journal': DocumentTextIcon,
    'member.profile': UserCircleIcon,
    'member.support': LifebuoyIcon,
};

function isActive(name, path) {
    if (name === 'member.events') {
        return route.name === name || route.name === 'member.events.show';
    }

    if (name === 'member.journal') {
        return route.path.startsWith('/member/journal');
    }

    return route.name === name || route.path === path || route.path.startsWith(`${path}/`);
}
</script>

<template>
    <aside
        class="flex flex-col border-r border-slate-200 bg-white"
        :class="mobile ? 'h-full w-full' : 'sticky top-0 hidden h-screen w-72 shrink-0 lg:flex'"
    >
        <div class="border-b border-slate-100 px-6 py-5">
            <RouterLink to="/member/dashboard" class="flex items-center gap-3" @click="$emit('navigate')">
                <span class="flex size-10 items-center justify-center rounded-xl bg-institutional text-sm font-bold text-white shadow-sm">J</span>
                <div>
                    <p class="font-display text-lg font-bold leading-tight text-institutional-dark">Jocrams</p>
                    <p class="text-xs text-text-secondary">Member Portal</p>
                </div>
            </RouterLink>
        </div>

        <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4">
            <RouterLink
                v-for="item in memberNavLinks"
                :key="item.name"
                :to="item.to"
                class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition"
                :class="isActive(item.name, item.to) ? 'bg-institutional text-white shadow-sm' : 'text-slate-600 hover:bg-slate-50'"
                @click="$emit('navigate')"
            >
                <component :is="linkIcons[item.name]" class="size-5 shrink-0" />
                {{ item.label }}
            </RouterLink>
        </nav>

        <div class="border-t border-slate-100 p-4">
            <RouterLink
                to="/"
                target="_blank"
                class="mb-3 block rounded-lg px-2 py-1.5 text-sm text-slate-500 transition hover:bg-slate-50 hover:text-institutional"
            >
                Public site ↗
            </RouterLink>
            <div class="flex items-center justify-between gap-3 rounded-xl bg-surface-muted px-3 py-3">
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-slate-800">{{ memberUser?.name || 'Member' }}</p>
                    <button type="button" class="text-xs font-medium text-institutional hover:underline" @click="$emit('logout')">
                        Log Out
                    </button>
                </div>
                <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-institutional/10 text-sm font-semibold text-institutional">
                    {{ (memberUser?.name || 'M').charAt(0) }}
                </span>
            </div>
        </div>
    </aside>
</template>
