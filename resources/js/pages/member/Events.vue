<script setup>
import { computed, ref, watch, onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import {
    ArrowRightIcon,
    CalendarDaysIcon,
    CheckBadgeIcon,
    ClockIcon,
    MagnifyingGlassIcon,
    MapPinIcon,
    TicketIcon,
} from '@heroicons/vue/24/outline';
import AdminEmptyState from '../../components/admin/AdminEmptyState.vue';
import AdminPageIntro from '../../components/admin/AdminPageIntro.vue';
import AdminPanel from '../../components/admin/AdminPanel.vue';
import AdminToolbar from '../../components/admin/AdminToolbar.vue';
import EventBannerThumb from '../../components/admin/EventBannerThumb.vue';
import { publicApi } from '../../api/client';
import { useAuth } from '../../composables/useAuth';

const { getMemberClient } = useAuth();

const activeTab = ref('discover');
const events = ref([]);
const registrations = ref([]);
const search = ref('');
const loadingDiscover = ref(true);
const loadingRegistrations = ref(true);

let searchTimeout = null;

const upcomingRegistrations = computed(() => registrations.value.filter((registration) => {
    const startsAt = registration.event?.starts_at;

    return startsAt && new Date(startsAt) >= new Date();
}));

const pastRegistrations = computed(() => registrations.value.filter((registration) => {
    const startsAt = registration.event?.starts_at;

    return startsAt && new Date(startsAt) < new Date();
}));

const registeredEventUuids = computed(() => new Set(
    registrations.value.map((registration) => registration.event?.uuid).filter(Boolean),
));

function isEventRegistered(eventUuid) {
    return registeredEventUuids.value.has(eventUuid);
}

const searched = computed(() => search.value.trim().length > 0);

function formatEventDate(dateString) {
    const date = new Date(dateString);

    return {
        month: date.toLocaleString('en-US', { month: 'short' }).toUpperCase(),
        day: date.getDate(),
    };
}

function formatDateTime(dateString) {
    return new Date(dateString).toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}

function feeLabel(event) {
    if (!event.fee || Number(event.fee) <= 0) {
        return 'Free';
    }

    return `${event.currency} ${Number(event.fee).toLocaleString()}`;
}

function registrationStatusClass(status) {
    const map = {
        confirmed: 'bg-emerald-100 text-emerald-800',
        pending: 'bg-amber-100 text-amber-800',
        cancelled: 'bg-slate-100 text-slate-600',
    };

    return map[status] || 'bg-slate-100 text-slate-700';
}

async function loadEvents() {
    loadingDiscover.value = true;

    try {
        const { data } = await publicApi().get('/events', {
            params: { search: search.value.trim() || undefined },
        });
        events.value = data.data;
    } finally {
        loadingDiscover.value = false;
    }
}

async function loadRegistrations() {
    loadingRegistrations.value = true;

    try {
        const { data } = await getMemberClient().get('/events/registrations');
        registrations.value = data.data || [];
    } catch {
        registrations.value = [];
    } finally {
        loadingRegistrations.value = false;
    }
}

function switchTab(tab) {
    activeTab.value = tab;
}

watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(loadEvents, 300);
});

onMounted(async () => {
    await Promise.all([loadEvents(), loadRegistrations()]);
});
</script>

