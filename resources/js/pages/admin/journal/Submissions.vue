<script setup>
import { computed, ref, onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import {
    CheckBadgeIcon,
    DocumentTextIcon,
    EnvelopeOpenIcon,
    MagnifyingGlassIcon,
} from '@heroicons/vue/24/outline';
import AdminEmptyState from '../../../components/admin/AdminEmptyState.vue';
import AdminPageIntro from '../../../components/admin/AdminPageIntro.vue';
import AdminPanel from '../../../components/admin/AdminPanel.vue';
import JournalStatusBadge from '../../../components/journal/JournalStatusBadge.vue';
import { formatJournalDate } from '../../../utils/journal';
import { extractApiError } from '../../../utils/apiError';
import { confirmAction, notifyError, notifySuccess } from '../../../utils/swal';
import { useAuth } from '../../../composables/useAuth';

const { getJournalClient, fetchAdminProfile, canJournalAssign } = useAuth();
const submissions = ref([]);
const loading = ref(true);
const acknowledgingId = ref('');
const message = ref('');
const error = ref('');
const filter = ref({
    status: '',
    search: '',
    receipt: '',
});

const statusOptions = [
    { value: '', label: 'All statuses' },
    { value: 'payment_pending', label: 'Payment pending' },
    { value: 'submitted', label: 'Submitted' },
    { value: 'under_review', label: 'Under review' },
    { value: 'revision_requested', label: 'Revision requested' },
    { value: 'resubmitted', label: 'Resubmitted' },
    { value: 'approved', label: 'Approved' },
    { value: 'rejected', label: 'Rejected' },
];

const stats = computed(() => {
    const items = submissions.value;
    return {
        total: items.length,
        payment_pending: items.filter((item) => item.status === 'payment_pending').length,
        submitted: items.filter((item) => item.status === 'submitted').length,
        under_review: items.filter((item) => ['under_review', 'revision_requested', 'resubmitted'].includes(item.status)).length,
        awaiting_ack: items.filter((item) => item.can_acknowledge_receipt).length,
    };
});

const filtered = computed(() => {
    const q = filter.value.search.trim().toLowerCase();
    return submissions.value.filter((item) => {
        if (filter.value.status && item.status !== filter.value.status) {
            return false;
        }
        if (filter.value.receipt === 'pending' && !item.can_acknowledge_receipt) {
            return false;
        }
        if (filter.value.receipt === 'acknowledged' && !item.receipt_acknowledged) {
            return false;
        }
        if (!q) {
            return true;
        }
        const haystack = [item.title, item.author_name, item.author_email, item.category, item.reviewer_name]
            .filter(Boolean)
            .join(' ')
            .toLowerCase();
        return haystack.includes(q);
    });
});

onMounted(async () => {
    loading.value = true;
    await fetchAdminProfile();
    await load();
});

async function load() {
    loading.value = true;
    error.value = '';
    try {
        const { data } = await getJournalClient().get('/submissions/review');
        submissions.value = data.data || [];
    } catch (e) {
        error.value = extractApiError(e, 'Unable to load submissions.');
    } finally {
        loading.value = false;
    }
}

async function acknowledgeReceipt(item) {
    const confirmed = await confirmAction({
        title: 'Acknowledge receipt?',
        text: `Email ${item.author_name || 'the author'} confirming that JOCRAMS has received this manuscript.`,
        confirmText: 'Acknowledge & email',
    });
    if (!confirmed) {
        return;
    }

    acknowledgingId.value = item.id;
    message.value = '';
    error.value = '';
    try {
        await getJournalClient().post(`/submissions/${item.id}/acknowledge-receipt`);
        notifySuccess('Receipt acknowledged. Author emailed.');
        message.value = 'Receipt acknowledged. The author has been emailed.';
        await load();
    } catch (e) {
        const msg = extractApiError(e, 'Unable to acknowledge receipt.');
        error.value = msg;
        notifyError(msg);
    } finally {
        acknowledgingId.value = '';
    }
}

function titleCase(value) {
    return String(value || '')
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (c) => c.toUpperCase());
}
</script>

