<script setup>
import { computed, ref, onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import {
    ArrowRightIcon,
    CheckCircleIcon,
    ClockIcon,
    DocumentTextIcon,
    MagnifyingGlassIcon,
    NewspaperIcon,
    PencilSquareIcon,
    PlusIcon,
} from '@heroicons/vue/24/outline';
import AdminAlert from '../../../components/admin/AdminAlert.vue';
import AdminEmptyState from '../../../components/admin/AdminEmptyState.vue';
import AdminPageIntro from '../../../components/admin/AdminPageIntro.vue';
import AdminPanel from '../../../components/admin/AdminPanel.vue';
import JournalStatusBadge from '../../../components/journal/JournalStatusBadge.vue';
import { useAuth } from '../../../composables/useAuth';
import { extractApiError } from '../../../utils/apiError';
import { formatJournalDate } from '../../../utils/journal';

const { getJournalClient, fetchMemberProfile, canJournalSubmit, canJournalReview } = useAuth();

const submissions = ref([]);
const openCalls = ref([]);
const loading = ref(true);
const loadingCalls = ref(true);
const search = ref('');
const error = ref('');

const hasOpenCalls = computed(() => openCalls.value.length > 0);

const filteredSubmissions = computed(() => {
    const query = search.value.trim().toLowerCase();

    if (!query) {
        return submissions.value;
    }

    return submissions.value.filter((item) =>
        item.title.toLowerCase().includes(query)
        || (item.category ?? '').toLowerCase().includes(query)
        || (item.review_comment ?? '').toLowerCase().includes(query),
    );
});

const inReviewCount = computed(() =>
    submissions.value.filter((item) => ['under_review', 'resubmitted', 'submitted'].includes(item.status)).length,
);
const approvedCount = computed(() => submissions.value.filter((item) => item.status === 'approved').length);
const actionRequiredCount = computed(() =>
    submissions.value.filter((item) => item.status === 'revision_requested').length,
);

onMounted(async () => {
    await fetchMemberProfile();
    await Promise.all([load(), loadOpenCalls()]);
});

async function loadOpenCalls() {
    loadingCalls.value = true;

    if (!canJournalSubmit()) {
        openCalls.value = [];
        loadingCalls.value = false;
        return;
    }

    try {
        const { data } = await getJournalClient().get('/calls/open');
        openCalls.value = data.data || [];
    } catch {
        openCalls.value = [];
    } finally {
        loadingCalls.value = false;
    }
}

async function load() {
    loading.value = true;
    error.value = '';

    if (!canJournalSubmit()) {
        submissions.value = [];
        loading.value = false;
        return;
    }

    try {
        const { data } = await getJournalClient().get('/submissions/my');
        submissions.value = data.data || [];
    } catch (e) {
        submissions.value = [];
        if (e.response?.status !== 403) {
            error.value = extractApiError(e, 'Unable to load submissions.');
        }
    } finally {
        loading.value = false;
    }
}

function statusHint(status) {
    const map = {
        submitted: 'Awaiting editorial assignment',
        under_review: 'Currently being reviewed',
        revision_requested: 'Editor requested changes',
        resubmitted: 'Updated manuscript under review',
        approved: 'Accepted for publication',
        rejected: 'Not accepted for publication',
    };

    return map[status] || '';
}
</script>

<template>
    <div>
        <AdminPageIntro description="Submit manuscripts, track editorial decisions, and manage your publication pipeline.">
            <template #actions>
                <div class="flex flex-wrap items-center gap-2">
                    <div class="relative w-full sm:w-64">
                        <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                        <input
                            v-model="search"
                            type="search"
                            placeholder="Search submissions…"
                            class="input !py-2.5 !pl-10"
                            :disabled="!canJournalSubmit() || submissions.length === 0"
                        />
                    </div>
                    <RouterLink
                        v-if="canJournalReview()"
                        to="/member/journal/review"
                        class="btn-secondary inline-flex items-center gap-2"
                    >
                        <PencilSquareIcon class="size-4" />
                        Reviewer queue
                    </RouterLink>
                    <RouterLink
                        v-if="canJournalSubmit() && hasOpenCalls"
                        to="/member/journal/submit"
                        class="btn-institutional inline-flex items-center gap-2"
                    >
                        <PlusIcon class="size-4" />
                        Submit manuscript
                    </RouterLink>
                </div>
            </template>
        </AdminPageIntro>

        <AdminAlert v-if="error" type="error">{{ error }}</AdminAlert>

        <div
            v-if="!loading && !loadingCalls && canJournalSubmit() && !hasOpenCalls"
            class="mt-6 rounded-2xl border border-slate-200 bg-slate-50/80 px-5 py-4 text-sm text-slate-600"
        >
            Manuscript submissions are closed. The journal team will open a call for papers when submissions are accepted.
        </div>

        <div
            v-if="!loading && !canJournalSubmit()"
            class="mt-6 rounded-2xl border border-amber-200 bg-gradient-to-br from-amber-50 via-white to-white p-6"
        >
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="font-semibold text-amber-900">Active membership required</p>
                    <p class="mt-1 text-sm text-amber-800">
                        Complete your membership application before submitting manuscripts to the journal.
                    </p>
                </div>
                <RouterLink to="/member/applications" class="btn-institutional shrink-0">Go to applications</RouterLink>
            </div>
        </div>

        <template v-else>
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="card-modern p-5">
                    <div class="flex items-center gap-4">
                        <span class="metric-icon !size-12 !rounded-xl">
                            <DocumentTextIcon class="size-6" />
                        </span>
                        <div>
                            <p class="text-sm text-slate-500">Total submissions</p>
                            <p class="text-2xl font-bold text-slate-900">{{ loading ? '—' : submissions.length }}</p>
                        </div>
                    </div>
                </div>
                <div class="card-modern p-5">
                    <div class="flex items-center gap-4">
                        <span class="metric-icon !size-12 !rounded-xl">
                            <ClockIcon class="size-6" />
                        </span>
                        <div>
                            <p class="text-sm text-slate-500">In progress</p>
                            <p class="text-2xl font-bold text-slate-900">{{ loading ? '—' : inReviewCount }}</p>
                        </div>
                    </div>
                </div>
                <div class="card-modern p-5">
                    <div class="flex items-center gap-4">
                        <span class="metric-icon !size-12 !rounded-xl">
                            <PencilSquareIcon class="size-6" />
                        </span>
                        <div>
                            <p class="text-sm text-slate-500">Action required</p>
                            <p class="text-2xl font-bold text-slate-900">{{ loading ? '—' : actionRequiredCount }}</p>
                        </div>
                    </div>
                </div>
                <div class="card-modern p-5">
                    <div class="flex items-center gap-4">
                        <span class="metric-icon !size-12 !rounded-xl">
                            <CheckCircleIcon class="size-6" />
                        </span>
                        <div>
                            <p class="text-sm text-slate-500">Approved</p>
                            <p class="text-2xl font-bold text-slate-900">{{ loading ? '—' : approvedCount }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <AdminPanel
                class="mt-6"
                title="Your submissions"
                description="Track manuscript status, editor notes, and next steps for each submission."
            >
                <div v-if="loading" class="flex justify-center py-16">
                    <div class="size-10 animate-spin rounded-full border-2 border-institutional/20 border-t-institutional" />
                </div>

                <AdminEmptyState
                    v-else-if="submissions.length === 0"
                    :title="'No submissions yet'"
                    :description="hasOpenCalls
                        ? 'When you submit a manuscript, it will appear here with status updates as it moves through editorial review.'
                        : 'Submissions will be available when the journal publishes an open call for papers.'"
                >
                    <template #icon>
                        <DocumentTextIcon class="size-6" />
                    </template>
                    <template v-if="hasOpenCalls" #action>
                        <RouterLink to="/member/journal/submit" class="btn-institutional inline-flex items-center gap-2">
                            <PlusIcon class="size-4" />
                            Submit your first manuscript
                        </RouterLink>
                    </template>
                </AdminEmptyState>

                <AdminEmptyState
                    v-else-if="filteredSubmissions.length === 0"
                    title="No matching submissions"
                    description="Try a different search term or clear the filter to see all manuscripts."
                >
                    <template #icon>
                        <MagnifyingGlassIcon class="size-6" />
                    </template>
                    <template #action>
                        <button type="button" class="btn-secondary" @click="search = ''">Clear search</button>
                    </template>
                </AdminEmptyState>

                <div v-else class="space-y-3">
                    <RouterLink
                        v-for="item in filteredSubmissions"
                        :key="item.id"
                        :to="`/member/journal/${item.slug || item.id}`"
                        class="group block overflow-hidden rounded-2xl border border-slate-100 bg-white transition hover:border-institutional/20 hover:shadow-sm"
                    >
                        <div class="flex flex-col gap-4 p-5 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span v-if="item.category" class="text-xs font-semibold uppercase tracking-wide text-institutional">
                                        {{ item.category }}
                                    </span>
                                </div>
                                <h3 class="mt-1 text-lg font-semibold text-slate-900 group-hover:text-institutional">
                                    {{ item.title }}
                                </h3>
                                <p class="mt-1 text-sm text-slate-500">
                                    Submitted {{ formatJournalDate(item.date_submitted) }}
                                    <span v-if="statusHint(item.status)"> · {{ statusHint(item.status) }}</span>
                                </p>
                                <p v-if="item.review_comment" class="mt-3 rounded-xl bg-surface-muted/60 px-3 py-2 text-sm text-slate-700">
                                    <span class="font-medium text-slate-900">Latest note:</span> {{ item.review_comment }}
                                </p>
                            </div>
                            <div class="flex shrink-0 items-center gap-3 self-start sm:flex-col sm:items-end">
                                <JournalStatusBadge :status="item.status" />
                                <span class="inline-flex items-center gap-1 text-sm font-semibold text-institutional opacity-0 transition group-hover:opacity-100">
                                    View details
                                    <ArrowRightIcon class="size-4" />
                                </span>
                            </div>
                        </div>
                    </RouterLink>
                </div>
            </AdminPanel>

            <div class="mt-6 rounded-2xl border border-slate-200 bg-gradient-to-br from-surface-muted via-white to-white p-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-start gap-3">
                        <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-institutional/10 text-institutional">
                            <NewspaperIcon class="size-5" />
                        </span>
                        <div>
                            <p class="font-semibold text-slate-900">Browse published articles</p>
                            <p class="mt-1 text-sm text-slate-600">
                                Read articles already published in the Jocrams journal on the public site.
                            </p>
                        </div>
                    </div>
                    <RouterLink to="/journal" class="btn-secondary shrink-0">Open public journal</RouterLink>
                </div>
            </div>
        </template>
    </div>
</template>
