<script setup>
import { computed, ref, onMounted } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import {
    ArrowDownTrayIcon,
    CalendarDaysIcon,
    EyeIcon,
    ListBulletIcon,
    MapPinIcon,
    Squares2X2Icon,
    TicketIcon,
} from '@heroicons/vue/24/outline';
import AdminAlert from '../../components/admin/AdminAlert.vue';
import AdminEmptyState from '../../components/admin/AdminEmptyState.vue';
import AdminPageIntro from '../../components/admin/AdminPageIntro.vue';
import AdminPanel from '../../components/admin/AdminPanel.vue';
import EventBannerThumb from '../../components/admin/EventBannerThumb.vue';
import { useAuth } from '../../composables/useAuth';
import { downloadBlobResponse } from '../../utils/download';

const route = useRoute();
const router = useRouter();
const { getAdminClient } = useAuth();
const events = ref([]);
const loadingEvents = ref(true);
const exportingUuid = ref(null);
const exportError = ref('');

const MOBILE_LAYOUT_KEY = 'event-registrations-mobile-layout';
const mobileLayout = ref(localStorage.getItem(MOBILE_LAYOUT_KEY) || 'list');

function setMobileLayout(layout) {
    mobileLayout.value = layout;
    localStorage.setItem(MOBILE_LAYOUT_KEY, layout);
}

const filteredEvents = computed(() => {
    if (!route.query.event) {
        return events.value;
    }

    return events.value.filter((event) => event.uuid === route.query.event);
});

onMounted(async () => {
    await loadEvents();

    if (route.query.event) {
        router.replace({
            name: 'admin.event-registrations.show',
            params: { uuid: route.query.event },
        });
    }
});

async function loadEvents() {
    loadingEvents.value = true;
    try {
        const { data } = await getAdminClient().get('/events');
        events.value = data.data || [];
    } finally {
        loadingEvents.value = false;
    }
}

async function exportCsv(uuid) {
    exportError.value = '';
    exportingUuid.value = uuid;

    try {
        const event = events.value.find((item) => item.uuid === uuid);
        const response = await getAdminClient().get(`/events/${uuid}/registrations/export`, {
            responseType: 'blob',
        });

        const slug = event?.title
            ? event.title.replace(/[^a-z0-9]+/gi, '-').replace(/^-|-$/g, '').toLowerCase()
            : uuid;

        await downloadBlobResponse(response, `${slug}-registrations.csv`);
    } catch (e) {
        if (e.response?.data instanceof Blob) {
            try {
                const payload = JSON.parse(await e.response.data.text());
                exportError.value = payload.message || 'Unable to export registrations. Please try again.';
                return;
            } catch {
                // Fall through to generic message.
            }
        }

        exportError.value = e.message || 'Unable to export registrations. Please try again.';
    } finally {
        exportingUuid.value = null;
    }
}

function formatDate(value) {
    return value ? new Date(value).toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    }) : '—';
}

function registrantCount(event) {
    return event.registrations_count ?? event.confirmed_registrations_count ?? 0;
}

function eventDetailRoute(uuid) {
    return { name: 'admin.event-registrations.show', params: { uuid } };
}
</script>

