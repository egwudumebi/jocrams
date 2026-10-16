<script setup>
import { ref, onMounted } from 'vue';
import {
    ArrowDownTrayIcon,
    CheckCircleIcon,
    DocumentArrowDownIcon,
    UsersIcon,
    ClipboardDocumentListIcon,
    BanknotesIcon,
} from '@heroicons/vue/24/outline';
import AdminAlert from '../../components/admin/AdminAlert.vue';
import AdminEmptyState from '../../components/admin/AdminEmptyState.vue';
import AdminPageIntro from '../../components/admin/AdminPageIntro.vue';
import { useAuth } from '../../composables/useAuth';
import { downloadAdminExport, messageFromBlobError } from '../../utils/download';

const { getAdminClient } = useAuth();
const overview = ref(null);
const activities = ref([]);
const loading = ref(true);
const exportMessage = ref('');
const exportError = ref('');
const exportingKey = ref('');

const exportCards = [
    {
        key: 'members',
        title: 'Registered members',
        description: 'Full member roster with status, tier, join and expiry dates.',
        path: '/exports/members?format=csv',
        filename: `members-${new Date().toISOString().slice(0, 10)}.csv`,
        icon: UsersIcon,
        hint: 'Use for leadership roll calls and growth reports.',
    },
    {
        key: 'applications',
        title: 'Membership applications',
        description: 'Application pipeline for review boards and audits.',
        path: '/exports/applications?format=csv',
        filename: `applications-${new Date().toISOString().slice(0, 10)}.csv`,
        icon: ClipboardDocumentListIcon,
        hint: 'Includes applicant name, email, tier, and status.',
    },
    {
        key: 'payments',
        title: 'Dues & payments',
        description: 'Payment transactions with references, amounts, and status.',
        path: '/exports/payments?format=csv',
        filename: `payments-${new Date().toISOString().slice(0, 10)}.csv`,
        icon: BanknotesIcon,
        hint: 'Useful for finance reconciliation.',
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

async function downloadExport(card) {
    exportMessage.value = '';
    exportError.value = '';
    exportingKey.value = card.key;

    try {
        await downloadAdminExport(getAdminClient(), card.path, card.filename);
        exportMessage.value = `${card.title} CSV downloaded.`;
    } catch (e) {
        exportError.value = await messageFromBlobError(
            e,
            'Unable to download this report. Please try again.',
        );
    } finally {
        exportingKey.value = '';
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

function humanAction(action) {
    return String(action || '')
        .replace(/\./g, ' · ')
        .replace(/_/g, ' ');
}
</script>

<template>
    <div>
        <AdminPageIntro description="Live operational metrics, recent activity, and secure CSV downloads for leadership review." />

        <AdminAlert v-if="exportMessage" type="success" class="mb-4">{{ exportMessage }}</AdminAlert>
        <AdminAlert v-if="exportError" type="error" class="mb-4">{{ exportError }}</AdminAlert>

        <div v-if="loading" class="rounded-2xl border border-dashed border-slate-200 bg-white px-8 py-14 text-center text-slate-500">
            Loading reports...
        </div>

        <template v-else>
            <div v-if="overview" class="mb-8 admin-stat-grid">
                <div class="admin-stat-card">
                    <p class="text-sm text-slate-500">{{ overview.members_association_label || 'SICAMA' }} members</p>
                    <p class="mt-1 text-3xl font-bold text-slate-900">{{ overview.members_association_active ?? 0 }}</p>
                    <p class="mt-1 text-xs text-slate-500">Paid association memberships</p>
                </div>
                <div class="admin-stat-card">
                    <p class="text-sm text-slate-500">Platform members</p>
                    <p class="mt-1 text-3xl font-bold text-slate-900">{{ overview.members_platform_active ?? 0 }}</p>
                    <p class="mt-1 text-xs text-slate-500">Awaiting dues or non-association IDs</p>
                </div>
                <div class="admin-stat-card">
                    <p class="text-sm text-slate-500">Active members</p>
                    <p class="mt-1 text-3xl font-bold text-slate-900">{{ overview.members_active }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ overview.members_total }} total records</p>
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

            <section class="mb-8">
                <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h2 class="font-display text-lg font-bold text-slate-900">CSV exports</h2>
                        <p class="mt-1 text-sm text-slate-500">
                            Downloads stay in this page — signed in securely, no blank error screens.
                        </p>
                    </div>
                    <p class="inline-flex items-center gap-1.5 text-xs font-medium text-slate-500">
                        <DocumentArrowDownIcon class="size-4" />
                        Excel-ready CSV
                    </p>
                </div>

                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <article
                        v-for="card in exportCards"
                        :key="card.key"
                        class="flex flex-col rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm"
                    >
                        <div class="flex items-start gap-3">
                            <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-institutional">
                                <component :is="card.icon" class="size-6" />
                            </span>
                            <div class="min-w-0">
                                <h3 class="font-display text-base font-bold text-slate-900">{{ card.title }}</h3>
                                <p class="mt-1 text-sm leading-relaxed text-slate-500">{{ card.description }}</p>
                            </div>
                        </div>
                        <p class="mt-4 text-xs leading-relaxed text-slate-400">{{ card.hint }}</p>
                        <button
                            type="button"
                            class="btn-institutional mt-5 inline-flex w-full items-center justify-center gap-2 !rounded-xl !px-4 !py-2.5 text-sm disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="exportingKey === card.key"
                            @click="downloadExport(card)"
                        >
                            <ArrowDownTrayIcon v-if="exportingKey !== card.key" class="size-4" />
                            <span
                                v-else
                                class="size-4 animate-spin rounded-full border-2 border-white/30 border-t-white"
                                aria-hidden="true"
                            />
                            {{ exportingKey === card.key ? 'Preparing download…' : 'Download CSV' }}
                        </button>
                    </article>
                </div>
            </section>

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
                                <p class="font-medium capitalize text-slate-900">{{ humanAction(activity.action) }}</p>
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

            <p class="flex items-center gap-2 text-xs text-slate-400">
                <CheckCircleIcon class="size-4 text-emerald-500" />
                Exports use your admin session. If a download fails, the error stays on this page.
            </p>
        </template>
    </div>
</template>
