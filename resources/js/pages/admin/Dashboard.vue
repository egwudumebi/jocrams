<script setup>
import { computed, onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import {
    ArrowDownTrayIcon,
    ArrowTrendingUpIcon,
    CalendarDaysIcon,
    DocumentTextIcon,
    PlusIcon,
    UserGroupIcon,
} from '@heroicons/vue/24/outline';
import { useAuth } from '../../composables/useAuth';
import ExodusAreaChart from '../../components/charts/ExodusAreaChart.vue';

const { getAdminClient } = useAuth();
const loading = ref(true);
const dashboard = ref(null);
const message = ref('');

onMounted(async () => {
    try {
        const { data } = await getAdminClient().get('/dashboard');
        dashboard.value = data;
    } finally {
        loading.value = false;
    }
});

const metrics = computed(() => dashboard.value?.metrics ?? null);
const stats = computed(() => dashboard.value?.stats ?? null);

const registrationChartSeries = computed(() => {
    const analytics = dashboard.value?.registration_analytics;

    if (!analytics) {
        return [];
    }

    return [
        {
            key: 'registrations',
            name: 'Registrations',
            values: analytics.registrations ?? [],
            stroke: '#38bdf8',
            fillFrom: '#38bdf8',
            fillTo: '#0ea5e9',
        },
        {
            key: 'renewals',
            name: 'Renewals',
            values: analytics.renewals ?? [],
            stroke: '#fbbf24',
            fillFrom: '#fbbf24',
            fillTo: '#f59e0b',
        },
    ];
});

const registrationTotals = computed(() => {
    const analytics = dashboard.value?.registration_analytics;

    if (!analytics) {
        return { registrations: 0, renewals: 0 };
    }

    return {
        registrations: (analytics.registrations ?? []).reduce((sum, value) => sum + value, 0),
        renewals: (analytics.renewals ?? []).reduce((sum, value) => sum + value, 0),
    };
});

function formatNumber(value) {
    return Number(value || 0).toLocaleString();
}

function formatDate(value) {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
}

function eventDay(value) {
    if (!value) {
        return '—';
    }

    return new Date(value).getDate();
}

function eventMonth(value) {
    if (!value) {
        return '';
    }

    return new Date(value).toLocaleDateString(undefined, { month: 'short' });
}

function statusClass(label) {
    const map = {
        'Pending Review': 'bg-amber-100 text-amber-800',
        Approved: 'bg-emerald-100 text-emerald-800',
        'Awaiting Payment': 'bg-slate-100 text-slate-700',
        Rejected: 'bg-red-100 text-red-800',
        Cancelled: 'bg-slate-100 text-slate-600',
    };

    return map[label] || 'bg-slate-100 text-slate-700';
}

function eventStatusClass(status) {
    return status === 'published'
        ? 'bg-emerald-100 text-emerald-800'
        : 'bg-amber-100 text-amber-800';
}

function sparklinePoints(values) {
    const data = values?.length ? values : [1, 3, 2, 5, 4, 6, 7];
    const max = Math.max(...data, 1);

    return data
        .map((value, index) => {
            const x = (index / Math.max(data.length - 1, 1)) * 100;
            const y = 100 - (value / max) * 100;

            return `${x},${y}`;
        })
        .join(' ');
}

async function approveApplication(application) {
    await getAdminClient().post(`/applications/${application.uuid}/approve`, { notes: 'Approved from dashboard' });
    message.value = 'Application approved.';
    await refreshDashboard();
}

async function rejectApplication(application) {
    const reason = window.prompt('Rejection reason:');
    if (!reason) {
        return;
    }

    await getAdminClient().post(`/applications/${application.uuid}/reject`, { reason });
    message.value = 'Application rejected.';
    await refreshDashboard();
}

async function refreshDashboard() {
    const { data } = await getAdminClient().get('/dashboard');
    dashboard.value = data;
}

const quickReports = [
    { label: 'Monthly Member Growth', href: '/api/v1/admin/exports/members?format=csv', action: 'Download' },
    { label: 'Event Attendance', to: '/admin/event-registrations', action: 'Open' },
    { label: 'Dues Collection Status', href: '/api/v1/admin/exports/payments?format=csv', action: 'Download' },
];
</script>

<template>
    <div>
        <p v-if="message" class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ message }}
        </p>

        <div v-if="loading" class="rounded-2xl border border-dashed border-slate-200 bg-white px-8 py-16 text-center text-slate-500">
            Loading dashboard...
        </div>

        <template v-else-if="dashboard">
            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <article class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm sm:p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-xs font-medium text-slate-500 sm:text-sm">Total Active Members</p>
                            <p class="mt-2 text-3xl font-bold text-slate-900">{{ formatNumber(metrics?.active_members?.value ?? stats?.members?.active) }}</p>
                            <p class="mt-1 flex items-center gap-1 text-sm font-medium text-emerald-600">
                                <ArrowTrendingUpIcon class="size-4" />
                                {{ metrics?.active_members?.change_percent ?? 0 }}%
                            </p>
                        </div>
                        <span class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-brand-50 text-institutional">
                            <UserGroupIcon class="size-6" />
                        </span>
                    </div>
                    <svg viewBox="0 0 100 24" class="mt-4 h-8 w-full text-emerald-500" preserveAspectRatio="none">
                        <polyline fill="none" stroke="currentColor" stroke-width="2" :points="sparklinePoints(dashboard.registration_analytics?.registrations)" />
                    </svg>
                </article>

                <article class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm sm:p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-xs font-medium text-slate-500 sm:text-sm">New Applications (Current Month)</p>
                            <p class="mt-2 text-3xl font-bold text-slate-900">{{ formatNumber(metrics?.new_applications_month?.value ?? stats?.applications?.pending) }}</p>
                            <p class="mt-1 flex items-center gap-1 text-sm font-medium text-emerald-600">
                                <ArrowTrendingUpIcon class="size-4" />
                                {{ metrics?.new_applications_month?.change_percent ?? 0 }}%
                            </p>
                        </div>
                        <span class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-700">
                            <DocumentTextIcon class="size-6" />
                        </span>
                    </div>
                    <svg viewBox="0 0 100 24" class="mt-4 h-8 w-full text-emerald-500" preserveAspectRatio="none">
                        <polyline fill="none" stroke="currentColor" stroke-width="2" :points="sparklinePoints(dashboard.registration_analytics?.renewals)" />
                    </svg>
                </article>

                <article class="rounded-2xl border border-amber-200 bg-white p-4 shadow-md shadow-amber-100/60 sm:p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-xs font-medium text-slate-500 sm:text-sm">Upcoming Events (Active)</p>
                            <p class="mt-2 text-3xl font-bold text-slate-900">{{ formatNumber(metrics?.upcoming_events) }}</p>
                        </div>
                        <span class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-amber-50 text-amber-700">
                            <CalendarDaysIcon class="size-6" />
                        </span>
                    </div>
                </article>

                <article class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm sm:p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-xs font-medium text-slate-500 sm:text-sm">Recently Published Documents</p>
                            <p class="mt-2 text-3xl font-bold text-slate-900">{{ formatNumber(metrics?.published_documents) }}</p>
                        </div>
                        <span class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-slate-100 text-slate-600">
                            <DocumentTextIcon class="size-6" />
                        </span>
                    </div>
                </article>
            </section>

            <section class="mt-6 grid gap-6 xl:grid-cols-3">
                <div class="xl:col-span-2 space-y-6">
                    <article class="rounded-2xl border border-slate-200/80 bg-white shadow-sm">
                        <div class="flex flex-col gap-3 border-b border-slate-100 px-4 py-4 sm:flex-row sm:items-end sm:justify-between sm:px-5">
                            <div>
                                <h2 class="font-display text-base font-bold text-slate-900 sm:text-lg">Member Registration Analytics</h2>
                                <p class="mt-1 text-xs text-slate-500">12-month trend · hover chart for details</p>
                            </div>
                            <div class="flex gap-4 text-sm">
                                <div>
                                    <p class="text-xs text-slate-500">Registrations</p>
                                    <p class="font-bold tabular-nums text-slate-900">{{ formatNumber(registrationTotals.registrations) }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-slate-500">Renewals</p>
                                    <p class="font-bold tabular-nums text-slate-900">{{ formatNumber(registrationTotals.renewals) }}</p>
                                </div>
                            </div>
                        </div>
                        <div class="p-4 sm:p-5">
                            <ExodusAreaChart
                                v-if="dashboard.registration_analytics"
                                :labels="dashboard.registration_analytics.labels"
                                :series="registrationChartSeries"
                                :target="dashboard.registration_analytics.target"
                                :height="260"
                            />
                        </div>
                    </article>

                    <article class="rounded-2xl border border-slate-200/80 bg-white shadow-sm">
                        <div class="space-y-2 border-b border-slate-100 px-4 py-4 sm:px-5">
                            <h2 class="font-display text-base font-bold text-slate-900 sm:text-lg">Pending Member Applications</h2>
                            <RouterLink to="/admin/approvals" class="inline-flex text-sm font-semibold text-institutional hover:underline">
                                View All Applications →
                            </RouterLink>
                        </div>

                        <div v-if="!dashboard.pending_applications?.length" class="empty-state mx-4 my-6 bg-white sm:mx-5">
                            No applications yet.
                        </div>

                        <template v-else>
                        <div class="divide-y divide-slate-100 md:hidden">
                            <article
                                v-for="application in dashboard.pending_applications"
                                :key="application.uuid"
                                class="space-y-3 px-4 py-4"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="font-medium text-slate-900">{{ application.name }}</p>
                                        <p class="mt-1 text-sm text-slate-600">{{ application.company || 'No company listed' }}</p>
                                    </div>
                                    <span class="badge shrink-0" :class="statusClass(application.status_label)">{{ application.status_label }}</span>
                                </div>
                                <p class="text-xs text-slate-500">Submitted {{ formatDate(application.submitted_at) }}</p>
                                <div class="flex flex-wrap gap-3 text-sm">
                                    <RouterLink to="/admin/approvals" class="font-medium text-institutional hover:underline">View</RouterLink>
                                    <button
                                        v-if="['submitted', 'under_review'].includes(application.status)"
                                        type="button"
                                        class="font-medium text-emerald-700 hover:underline"
                                        @click="approveApplication(application)"
                                    >
                                        Approve
                                    </button>
                                    <button
                                        v-if="['submitted', 'under_review'].includes(application.status)"
                                        type="button"
                                        class="font-medium text-red-600 hover:underline"
                                        @click="rejectApplication(application)"
                                    >
                                        Reject
                                    </button>
                                </div>
                            </article>
                        </div>

                        <div class="hidden overflow-x-auto md:block">
                            <table class="min-w-[640px] text-left text-sm">
                                <thead class="border-b border-slate-100 bg-slate-50/80 text-xs uppercase tracking-wide text-slate-500">
                                    <tr>
                                        <th class="px-5 py-3 font-semibold">Name</th>
                                        <th class="px-5 py-3 font-semibold">Company</th>
                                        <th class="px-5 py-3 font-semibold">Join Date</th>
                                        <th class="px-5 py-3 font-semibold">Status</th>
                                        <th class="px-5 py-3 font-semibold">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr v-for="application in dashboard.pending_applications" :key="application.uuid" class="hover:bg-slate-50/60">
                                        <td class="px-5 py-4 font-medium text-slate-900">{{ application.name }}</td>
                                        <td class="px-5 py-4 text-slate-600">{{ application.company || '—' }}</td>
                                        <td class="px-5 py-4 text-slate-600">{{ formatDate(application.submitted_at) }}</td>
                                        <td class="px-5 py-4">
                                            <span class="badge" :class="statusClass(application.status_label)">{{ application.status_label }}</span>
                                        </td>
                                        <td class="px-5 py-4">
                                            <div class="flex flex-wrap gap-2">
                                                <RouterLink to="/admin/approvals" class="text-institutional hover:underline">View</RouterLink>
                                                <button
                                                    v-if="['submitted', 'under_review'].includes(application.status)"
                                                    type="button"
                                                    class="text-emerald-700 hover:underline"
                                                    @click="approveApplication(application)"
                                                >
                                                    Approve
                                                </button>
                                                <button
                                                    v-if="['submitted', 'under_review'].includes(application.status)"
                                                    type="button"
                                                    class="text-red-600 hover:underline"
                                                    @click="rejectApplication(application)"
                                                >
                                                    Reject
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        </template>
                    </article>
                </div>

                <div class="space-y-6">
                    <article class="rounded-2xl border border-slate-200/80 bg-white shadow-sm">
                        <div class="space-y-3 border-b border-slate-100 px-4 py-4 sm:px-5">
                            <h2 class="font-display text-base font-bold text-slate-900 sm:text-lg">Upcoming Events Queue</h2>
                            <RouterLink to="/admin/events" class="btn-primary inline-flex w-full !rounded-lg !px-3 !py-2.5 text-xs sm:w-auto">
                                <PlusIcon class="mr-1 size-4" />
                                Add Event
                            </RouterLink>
                        </div>
                        <div v-if="!dashboard.upcoming_events?.length" class="empty-state mx-4 my-6 bg-white sm:mx-5">
                            No upcoming events scheduled.
                        </div>
                        <div v-else class="flex flex-col gap-3 p-4">
                            <div
                                v-for="event in dashboard.upcoming_events"
                                :key="event.uuid"
                                class="rounded-xl border p-4"
                                :class="event.status === 'published' ? 'border-brand-100 bg-brand-50/40' : 'border-amber-100 bg-amber-50/40'"
                            >
                                <div class="flex items-start gap-3">
                                    <div class="rounded-lg bg-white px-3 py-2 text-center shadow-sm">
                                        <p class="text-lg font-bold text-institutional">{{ eventDay(event.starts_at) }}</p>
                                        <p class="text-[10px] uppercase text-slate-500">{{ eventMonth(event.starts_at) }}</p>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h3 class="font-semibold text-slate-900">{{ event.title }}</h3>
                                            <span class="badge shrink-0 capitalize" :class="eventStatusClass(event.status)">{{ event.status }}</span>
                                        </div>
                                        <p class="mt-1 text-xs text-slate-500">{{ event.registrants_count }} registrants</p>
                                        <RouterLink to="/admin/event-registrations" class="mt-3 inline-flex text-xs font-semibold text-institutional hover:underline">
                                            Manage
                                        </RouterLink>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </article>

                    <article class="rounded-2xl border border-slate-200/80 bg-white shadow-sm">
                        <div class="border-b border-slate-100 px-4 py-4 sm:px-5">
                            <h2 class="font-display text-base font-bold text-slate-900 sm:text-lg">Publications & Library Uploads</h2>
                        </div>
                        <div v-if="!dashboard.recent_publications?.length" class="empty-state mx-4 my-6 bg-white sm:mx-5">
                            No publications yet.
                        </div>
                        <div v-else class="flex flex-col gap-3 p-4">
                            <div
                                v-for="item in dashboard.recent_publications"
                                :key="item.uuid"
                                class="rounded-xl border border-slate-100 bg-slate-50/50 p-4"
                            >
                                <div class="flex items-start gap-3">
                                    <span class="rounded-lg bg-white px-2 py-1 text-xs font-bold text-institutional shadow-sm">{{ item.type }}</span>
                                    <div class="min-w-0 flex-1">
                                        <p class="font-medium text-slate-900">{{ item.title }}</p>
                                        <p class="mt-1 text-xs text-slate-500">{{ formatDate(item.published_at) }}</p>
                                        <RouterLink
                                            :to="item.kind === 'news' ? `/admin/news` : '/admin/publications'"
                                            class="mt-3 inline-flex text-xs font-semibold text-institutional hover:underline"
                                        >
                                            Review Publication
                                        </RouterLink>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </article>

                    <article class="rounded-2xl border border-slate-200/80 bg-white shadow-sm">
                        <div class="border-b border-slate-100 px-4 py-4 sm:px-5">
                            <h2 class="font-display text-base font-bold text-slate-900 sm:text-lg">Quick Reports & Downloads</h2>
                        </div>
                        <ul class="divide-y divide-slate-100">
                            <li v-for="report in quickReports" :key="report.label" class="px-4 py-4 sm:px-5">
                                <div class="space-y-3">
                                    <div>
                                        <p class="font-medium text-slate-800">{{ report.label }}</p>
                                    </div>
                                    <RouterLink
                                        v-if="report.to"
                                        :to="report.to"
                                        class="btn-primary inline-flex w-full items-center justify-center !rounded-lg !px-3 !py-2.5 text-xs sm:w-auto"
                                    >
                                        View Report
                                    </RouterLink>
                                    <a
                                        v-else
                                        :href="report.href"
                                        class="btn-institutional inline-flex w-full items-center justify-center !rounded-lg !px-3 !py-2.5 text-xs sm:w-auto"
                                    >
                                        <ArrowDownTrayIcon class="mr-1.5 size-4" />
                                        Download CSV
                                    </a>
                                </div>
                            </li>
                        </ul>
                    </article>
                </div>
            </section>
        </template>
    </div>
</template>
