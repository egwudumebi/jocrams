<script setup>
import { ref, onMounted } from 'vue';
import { ArrowDownTrayIcon, ChartBarIcon } from '@heroicons/vue/24/outline';
import AdminAlert from '../../components/admin/AdminAlert.vue';
import AdminEmptyState from '../../components/admin/AdminEmptyState.vue';
import AdminPageIntro from '../../components/admin/AdminPageIntro.vue';
import { useAuth } from '../../composables/useAuth';

const { getAdminClient } = useAuth();
const overview = ref(null);
const activities = ref([]);
const loading = ref(true);

const exports = [
    {
        title: 'Monthly Member Growth',
        description: 'Export member roster with status, tier, and join dates.',
        href: '/api/v1/admin/exports/members?format=csv',
    },
    {
        title: 'Membership Applications',
        description: 'Download application pipeline for review and reporting.',
        href: '/api/v1/admin/exports/applications?format=csv',
    },
    {
        title: 'Dues Collection Status',
        description: 'Payment transactions with references, amounts, and status.',
        href: '/api/v1/admin/exports/payments?format=csv',
    },
];

onMounted(load);

async function load() {
    loading.value = true;
    try {
        const [overviewResponse, activitiesResponse] = await Promise.all([
            getAdminClient().get('/reports/overview'),
            getAdminClient().get('/reports/recent-activities', { params: { limit: 20 } }),
        ]);
        overview.value = overviewResponse.data.data;
        activities.value = activitiesResponse.data.data || [];
    } finally {
        loading.value = false;
    }
}

function formatCurrency(value) {
    return new Intl.NumberFormat('en-NG', {
        style: 'currency',
        currency: 'NGN',
        maximumFractionDigits: 0,
    }).format(value || 0);
}

function formatDate(value) {
    return value ? new Date(value).toLocaleString() : '—';
}
</script>

<template>
    <div>
        <AdminPageIntro description="Live operational metrics, recent activity, and CSV exports for leadership review." />

        <div v-if="loading" class="rounded-2xl border border-dashed border-slate-200 bg-white px-8 py-14 text-center text-slate-500">
            Loading reports...
        </div>

        <template v-else>
            <div v-if="overview" class="mb-8 admin-stat-grid">
                <div class="admin-stat-card">
                    <p class="text-sm text-slate-500">Active members</p>
                    <p class="mt-1 text-3xl font-bold text-slate-900">{{ overview.members_active }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ overview.members_total }} total</p>
                </div>
                <div class="admin-stat-card">
                    <p class="text-sm text-slate-500">Pending applications</p>
                    <p class="mt-1 text-3xl font-bold text-slate-900">{{ overview.applications_pending }}</p>
                </div>
                <div class="admin-stat-card">
                    <p class="text-sm text-slate-500">Journal queue</p>
                    <p class="mt-1 text-3xl font-bold text-slate-900">{{ overview.journal_submissions_pending }}</p>
                </div>
                <div class="admin-stat-card">
                    <p class="text-sm text-slate-500">Successful payments</p>
                    <p class="mt-1 text-2xl font-bold text-slate-900">{{ formatCurrency(overview.payments_total) }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ overview.events_total }} events · {{ overview.support_open }} open support</p>
                </div>
            </div>

            <section v-if="overview?.applications_growth_trend_6_months?.length" class="mb-8 admin-panel">
                <div class="admin-panel-header !border-b">
                    <h2 class="admin-panel-title">Application trend (6 months)</h2>
                </div>
                <div class="admin-panel-body grid gap-3 sm:grid-cols-3 lg:grid-cols-6">
                    <div
                        v-for="point in overview.applications_growth_trend_6_months"
                        :key="point.month"
                        class="rounded-xl bg-slate-50 p-4 text-center"
                    >
                        <p class="text-xs uppercase tracking-wide text-slate-500">{{ point.month }}</p>
                        <p class="mt-1 text-2xl font-bold text-slate-900">{{ point.applications }}</p>
                    </div>
                </div>
            </section>

            <section class="mb-8 admin-panel">
                <div class="admin-panel-header !border-b">
                    <h2 class="admin-panel-title">Recent activity</h2>
                </div>
                <AdminEmptyState v-if="!activities.length" title="No recent activity logged" description="Admin actions and system events will appear here." />
                <ul v-else class="divide-y divide-slate-100">
                    <li v-for="activity in activities" :key="activity.id" class="px-5 py-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="font-medium text-slate-900">{{ activity.action }}</p>
                                <p class="mt-1 text-sm text-slate-500">
                                    {{ activity.actor_name || 'System' }}
                                    <span v-if="activity.actor_email"> · {{ activity.actor_email }}</span>
                                </p>
                            </div>
                            <p class="text-xs text-slate-400">{{ formatDate(activity.occurred_at) }}</p>
                        </div>
                    </li>
                </ul>
            </section>
        </template>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <article
                v-for="report in exports"
                :key="report.title"
                class="admin-stat-card flex flex-col"
            >
                <div class="flex size-11 items-center justify-center rounded-xl bg-brand-50 text-institutional">
                    <ChartBarIcon class="size-6" />
                </div>
                <h2 class="mt-4 font-display text-base font-bold text-slate-900 sm:text-lg">{{ report.title }}</h2>
                <p class="mt-2 flex-1 text-sm leading-relaxed text-text-secondary">{{ report.description }}</p>
                <a :href="report.href" class="btn-institutional mt-5 inline-flex w-full items-center justify-center !rounded-xl !px-4 !py-2.5 text-sm">
                    <ArrowDownTrayIcon class="mr-1.5 size-4" />
                    Download CSV
                </a>
            </article>
        </div>
    </div>
</template>
