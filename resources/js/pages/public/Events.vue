<script setup>
import { computed, ref, watch, onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import {
    CalendarDaysIcon,
    ClockIcon,
    MagnifyingGlassIcon,
    MapPinIcon,
    TicketIcon,
} from '@heroicons/vue/24/outline';
import { publicApi } from '../../api/client';
import { stripHtml } from '../../utils/html';
import EventBannerThumb from '../../components/admin/EventBannerThumb.vue';

const events = ref([]);
const search = ref('');
const loading = ref(true);
const searched = ref(false);

let searchTimeout = null;

function isUpcoming(event) {
    const now = Date.now();
    const endsAt = event.ends_at ? new Date(event.ends_at).getTime() : null;
    const startsAt = new Date(event.starts_at).getTime();

    if (endsAt) {
        return endsAt >= now;
    }

    return startsAt >= now;
}

const upcomingEvents = computed(() => events.value.filter(isUpcoming));
const pastEvents = computed(() => events.value.filter((event) => ! isUpcoming(event)));

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
    if (! event.fee || Number(event.fee) <= 0) {
        return 'Free';
    }

    return `${event.currency} ${Number(event.fee).toLocaleString()}`;
}

async function load() {
    loading.value = true;
    searched.value = search.value.trim().length > 0;

    try {
        const { data } = await publicApi().get('/events', {
            params: {
                search: search.value.trim() || undefined,
                upcoming: false,
                per_page: 50,
            },
        });
        events.value = data.data;
    } finally {
        loading.value = false;
    }
}

watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(load, 300);
});

onMounted(load);
</script>