<template>
    <div>
        <AdminPageIntro description="Track attendee registrations across upcoming and past association events." />

        <AdminAlert v-if="exportError" type="error">{{ exportError }}</AdminAlert>

        <div v-if="loadingEvents" class="empty-state bg-white">Loading events...</div>

        <AdminEmptyState
            v-else-if="!filteredEvents.length"
            title="No events to display"
            description="Create an event first, then track registrations here."
        >
            <template #icon><CalendarDaysIcon class="size-6" /></template>
        </AdminEmptyState>

        <template v-else>
            <div class="admin-content-stretch">
            <AdminPanel
                title="Events"
                :description="`${filteredEvents.length} event${filteredEvents.length === 1 ? '' : 's'} with registration activity.`"
                class="mb-6"
            >
                <template #actions>
                    <div class="view-toggle md:hidden" role="group" aria-label="Layout view">
                        <button
                            type="button"
                            class="view-toggle-btn"
                            :class="{ 'view-toggle-btn-active': mobileLayout === 'list' }"
                            aria-label="List view"
                            @click="setMobileLayout('list')"
                        >
                            <ListBulletIcon class="size-5" />
                        </button>
                        <button
                            type="button"
                            class="view-toggle-btn"
                            :class="{ 'view-toggle-btn-active': mobileLayout === 'grid' }"
                            aria-label="Grid view"
                            @click="setMobileLayout('grid')"
                        >
                            <Squares2X2Icon class="size-5" />
                        </button>
                    </div>
                </template>

                <div
                    class="md:hidden"
                    :class="mobileLayout === 'grid' ? 'grid grid-cols-2 gap-3 p-3' : 'divide-y divide-slate-100'"
                >
                    <article
                        v-for="event in filteredEvents"
                        :key="event.uuid"
                        :class="mobileLayout === 'grid' ? 'event-mobile-card-grid' : 'space-y-4 p-4'"
                    >
                        <div :class="mobileLayout === 'list' ? '-mx-4' : ''">
                            <EventBannerThumb :url="event.banner_url" :title="event.title" variant="card" />
                        </div>

                        <div :class="mobileLayout === 'grid' ? 'flex flex-1 flex-col gap-2 p-3' : 'space-y-4'">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p
                                        class="font-display font-bold text-slate-900"
                                        :class="mobileLayout === 'grid' ? 'line-clamp-2 text-sm' : 'text-lg'"
                                    >
                                        {{ event.title }}
                                    </p>
                                    <p
                                        class="mt-1 flex items-center gap-1 text-slate-600"
                                        :class="mobileLayout === 'grid' ? 'text-xs' : 'gap-1.5 text-sm'"
                                    >
                                        <CalendarDaysIcon class="size-3.5 shrink-0 text-slate-400 sm:size-4" />
                                        {{ formatDate(event.starts_at) }}
                                    </p>
                                    <p
                                        v-if="mobileLayout === 'list'"
                                        class="mt-1 flex items-center gap-1.5 text-sm text-slate-600"
                                    >
                                        <MapPinIcon class="size-4 shrink-0 text-slate-400" />
                                        {{ event.location || 'Location TBA' }}
                                    </p>
                                </div>
                                <span
                                    class="inline-flex shrink-0 items-center gap-1 rounded-full bg-brand-50 font-semibold text-institutional ring-1 ring-brand-100"
                                    :class="mobileLayout === 'grid' ? 'px-2 py-0.5 text-xs' : 'px-3 py-1 text-sm'"
                                >
                                    <TicketIcon class="size-3.5" />
                                    {{ registrantCount(event) }}
                                </span>
                            </div>

                            <div
                                class="flex gap-2"
                                :class="mobileLayout === 'grid' ? 'mt-auto flex-col' : 'flex-col sm:flex-row'"
                            >
                                <RouterLink
                                    :to="eventDetailRoute(event.uuid)"
                                    class="btn-primary inline-flex w-full items-center justify-center gap-1.5 !rounded-xl"
                                    :class="mobileLayout === 'grid' ? '!px-2 !py-2 text-xs' : 'sm:w-auto'"
                                >
                                    <EyeIcon class="size-4" />
                                    {{ mobileLayout === 'grid' ? 'View' : 'View registrants' }}
                                </RouterLink>
                                <button
                                    type="button"
                                    class="btn-secondary inline-flex w-full items-center justify-center gap-1.5 !rounded-xl"
                                    :class="mobileLayout === 'grid' ? '!px-2 !py-2 text-xs' : 'sm:w-auto'"
                                    :disabled="exportingUuid === event.uuid"
                                    @click="exportCsv(event.uuid)"
                                >
                                    <ArrowDownTrayIcon class="size-4" />
                                    {{ exportingUuid === event.uuid ? '…' : (mobileLayout === 'grid' ? 'CSV' : 'Export CSV') }}
                                </button>
                            </div>
                        </div>
                    </article>
                </div>

                <div class="hidden overflow-x-auto md:block md:px-0">
                    <table class="admin-table w-full min-w-[860px]">
                        <thead>
                            <tr>
                                <th>Event</th>
                                <th>Date</th>
                                <th>Location</th>
                                <th>Registrants</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="event in filteredEvents"
                                :key="event.uuid"
                            >
                                <td>
                                    <div class="flex items-center gap-3">
                                        <EventBannerThumb :url="event.banner_url" :title="event.title" variant="thumb" />
                                        <div class="min-w-0">
                                            <p class="truncate font-medium text-slate-900">{{ event.title }}</p>
                                            <p class="mt-0.5 text-xs text-slate-500">{{ event.status || 'draft' }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="inline-flex items-center gap-1.5 text-slate-600">
                                        <CalendarDaysIcon class="size-4 shrink-0 text-slate-400" />
                                        {{ formatDate(event.starts_at) }}
                                    </span>
                                </td>
                                <td>
                                    <span class="inline-flex items-center gap-1.5 text-slate-600">
                                        <MapPinIcon class="size-4 shrink-0 text-slate-400" />
                                        {{ event.location || '—' }}
                                    </span>
                                </td>
                                <td>
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-50 px-3 py-1 text-sm font-semibold text-institutional ring-1 ring-brand-100">
                                        <TicketIcon class="size-4" />
                                        {{ registrantCount(event) }}
                                    </span>
                                </td>
                                <td class="text-right">
                                    <div class="admin-table-actions justify-end">
                                        <RouterLink
                                            :to="eventDetailRoute(event.uuid)"
                                            class="btn-primary inline-flex items-center gap-1.5 !rounded-xl !px-3 !py-2 text-xs"
                                        >
                                            <EyeIcon class="size-4" />
                                            View
                                        </RouterLink>
                                        <button
                                            type="button"
                                            class="btn-secondary inline-flex items-center gap-1.5 !rounded-xl !px-3 !py-2 text-xs"
                                            :disabled="exportingUuid === event.uuid"
                                            @click="exportCsv(event.uuid)"
                                        >
                                            <ArrowDownTrayIcon class="size-4" />
                                            {{ exportingUuid === event.uuid ? 'Exporting…' : 'CSV' }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </AdminPanel>
            </div>
        </template>
    </div>
</template>