<template>
    <div>
        <AdminPageIntro description="Browse upcoming events and manage your registrations without leaving the member portal." />

        <div class="grid gap-4 sm:grid-cols-3">
            <div class="card-modern p-5">
                <div class="flex items-center gap-4">
                    <span class="metric-icon !size-12 !rounded-xl">
                        <CalendarDaysIcon class="size-6" />
                    </span>
                    <div>
                        <p class="text-sm text-slate-500">Available events</p>
                        <p class="text-2xl font-bold text-slate-900">{{ loadingDiscover ? '—' : events.length }}</p>
                    </div>
                </div>
            </div>
            <div class="card-modern p-5">
                <div class="flex items-center gap-4">
                    <span class="metric-icon !size-12 !rounded-xl">
                        <CheckBadgeIcon class="size-6" />
                    </span>
                    <div>
                        <p class="text-sm text-slate-500">My registrations</p>
                        <p class="text-2xl font-bold text-slate-900">{{ loadingRegistrations ? '—' : registrations.length }}</p>
                    </div>
                </div>
            </div>
            <div class="card-modern p-5">
                <div class="flex items-center gap-4">
                    <span class="metric-icon !size-12 !rounded-xl">
                        <ClockIcon class="size-6" />
                    </span>
                    <div>
                        <p class="text-sm text-slate-500">Upcoming registered</p>
                        <p class="text-2xl font-bold text-slate-900">{{ loadingRegistrations ? '—' : upcomingRegistrations.length }}</p>
                    </div>
                </div>
            </div>
        </div>

        <AdminPanel class="mt-6">
            <template #header>
                <div class="flex w-full flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div class="inline-flex rounded-xl border border-slate-200 bg-surface-muted p-1">
                        <button
                            type="button"
                            class="rounded-lg px-4 py-2 text-sm font-medium transition"
                            :class="activeTab === 'discover' ? 'bg-white text-institutional shadow-sm' : 'text-slate-600 hover:text-slate-900'"
                            @click="switchTab('discover')"
                        >
                            Discover events
                        </button>
                        <button
                            type="button"
                            class="rounded-lg px-4 py-2 text-sm font-medium transition"
                            :class="activeTab === 'registrations' ? 'bg-white text-institutional shadow-sm' : 'text-slate-600 hover:text-slate-900'"
                            @click="switchTab('registrations')"
                        >
                            My registrations
                            <span
                                v-if="registrations.length"
                                class="ml-1.5 rounded-full bg-institutional/10 px-2 py-0.5 text-xs text-institutional"
                            >
                                {{ registrations.length }}
                            </span>
                        </button>
                    </div>

                    <p class="text-sm text-text-secondary">
                        <template v-if="activeTab === 'discover'">
                            {{ events.length }} event{{ events.length === 1 ? '' : 's' }} available to register
                        </template>
                        <template v-else>
                            {{ upcomingRegistrations.length }} upcoming · {{ pastRegistrations.length }} past
                        </template>
                    </p>
                </div>
            </template>

            <AdminToolbar v-if="activeTab === 'discover'">
                <template #search>
                    <div class="relative">
                        <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 size-5 -translate-y-1/2 text-slate-400" />
                        <input
                            v-model="search"
                            type="search"
                            placeholder="Search by title, location, or keyword..."
                            class="input !rounded-xl !border-slate-200 !py-2.5 !pl-10 !pr-4"
                        />
                    </div>
                </template>
            </AdminToolbar>

            <div v-if="activeTab === 'discover'">
                <div v-if="loadingDiscover" class="flex justify-center py-16">
                    <div class="size-10 animate-spin rounded-full border-2 border-institutional/20 border-t-institutional" />
                </div>

                <AdminEmptyState
                    v-else-if="events.length === 0 && !searched"
                    title="No upcoming events right now"
                    description="New conferences and workshops will appear here when they are published. Check back soon or contact support if you expected to see an event."
                >
                    <template #icon>
                        <CalendarDaysIcon class="size-6" />
                    </template>
                </AdminEmptyState>

                <AdminEmptyState
                    v-else-if="events.length === 0"
                    title="No events match your search"
                    description="Try different keywords or clear the search to browse all available events."
                >
                    <template #icon>
                        <MagnifyingGlassIcon class="size-6" />
                    </template>
                    <template #action>
                        <button type="button" class="btn-secondary" @click="search = ''">Clear search</button>
                    </template>
                </AdminEmptyState>

                <div v-else class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                    <RouterLink
                        v-for="event in events"
                        :key="event.uuid"
                        :to="`/member/events/${event.uuid}`"
                        class="group hover-lift flex h-full flex-col overflow-hidden rounded-2xl border border-slate-100 bg-white transition hover:border-institutional/20"
                    >
                        <div class="relative aspect-[16/9] overflow-hidden">
                            <EventBannerThumb :url="event.banner_url" :title="event.title" variant="card" />
                            <div class="absolute left-4 top-4 rounded-xl bg-white/95 px-3 py-2 text-center shadow-sm">
                                <p class="text-[10px] font-bold tracking-wide text-institutional">{{ formatEventDate(event.starts_at).month }}</p>
                                <p class="text-xl font-bold leading-none text-slate-900">{{ formatEventDate(event.starts_at).day }}</p>
                            </div>
                        </div>
                        <div class="flex flex-1 flex-col p-5">
                            <div class="flex items-start justify-between gap-2">
                                <h2 class="font-display text-lg font-bold text-slate-900 group-hover:text-institutional">{{ event.title }}</h2>
                                <span
                                    v-if="isEventRegistered(event.uuid)"
                                    class="shrink-0 rounded-full bg-emerald-50 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide text-emerald-700 ring-1 ring-emerald-100"
                                >
                                    Registered
                                </span>
                            </div>
                            <div class="mt-3 space-y-2 text-sm text-slate-500">
                                <p class="flex items-center gap-2">
                                    <ClockIcon class="size-4 shrink-0" />
                                    {{ formatDateTime(event.starts_at) }}
                                </p>
                                <p class="flex items-center gap-2">
                                    <MapPinIcon class="size-4 shrink-0" />
                                    {{ event.location || event.virtual_url || 'Location TBA' }}
                                </p>
                                <p class="flex items-center gap-2">
                                    <TicketIcon class="size-4 shrink-0" />
                                    {{ feeLabel(event) }}
                                </p>
                            </div>
                            <span class="mt-auto inline-flex items-center gap-1 pt-4 text-sm font-semibold text-institutional">
                                {{ isEventRegistered(event.uuid) ? 'View your registration' : 'View & register' }}
                                <ArrowRightIcon class="size-4 transition group-hover:translate-x-0.5" />
                            </span>
                        </div>
                    </RouterLink>
                </div>
            </div>

            <div v-else>
                <div v-if="loadingRegistrations" class="flex justify-center py-16">
                    <div class="size-10 animate-spin rounded-full border-2 border-institutional/20 border-t-institutional" />
                </div>

                <AdminEmptyState
                    v-else-if="registrations.length === 0"
                    title="No registrations yet"
                    description="When you register for an event, it will show up here with your status, date, and location details."
                >
                    <template #icon>
                        <CalendarDaysIcon class="size-6" />
                    </template>
                    <template #action>
                        <button type="button" class="btn-institutional" @click="switchTab('discover')">
                            Browse events
                        </button>
                    </template>
                </AdminEmptyState>

                <div v-else class="space-y-8">
                    <section v-if="upcomingRegistrations.length">
                        <div class="mb-4 flex items-center justify-between gap-3">
                            <h3 class="font-display text-base font-bold text-slate-900">Upcoming</h3>
                            <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-700">
                                {{ upcomingRegistrations.length }} scheduled
                            </span>
                        </div>
                        <div class="grid gap-4 lg:grid-cols-2">
                            <article
                                v-for="registration in upcomingRegistrations"
                                :key="registration.uuid"
                                class="flex gap-4 rounded-2xl border border-slate-100 bg-surface-muted/40 p-4 transition hover:border-institutional/20 hover:bg-white"
                            >
                                <div class="flex size-16 shrink-0 flex-col items-center justify-center rounded-xl bg-institutional/10 text-institutional">
                                    <span class="text-[10px] font-bold uppercase">{{ formatEventDate(registration.event.starts_at).month }}</span>
                                    <span class="text-2xl font-bold leading-none">{{ formatEventDate(registration.event.starts_at).day }}</span>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-start justify-between gap-2">
                                        <h4 class="font-semibold text-slate-900">{{ registration.event?.title || 'Event' }}</h4>
                                        <span class="badge capitalize" :class="registrationStatusClass(registration.status)">
                                            {{ registration.status }}
                                        </span>
                                    </div>
                                    <p class="mt-1 text-sm text-slate-500">
                                        {{ formatDateTime(registration.event.starts_at) }}
                                    </p>
                                    <p class="mt-1 text-sm text-slate-500">
                                        {{ registration.event?.location || registration.event?.virtual_url || 'Location TBA' }}
                                    </p>
                                    <RouterLink
                                        v-if="registration.event?.uuid"
                                        :to="`/member/events/${registration.event.uuid}`"
                                        class="link-arrow mt-3"
                                    >
                                        View event details
                                    </RouterLink>
                                </div>
                            </article>
                        </div>
                    </section>

                    <section v-if="pastRegistrations.length">
                        <div class="mb-4 flex items-center justify-between gap-3">
                            <h3 class="font-display text-base font-bold text-slate-900">Past events</h3>
                            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600">
                                {{ pastRegistrations.length }} attended
                            </span>
                        </div>
                        <div class="overflow-hidden rounded-2xl border border-slate-100">
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-slate-200 text-sm">
                                    <thead class="bg-slate-50">
                                        <tr>
                                            <th class="px-4 py-3 text-left font-medium text-slate-600">Event</th>
                                            <th class="px-4 py-3 text-left font-medium text-slate-600">Date</th>
                                            <th class="px-4 py-3 text-left font-medium text-slate-600">Status</th>
                                            <th class="px-4 py-3 text-right font-medium text-slate-600">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 bg-white">
                                        <tr v-for="registration in pastRegistrations" :key="registration.uuid">
                                            <td class="px-4 py-3 font-medium text-slate-900">
                                                {{ registration.event?.title || 'Event' }}
                                            </td>
                                            <td class="px-4 py-3 text-slate-600">
                                                {{ registration.event?.starts_at ? formatDateTime(registration.event.starts_at) : '—' }}
                                            </td>
                                            <td class="px-4 py-3">
                                                <span class="badge capitalize" :class="registrationStatusClass(registration.status)">
                                                    {{ registration.status }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 text-right">
                                                <RouterLink
                                                    v-if="registration.event?.uuid"
                                                    :to="`/member/events/${registration.event.uuid}`"
                                                    class="font-medium text-institutional hover:underline"
                                                >
                                                    View
                                                </RouterLink>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </AdminPanel>
    </div>
</template>