<template>
    <div>
        <AdminPageIntro description="Manage editorial workflow, reviewers, and publication visibility.">
            <template #actions>
                <RouterLink v-if="canJournalAssign()" to="/admin/journal/volumes-issues" class="btn-secondary w-full !rounded-xl text-center sm:w-auto">
                    Volumes & issues
                </RouterLink>
                <RouterLink v-if="canJournalAssign()" to="/admin/journal/calls-for-papers" class="btn-secondary w-full !rounded-xl text-center sm:w-auto">
                    Calls for papers
                </RouterLink>
                <RouterLink v-if="canJournalAssign()" to="/admin/journal/editorial-board" class="btn-secondary w-full !rounded-xl text-center sm:w-auto">
                    Editorial board
                </RouterLink>
                <RouterLink v-if="canJournalAssign()" to="/admin/journal/categories" class="btn-secondary w-full !rounded-xl text-center sm:w-auto">
                    Categories
                </RouterLink>
                <RouterLink v-if="canJournalAssign()" to="/admin/journal/reviewers" class="btn-secondary w-full !rounded-xl text-center sm:w-auto">
                    Peer reviewers
                </RouterLink>
            </template>
        </AdminPageIntro>

        <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Total</p>
                <p class="mt-1 text-2xl font-semibold text-slate-900">{{ stats.total }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Payment pending</p>
                <p class="mt-1 text-2xl font-semibold text-orange-700">{{ stats.payment_pending }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Ready / submitted</p>
                <p class="mt-1 text-2xl font-semibold text-amber-700">{{ stats.submitted }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Awaiting acknowledgement</p>
                <p class="mt-1 text-2xl font-semibold text-institutional">{{ stats.awaiting_ack }}</p>
            </div>
        </div>

        <p v-if="message" class="mt-4 text-sm text-green-700">{{ message }}</p>
        <p v-if="error" class="mt-4 text-sm text-red-600">{{ error }}</p>

        <AdminPanel
            class="mt-6"
            title="Submissions"
            description="Filter by status, search authors, and acknowledge receipt once payment is confirmed."
        >
            <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex flex-wrap gap-2">
                    <select v-model="filter.status" class="input max-w-[180px]">
                        <option v-for="option in statusOptions" :key="option.value || 'all'" :value="option.value">
                            {{ option.label }}
                        </option>
                    </select>
                    <select v-if="canJournalAssign()" v-model="filter.receipt" class="input max-w-[200px]">
                        <option value="">All receipt states</option>
                        <option value="pending">Awaiting acknowledgement</option>
                        <option value="acknowledged">Receipt acknowledged</option>
                    </select>
                    <div class="relative min-w-[220px] flex-1">
                        <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                        <input
                            v-model="filter.search"
                            type="search"
                            placeholder="Search title, author, email…"
                            class="input w-full pl-9"
                        />
                    </div>
                </div>
                <p class="text-sm text-slate-500">
                    Showing {{ filtered.length }} of {{ submissions.length }}
                </p>
            </div>

            <div v-if="loading" class="empty-state bg-white">Loading submissions...</div>

            <AdminEmptyState
                v-else-if="!filtered.length"
                title="No matching submissions"
                description="Try clearing filters, or wait for new manuscripts from authors."
            >
                <template #icon><DocumentTextIcon class="size-6" /></template>
            </AdminEmptyState>

            <template v-else>
                <div class="divide-y divide-slate-100 md:hidden">
                    <article v-for="item in filtered" :key="item.id" class="space-y-3 py-4 first:pt-0 last:pb-0">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-medium leading-snug text-slate-900">{{ titleCase(item.title) }}</p>
                                <p class="mt-1 text-sm text-slate-600">{{ item.author_name }}</p>
                                <p v-if="item.author_email" class="text-xs text-slate-500">{{ item.author_email }}</p>
                            </div>
                            <JournalStatusBadge :status="item.status" />
                        </div>
                        <div class="flex flex-wrap gap-2 text-xs text-slate-500">
                            <span v-if="item.category" class="rounded-lg bg-slate-100 px-2 py-1">{{ item.category }}</span>
                            <span>Submitted {{ formatJournalDate(item.date_submitted) }}</span>
                            <span
                                v-if="item.receipt_acknowledged"
                                class="inline-flex items-center gap-1 rounded-lg bg-emerald-50 px-2 py-1 font-medium text-emerald-700"
                            >
                                <CheckBadgeIcon class="size-3.5" />
                                Receipt acknowledged
                            </span>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-if="item.can_acknowledge_receipt"
                                type="button"
                                class="btn-primary inline-flex items-center gap-1.5 !px-3 !py-2 text-sm"
                                :disabled="acknowledgingId === item.id"
                                @click="acknowledgeReceipt(item)"
                            >
                                <EnvelopeOpenIcon class="size-4" />
                                {{ acknowledgingId === item.id ? 'Sending…' : 'Acknowledge receipt' }}
                            </button>
                            <RouterLink
                                :to="`/admin/journal/${item.slug || item.id}`"
                                class="btn-secondary inline-flex items-center !px-3 !py-2 text-sm"
                            >
                                Manage
                            </RouterLink>
                        </div>
                    </article>
                </div>

                <div class="hidden overflow-x-auto md:block">
                    <table class="min-w-[880px] w-full text-sm">
                        <thead class="border-b border-slate-100 bg-slate-50/80 text-left text-slate-500">
                            <tr>
                                <th class="px-4 py-3 font-medium">Manuscript</th>
                                <th class="px-4 py-3 font-medium">Author</th>
                                <th class="px-4 py-3 font-medium">Category</th>
                                <th class="px-4 py-3 font-medium">Status</th>
                                <th class="px-4 py-3 font-medium">Receipt</th>
                                <th class="px-4 py-3 font-medium">Submitted</th>
                                <th class="px-4 py-3 font-medium text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <tr
                                v-for="item in filtered"
                                :key="item.id"
                                class="align-top transition-colors hover:bg-slate-50/70"
                            >
                                <td class="max-w-xs px-4 py-4">
                                    <p class="font-medium leading-snug text-slate-900">{{ titleCase(item.title) }}</p>
                                    <p v-if="item.reviewer_name" class="mt-1 text-xs text-slate-500">
                                        Reviewer: {{ item.reviewer_name }}
                                    </p>
                                </td>
                                <td class="px-4 py-4">
                                    <p class="text-slate-800">{{ item.author_name }}</p>
                                    <p class="mt-0.5 text-xs text-slate-500">{{ item.author_email }}</p>
                                </td>
                                <td class="px-4 py-4 text-slate-600">{{ item.category || '—' }}</td>
                                <td class="px-4 py-4"><JournalStatusBadge :status="item.status" /></td>
                                <td class="px-4 py-4">
                                    <span
                                        v-if="item.receipt_acknowledged"
                                        class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700"
                                    >
                                        <CheckBadgeIcon class="size-3.5" />
                                        Acknowledged
                                    </span>
                                    <span
                                        v-else-if="item.status === 'payment_pending'"
                                        class="text-xs text-slate-400"
                                    >
                                        After payment
                                    </span>
                                    <span v-else class="text-xs font-medium text-amber-700">Awaiting</span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-4 text-slate-600">
                                    {{ formatJournalDate(item.date_submitted) }}
                                </td>
                                <td class="px-4 py-4">
                                    <div class="flex flex-col items-end gap-2">
                                        <button
                                            v-if="item.can_acknowledge_receipt"
                                            type="button"
                                            class="btn-primary inline-flex items-center gap-1.5 !px-3 !py-1.5 text-xs"
                                            :disabled="acknowledgingId === item.id"
                                            @click="acknowledgeReceipt(item)"
                                        >
                                            <EnvelopeOpenIcon class="size-3.5" />
                                            {{ acknowledgingId === item.id ? 'Sending…' : 'Acknowledge' }}
                                        </button>
                                        <RouterLink
                                            :to="`/admin/journal/${item.slug || item.id}`"
                                            class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:border-slate-300 hover:bg-slate-50"
                                        >
                                            Manage
                                        </RouterLink>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>
        </AdminPanel>
    </div>
</template>
