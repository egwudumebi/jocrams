<script setup>
import { ref, watch } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import {
    CalendarDaysIcon,
    ChartBarIcon,
    ChevronDownIcon,
    Cog6ToothIcon,
    DocumentTextIcon,
    HomeIcon,
    LifebuoyIcon,
    UsersIcon,
} from '@heroicons/vue/24/outline';
import {
    adminNavSections,
    adminStandaloneLinks,
    sectionForRoute,
} from '../../config/adminNavigation';

defineProps({
    adminUser: {
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
const openSections = ref(['membership', 'content']);

const sectionIcons = {
    membership: UsersIcon,
    events: CalendarDaysIcon,
    content: DocumentTextIcon,
};

const standaloneIcons = {
    'admin.reports': ChartBarIcon,
    'admin.settings': Cog6ToothIcon,
};

watch(
    () => route.name,
    (name) => {
        const section = sectionForRoute(name);
        if (section && !openSections.value.includes(section)) {
            openSections.value = [...openSections.value, section];
        }
    },
    { immediate: true },
);

function toggleSection(id) {
    if (openSections.value.includes(id)) {
        openSections.value = openSections.value.filter((section) => section !== id);
    } else {
        openSections.value = [...openSections.value, id];
    }
}

function isActive(name, path) {
    return route.name === name || route.path === path || route.path.startsWith(`${path}/`);
}
</script>

<template>
    <aside
        class="flex flex-col border-r border-slate-200 bg-white"
        :class="mobile ? 'h-full w-full' : 'sticky top-0 hidden h-screen w-72 shrink-0 lg:flex'"
    >
        <div class="border-b border-slate-100 px-6 py-5">
            <RouterLink to="/admin/dashboard" class="flex items-center gap-3">
                <span class="flex size-10 items-center justify-center rounded-xl bg-institutional text-sm font-bold text-white shadow-sm">J</span>
                <div>
                    <p class="font-display text-lg font-bold leading-tight text-institutional-dark">Jocrams</p>
                    <p class="text-xs text-text-secondary">Association Admin</p>
                </div>
            </RouterLink>
        </div>

        <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4">
            <RouterLink
                to="/admin/dashboard"
                class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition"
                :class="route.name === 'admin.dashboard' ? 'bg-institutional text-white shadow-sm' : 'text-slate-600 hover:bg-slate-50'"
                @click="$emit('navigate')"
            >
                <HomeIcon class="size-5 shrink-0" />
                Dashboard
            </RouterLink>

            <div v-for="section in adminNavSections" :key="section.id" class="pt-2">
                <button
                    type="button"
                    class="flex w-full items-center justify-between rounded-xl px-3 py-2.5 text-left text-sm font-semibold text-slate-700 hover:bg-slate-50"
                    @click="toggleSection(section.id)"
                >
                    <span class="flex items-center gap-3">
                        <component :is="sectionIcons[section.id]" class="size-5 shrink-0 text-slate-400" />
                        {{ section.label }}
                    </span>
                    <ChevronDownIcon
                        class="size-4 text-slate-400 transition"
                        :class="openSections.includes(section.id) ? 'rotate-180' : ''"
                    />
                </button>
                <div v-show="openSections.includes(section.id)" class="mt-1 space-y-0.5 border-l border-slate-100 pl-3 ml-3">
                    <RouterLink
                        v-for="item in section.items"
                        :key="item.name"
                        :to="item.to"
                        class="block rounded-lg px-3 py-2 text-sm transition"
                        :class="isActive(item.name, item.to) ? 'bg-brand-50 font-medium text-institutional' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-700'"
                        @click="$emit('navigate')"
                    >
                        {{ item.label }}
                    </RouterLink>
                </div>
            </div>

            <div class="border-t border-slate-100 pt-3">
                <RouterLink
                    v-for="link in adminStandaloneLinks"
                    :key="link.name"
                    :to="link.to"
                    class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition"
                    :class="route.name === link.name ? 'bg-brand-50 text-institutional' : 'text-slate-600 hover:bg-slate-50'"
                    @click="$emit('navigate')"
                >
                    <component :is="standaloneIcons[link.name]" class="size-5 shrink-0 text-slate-400" />
                    {{ link.label }}
                </RouterLink>
            </div>
        </nav>

        <div class="border-t border-slate-100 p-4">
            <RouterLink
                to="/admin/help"
                class="mb-3 flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm transition"
                :class="route.name === 'admin.help' ? 'bg-brand-50 font-medium text-institutional' : 'text-slate-500 hover:bg-slate-50 hover:text-institutional'"
                @click="$emit('navigate')"
            >
                <LifebuoyIcon class="size-5" />
                Help & Support
            </RouterLink>
            <div class="flex items-center justify-between gap-3 rounded-xl bg-surface-muted px-3 py-3">
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-slate-800">{{ adminUser?.name || 'Admin User' }}</p>
                    <button type="button" class="text-xs font-medium text-institutional hover:underline" @click="$emit('logout')">
                        Log Out
                    </button>
                </div>
                <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-institutional/10 text-sm font-semibold text-institutional">
                    {{ (adminUser?.name || 'A').charAt(0) }}
                </span>
            </div>
        </div>
    </aside>
</template>