<template>
    <div>
        <!-- Page header -->
        <section class="border-b border-slate-200/80 bg-gradient-to-br from-surface-muted via-white to-institutional/5">
            <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 sm:py-16 lg:px-8">
                <div class="flex flex-col gap-8 lg:flex-row lg:items-end lg:justify-between">
                    <div class="max-w-2xl">
                        <p class="label-caps text-institutional">Calendar</p>
                        <h1 class="mt-2 font-display text-4xl font-bold tracking-tight text-institutional-dark sm:text-5xl">
                            Events
                        </h1>
                        <p class="mt-4 text-lg leading-relaxed text-text-secondary">
                            Conferences, workshops, and association gatherings — register online and connect with peers.
                        </p>
                    </div>

                    <div class="relative w-full max-w-md shrink-0">
                        <MagnifyingGlassIcon class="pointer-events-none absolute left-4 top-1/2 size-5 -translate-y-1/2 text-text-secondary" aria-hidden="true" />
                        <input
                            v-model="search"
                            type="search"
                            placeholder="Search events..."
                            class="input !rounded-xl !border-slate-200/80 !py-3 !pl-12 !shadow-sm focus:!border-institutional focus:!ring-institutional/20"
                        />
                    </div>
                </div>

                <div v-if="! loading && events.length > 0" class="mt-10 flex flex-wrap gap-3">
                    <span
                        v-if="upcomingEvents.length"
                        class="inline-flex items-center gap-2 rounded-full bg-white px-4 py-2 text-sm font-medium text-institutional-dark shadow-sm ring-1 ring-slate-200/80"
                    >
                        <CalendarDaysIcon class="size-4 text-institutional" aria-hidden="true" />
                        {{ upcomingEvents.length }} upcoming
                    </span>
                    <span
                        v-if="pastEvents.length"
                        class="inline-flex items-center gap-2 rounded-full bg-white px-4 py-2 text-sm font-medium text-slate-600 shadow-sm ring-1 ring-slate-200/80"
                    >
                        {{ pastEvents.length }} past
                    </span>
                    <span v-if="searched" class="inline-flex items-center rounded-full bg-institutional/8 px-4 py-2 text-sm font-medium text-institutional">
                        Filtered results
                    </span>
                </div>
            </div>
        </section>

        <!-- Content -->
        <section class="section-padding bg-white">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div v-if="loading" class="flex justify-center py-16">
                    <div class="size-10 animate-spin rounded-full border-2 border-institutional/20 border-t-institutional" />
                </div>

                <div v-else-if="events.length === 0 && ! searched" class="empty-state mx-auto max-w-2xl">
                    <CalendarDaysIcon class="mx-auto size-12 text-institutional/25" aria-hidden="true" />
                    <h2 class="mt-5 font-display text-2xl font-bold text-institutional-dark">No events yet</h2>
                    <p class="mx-auto mt-3 max-w-md text-base leading-relaxed text-text-secondary">
                        New conferences and workshops will be announced here. Join as a member to get early notifications.
                    </p>
                    <RouterLink to="/member/register" class="btn-gold mt-8 inline-flex">
                        Become a member
                    </RouterLink>
                </div>

                <div v-else-if="events.length === 0" class="empty-state mx-auto max-w-xl">
                    <MagnifyingGlassIcon class="mx-auto size-10 text-institutional/25" aria-hidden="true" />
                    <h2 class="mt-4 font-display text-xl font-bold text-institutional-dark">No events match your search</h2>
                    <p class="mt-2 text-sm text-text-secondary">Try different keywords or browse all events.</p>
                    <button type="button" class="link-arrow mt-4" @click="search = ''">
                        Clear search
                    </button>
                </div>

                <template v-else>
                    <div v-if="upcomingEvents.length" class="mb-10">
                        <h2 class="mb-6 font-display text-2xl font-bold text-institutional-dark">Upcoming</h2>
                        <div class="grid auto-rows-fr gap-8 sm:grid-cols-2 xl:grid-cols-3">
                            <article
                                v-for="event in upcomingEvents"
                                :key="event.uuid"
                                class="hover-lift card-modern flex h-full flex-col overflow-hidden"
                            >
                                <EventBannerThumb :url="event.banner_url" :title="event.title" variant="card" />

                                <div class="flex items-center gap-4 border-b border-slate-100 bg-gradient-to-r from-institutional/5 to-transparent px-6 py-5">
                                    <div class="flex size-16 shrink-0 flex-col items-center justify-center rounded-xl bg-institutional text-white shadow-md shadow-institutional/25">
                                        <span class="text-[10px] font-bold uppercase tracking-wider">{{ formatEventDate(event.starts_at).month }}</span>
                                        <span class="font-display text-2xl font-bold leading-none">{{ formatEventDate(event.starts_at).day }}</span>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <span
                                            class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                            :class="Number(event.fee) > 0 ? 'bg-accent-gold/20 text-institutional-dark' : 'bg-emerald-100 text-emerald-800'"
                                        >
                                            {{ feeLabel(event) }}
                                        </span>
                                        <h2 class="mt-2 line-clamp-2 font-display text-lg font-semibold text-institutional-dark">
                                            {{ event.title }}
                                        </h2>
                                    </div>
                                </div>

                                <div class="flex flex-1 flex-col p-6">
                                    <p class="line-clamp-3 flex-1 text-sm leading-relaxed text-text-secondary">
                                        {{ stripHtml(event.description) }}
                                    </p>

                                    <dl class="mt-5 space-y-3 text-sm">
                                        <div class="flex items-start gap-2.5 text-text-secondary">
                                            <ClockIcon class="mt-0.5 size-4 shrink-0 text-institutional/70" aria-hidden="true" />
                                            <div>
                                                <dt class="sr-only">When</dt>
                                                <dd>{{ formatDateTime(event.starts_at) }}</dd>
                                            </div>
                                        </div>
                                        <div class="flex items-start gap-2.5 text-text-secondary">
                                            <MapPinIcon class="mt-0.5 size-4 shrink-0 text-institutional/70" aria-hidden="true" />
                                            <div>
                                                <dt class="sr-only">Where</dt>
                                                <dd>{{ event.location || 'Virtual' }}</dd>
                                            </div>
                                        </div>
                                    </dl>

                                    <RouterLink :to="`/events/${event.uuid}`" class="btn-institutional mt-6 inline-flex w-full justify-center !py-2.5 text-sm sm:w-auto">
                                        <TicketIcon class="mr-2 size-4" aria-hidden="true" />
                                        View & Register
                                    </RouterLink>
                                </div>
                            </article>
                        </div>
                    </div>

                    <div v-else-if="! searched" class="empty-state mx-auto mb-10 max-w-2xl">
                        <CalendarDaysIcon class="mx-auto size-12 text-institutional/25" aria-hidden="true" />
                        <h2 class="mt-5 font-display text-2xl font-bold text-institutional-dark">No upcoming events</h2>
                        <p class="mx-auto mt-3 max-w-md text-base leading-relaxed text-text-secondary">
                            New conferences and workshops will be announced here. Past events are listed below when available.
                        </p>
                    </div>

                    <div v-if="pastEvents.length">
                        <h2 class="mb-6 font-display text-2xl font-bold text-slate-700">Past events</h2>
                        <div class="grid auto-rows-fr gap-8 sm:grid-cols-2 xl:grid-cols-3">
                            <article
                                v-for="event in pastEvents"
                                :key="event.uuid"
                                class="card-modern flex h-full flex-col overflow-hidden opacity-90"
                            >
                                <EventBannerThumb :url="event.banner_url" :title="event.title" variant="card" />

                                <div class="flex items-center gap-4 border-b border-slate-100 bg-slate-50 px-6 py-5">
                                    <div class="flex size-16 shrink-0 flex-col items-center justify-center rounded-xl bg-slate-400 text-white">
                                        <span class="text-[10px] font-bold uppercase tracking-wider">{{ formatEventDate(event.starts_at).month }}</span>
                                        <span class="font-display text-2xl font-bold leading-none">{{ formatEventDate(event.starts_at).day }}</span>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <span class="inline-flex rounded-full bg-slate-200 px-2.5 py-0.5 text-xs font-semibold text-slate-600">
                                            Ended
                                        </span>
                                        <h2 class="mt-2 line-clamp-2 font-display text-lg font-semibold text-slate-800">
                                            {{ event.title }}
                                        </h2>
                                    </div>
                                </div>

                                <div class="flex flex-1 flex-col p-6">
                                    <p class="line-clamp-3 flex-1 text-sm leading-relaxed text-text-secondary">
                                        {{ stripHtml(event.description) }}
                                    </p>

                                    <dl class="mt-5 space-y-3 text-sm">
                                        <div class="flex items-start gap-2.5 text-text-secondary">
                                            <ClockIcon class="mt-0.5 size-4 shrink-0 text-slate-400" aria-hidden="true" />
                                            <div>
                                                <dt class="sr-only">When</dt>
                                                <dd>{{ formatDateTime(event.starts_at) }}</dd>
                                            </div>
                                        </div>
                                        <div class="flex items-start gap-2.5 text-text-secondary">
                                            <MapPinIcon class="mt-0.5 size-4 shrink-0 text-slate-400" aria-hidden="true" />
                                            <div>
                                                <dt class="sr-only">Where</dt>
                                                <dd>{{ event.location || 'Virtual' }}</dd>
                                            </div>
                                        </div>
                                    </dl>

                                    <RouterLink :to="`/events/${event.uuid}`" class="btn-secondary mt-6 inline-flex w-full justify-center !py-2.5 text-sm sm:w-auto">
                                        View details
                                    </RouterLink>
                                </div>
                            </article>
                        </div>
                    </div>
                </template>
            </div>
        </section>

        <!-- News upsell -->
        <section class="border-t border-slate-200/80 bg-surface-muted py-14 sm:py-16">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="card-modern flex flex-col items-center gap-6 p-8 text-center sm:p-10 lg:flex-row lg:text-left">
                    <div class="flex size-16 shrink-0 items-center justify-center rounded-2xl bg-institutional/10 text-institutional">
                        <CalendarDaysIcon class="size-8" aria-hidden="true" />
                    </div>
                    <div class="flex-1">
                        <h2 class="font-display text-2xl font-bold text-institutional-dark">Miss an announcement?</h2>
                        <p class="mt-2 max-w-2xl text-text-secondary">
                            Visit the news center for the latest association updates, press releases, and event reminders.
                        </p>
                    </div>
                    <RouterLink to="/news" class="btn-institutional shrink-0">
                        View news
                    </RouterLink>
                </div>
            </div>
        </section>
    </div>
</template>
