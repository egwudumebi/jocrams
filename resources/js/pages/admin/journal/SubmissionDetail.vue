<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRoute, RouterLink } from 'vue-router';
import {
    ArrowDownTrayIcon,
    CalendarDaysIcon,
    ChatBubbleLeftRightIcon,
    CheckCircleIcon,
    ClockIcon,
    EnvelopeOpenIcon,
    EyeIcon,
    EyeSlashIcon,
    FlagIcon,
    MagnifyingGlassIcon,
    UserPlusIcon,
    XCircleIcon,
} from '@heroicons/vue/24/outline';
import AdminAlert from '../../../components/admin/AdminAlert.vue';
import AdminPanel from '../../../components/admin/AdminPanel.vue';
import JournalStatusBadge from '../../../components/journal/JournalStatusBadge.vue';
import { useAuth } from '../../../composables/useAuth';
import { useSiteBranding } from '../../../composables/useSiteBranding';
import { formatJournalDate, downloadJournalDocument } from '../../../utils/journal';

const route = useRoute();
const { getJournalClient, adminToken, fetchAdminProfile, canJournalAssign, canJournalPublish } = useAuth();
const { branding, loadBranding } = useSiteBranding();

const submission = ref(null);
const reviewers = ref([]);
const timeline = ref([]);
const comments = ref([]);
const assignForm = ref({ reviewer_id: '', priority: 'normal', due_at: '' });
const reviewForm = ref({ decision: 'accept', review_comment: '', rejection_reason: '' });
const visibility = ref('all');
const productionFile = ref(null);
const acknowledging = ref(false);
const assigning = ref(false);
const deciding = ref(false);
const message = ref('');
const error = ref('');
const loading = ref(true);
const reviewerSearch = ref('');

const submissionKey = computed(() => route.params.id);
const isApproved = computed(() => submission.value?.status === 'approved');
const canAcknowledge = computed(() => Boolean(submission.value?.can_acknowledge_receipt));
const siteName = computed(() => branding.value?.site_name || 'JOCRAMS');
const supportsAcknowledge = computed(() => (
    submission.value != null
    && Object.prototype.hasOwnProperty.call(submission.value, 'can_acknowledge_receipt')
));

const filteredReviewers = computed(() => {
    const q = reviewerSearch.value.trim().toLowerCase();
    if (!q) {
        return reviewers.value;
    }
    return reviewers.value.filter((reviewer) => {
        const haystack = `${reviewer.name} ${reviewer.email}`.toLowerCase();
        return haystack.includes(q);
    });
});

const selectedReviewer = computed(() => reviewers.value.find((r) => r.id === assignForm.value.reviewer_id) || null);

const priorityHints = {
    low: 'Shown to the reviewer as lower urgency. Does not change who can review.',
    normal: 'Standard review pace. Shown in the reviewer’s queue.',
    high: 'Marked urgent for the reviewer. Does not auto-escalate or send extra emails.',
};

const priorityHint = computed(() => priorityHints[assignForm.value.priority] || priorityHints.normal);

const decisionHint = computed(() => (
    reviewForm.value.decision === 'reject'
        ? 'Rejects the manuscript, emails the author, and closes this review.'
        : 'Accepts the manuscript for publication and emails the author.'
));

const visibilityHint = computed(() => (
    visibility.value === 'members_only'
        ? 'Only logged-in active members can read the published article. Admins and the author can always access it.'
        : 'Anyone on the public site can read the published article once it is accepted.'
));

const keywordList = computed(() => {
    const raw = submission.value?.keywords;
    if (!raw) {
        return [];
    }
    return String(raw)
        .split(/[,;]+/)
        .map((item) => item.trim())
        .filter(Boolean);
});

const timelineLabels = {
    'submission.created': 'Manuscript submitted',
    'submission.payment_received': 'Submission fee received',
    'submission.receipt_acknowledged': 'Receipt acknowledged',
    'submission.reviewer_assigned': 'Reviewer assigned',
    'submission.under_review': 'Moved to under review',
    'submission.approved': 'Accepted for publication',
    'submission.rejected': 'Rejected',
    'submission.revision_requested': 'Revision requested',
    'submission.resubmitted': 'Revision resubmitted',
    'submission.reviewer_assignment_accepted': 'Reviewer accepted assignment',
    'submission.reviewer_assignment_declined': 'Reviewer declined assignment',
    'submission.production_document_uploaded': 'Production file uploaded',
};

onMounted(async () => {
    await Promise.all([loadBranding(), load()]);
});

async function load() {
    loading.value = true;
    error.value = '';
    await fetchAdminProfile();

    try {
        const [listRes, allRes, reviewersRes] = await Promise.all([
            getJournalClient().get('/submissions/review'),
            getJournalClient().get('/submissions'),
            canJournalAssign() ? getJournalClient().get('/admin/reviewers') : Promise.resolve({ data: { data: [] } }),
        ]);

        submission.value = (listRes.data.data || []).find((item) => item.id === submissionKey.value || item.slug === submissionKey.value)
            || (allRes.data.data || []).find((item) => item.id === submissionKey.value || item.slug === submissionKey.value)
            || null;
        reviewers.value = reviewersRes.data.data || [];
        visibility.value = submission.value?.visibility || 'all';

        if (submission.value) {
            const [timelineRes, commentsRes] = await Promise.all([
                getJournalClient().get(`/submissions/${submission.value.id}/timeline`),
                getJournalClient().get(`/submissions/${submission.value.id}/comments`),
            ]);
            timeline.value = commentsTimeline(timelineRes.data.data || []);
            comments.value = commentsRes.data.data || [];
        }
    } catch (e) {
        error.value = e.response?.data?.message || 'Unable to load submission.';
    } finally {
        loading.value = false;
    }
}

function commentsTimeline(events) {
    return [...events].sort((a, b) => new Date(a.occurred_at || 0) - new Date(b.occurred_at || 0));
}

function titleCase(value) {
    return String(value || '')
        .toLowerCase()
        .replace(/\b\w/g, (c) => c.toUpperCase());
}

function timelineLabel(eventKey) {
    return timelineLabels[eventKey] || String(eventKey || '').replace(/^submission\./, '').replace(/_/g, ' ');
}

async function downloadDocument(variant = 'manuscript') {
    error.value = '';
    try {
        await downloadJournalDocument(submission.value.document_url, adminToken.value, variant);
    } catch (e) {
        error.value = e?.message || 'Unable to download document.';
    }
}

async function assignReviewer() {
    error.value = '';
    assigning.value = true;
    try {
        await getJournalClient().post(`/submissions/${submission.value.id}/assign`, assignForm.value);
        message.value = selectedReviewer.value
            ? `Assigned to ${selectedReviewer.value.name}.`
            : 'Reviewer assigned.';
        assignForm.value.reviewer_id = '';
        reviewerSearch.value = '';
        await load();
    } catch (e) {
        error.value = e.response?.data?.message || 'Unable to assign reviewer.';
    } finally {
        assigning.value = false;
    }
}

async function submitReview() {
    error.value = '';
    deciding.value = true;
    try {
        await getJournalClient().post(`/submissions/${submission.value.id}/review`, reviewForm.value);
        message.value = 'Review decision recorded.';
        await load();
    } catch (e) {
        error.value = e.response?.data?.message || 'Unable to submit review.';
    } finally {
        deciding.value = false;
    }
}

async function updateVisibility() {
    error.value = '';
    try {
        await getJournalClient().put(`/submissions/${submission.value.id}/visibility`, { visibility: visibility.value });
        message.value = 'Visibility updated.';
        await load();
    } catch (e) {
        error.value = e.response?.data?.message || 'Unable to update visibility.';
    }
}

async function acknowledgeReceipt() {
    acknowledging.value = true;
    error.value = '';
    try {
        await getJournalClient().post(`/submissions/${submission.value.id}/acknowledge-receipt`);
        message.value = 'Receipt acknowledged. The author has been emailed.';
        await load();
    } catch (e) {
        error.value = e.response?.data?.message || 'Unable to acknowledge receipt.';
    } finally {
        acknowledging.value = false;
    }
}

function onProductionFileChange(event) {
    const selected = event.target.files?.[0] || null;

    if (selected && !/\.docx$/i.test(selected.name)) {
        error.value = 'Production files must be DOCX.';
        productionFile.value = null;
        return;
    }

    productionFile.value = selected;
    error.value = '';
}

async function uploadProductionDocument() {
    if (!productionFile.value) {
        error.value = 'Choose a DOCX production file to upload.';
        return;
    }

    const fd = new FormData();
    fd.append('document', productionFile.value);

    try {
        await getJournalClient().post(`/submissions/${submission.value.id}/production-document`, fd, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        message.value = 'Production document uploaded.';
        error.value = '';
        productionFile.value = null;
        await load();
    } catch (e) {
        error.value = e.response?.data?.message || 'Unable to upload production document.';
    }
}
</script>

<template>
    <div class="space-y-6">
        <RouterLink
            to="/admin/journal"
            class="inline-flex text-sm font-semibold text-brand-700 hover:text-brand-800"
        >
            ← All submissions
        </RouterLink>

        <div v-if="loading" class="empty-state bg-white">Loading submission…</div>

        <div v-else-if="!submission" class="rounded-2xl border border-slate-200 bg-white p-10 text-center text-slate-500">
            Submission not found.
        </div>

        <template v-else>
            <header class="overflow-hidden rounded-2xl border border-slate-200/80 bg-gradient-to-br from-slate-50 via-white to-brand-50/30 shadow-sm">
                <div class="flex flex-col gap-4 p-5 sm:p-6 lg:flex-row lg:items-start lg:justify-between">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <JournalStatusBadge :status="submission.status" />
                            <span
                                v-if="submission.receipt_acknowledged"
                                class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-600/15"
                            >
                                <CheckCircleIcon class="size-3.5" />
                                Receipt acknowledged
                            </span>
                        </div>
                        <h1 class="mt-3 text-2xl font-semibold leading-snug tracking-tight text-slate-900 sm:text-3xl">
                            {{ titleCase(submission.title) }}
                        </h1>
                        <p class="mt-2 text-sm text-slate-600">
                            <span class="font-medium text-slate-800">{{ submission.author_name }}</span>
                            <span v-if="submission.author_email"> · {{ submission.author_email }}</span>
                        </p>
                        <div class="mt-4 flex flex-wrap gap-2 text-xs text-slate-600">
                            <span v-if="submission.category" class="rounded-lg bg-white px-2.5 py-1 ring-1 ring-slate-200">
                                {{ submission.category }}
                            </span>
                            <span class="inline-flex items-center gap-1 rounded-lg bg-white px-2.5 py-1 ring-1 ring-slate-200">
                                <CalendarDaysIcon class="size-3.5" />
                                Submitted {{ formatJournalDate(submission.date_submitted) }}
                            </span>
                            <span
                                v-if="submission.reviewer_name"
                                class="rounded-lg bg-white px-2.5 py-1 ring-1 ring-slate-200"
                            >
                                Reviewer: {{ submission.reviewer_name }}
                            </span>
                        </div>
                    </div>
                    <div class="flex shrink-0 flex-wrap gap-2">
                        <button
                            type="button"
                            class="btn-secondary inline-flex items-center gap-2 !rounded-xl"
                            @click="downloadDocument('manuscript')"
                        >
                            <ArrowDownTrayIcon class="size-4" />
                            Manuscript
                        </button>
                        <button
                            v-if="isApproved && canJournalPublish()"
                            type="button"
                            class="btn-secondary inline-flex items-center gap-2 !rounded-xl"
                            @click="downloadDocument('production')"
                        >
                            <ArrowDownTrayIcon class="size-4" />
                            Production file
                        </button>
                    </div>
                </div>
            </header>

            <AdminAlert v-if="message" type="success">{{ message }}</AdminAlert>
            <AdminAlert v-if="error" type="error">{{ error }}</AdminAlert>

            <div class="grid gap-6 xl:grid-cols-[minmax(0,1.6fr)_minmax(18rem,0.9fr)]">
                <div class="space-y-6">
                    <AdminPanel title="Abstract" description="Author-submitted summary and keywords.">
                        <p class="whitespace-pre-wrap text-sm leading-relaxed text-slate-700">{{ submission.abstract }}</p>
                        <div v-if="keywordList.length" class="mt-4 flex flex-wrap gap-2">
                            <span
                                v-for="keyword in keywordList"
                                :key="keyword"
                                class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700"
                            >
                                {{ keyword }}
                            </span>
                        </div>
                    </AdminPanel>

                    <AdminPanel title="Activity timeline">
                        <ol v-if="timeline.length" class="relative space-y-0 border-l border-slate-200 pl-5">
                            <li
                                v-for="event in timeline"
                                :key="event.id"
                                class="relative pb-5 last:pb-0"
                            >
                                <span class="absolute -left-[1.45rem] top-1 flex size-3 items-center justify-center rounded-full bg-brand-600 ring-4 ring-white" />
                                <p class="text-sm font-medium text-slate-900">{{ timelineLabel(event.event) }}</p>
                                <p class="mt-0.5 inline-flex items-center gap-1 text-xs text-slate-500">
                                    <ClockIcon class="size-3.5" />
                                    {{ formatJournalDate(event.occurred_at) }}
                                </p>
                            </li>
                        </ol>
                        <p v-else class="text-sm text-slate-500">No timeline events yet.</p>
                    </AdminPanel>

                    <AdminPanel title="Editorial comments">
                        <ul v-if="comments.length" class="space-y-3">
                            <li
                                v-for="comment in comments"
                                :key="comment.id"
                                class="rounded-xl bg-slate-50 px-4 py-3 text-sm ring-1 ring-slate-100"
                            >
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    {{ comment.author_role }}
                                </p>
                                <p class="mt-1 text-slate-800">{{ comment.comment }}</p>
                            </li>
                        </ul>
                        <div v-else class="flex items-start gap-3 text-sm text-slate-500">
                            <ChatBubbleLeftRightIcon class="mt-0.5 size-5 shrink-0 text-slate-400" />
                            <p>No editorial comments yet. Discussion will appear here as reviewers and editors respond.</p>
                        </div>
                    </AdminPanel>
                </div>

                <aside class="space-y-6">
                    <AdminPanel
                        v-if="canJournalAssign() && supportsAcknowledge"
                        title="Before review · Acknowledge receipt"
                        description="Optional first step: email the author that their manuscript was received."
                    >
                        <p
                            v-if="submission.receipt_acknowledged"
                            class="text-sm font-medium text-emerald-700"
                        >
                            Already acknowledged{{ submission.receipt_acknowledged_at ? ` · ${formatJournalDate(submission.receipt_acknowledged_at)}` : '' }}.
                        </p>
                        <p
                            v-else-if="submission.status === 'payment_pending'"
                            class="text-sm text-amber-700"
                        >
                            Complete payment approval before acknowledging receipt.
                        </p>
                        <template v-else>
                            <p class="text-sm text-slate-600">
                                Sends a formal acknowledgement from {{ siteName }} to
                                <span class="font-medium text-slate-800">{{ submission.author_email }}</span>.
                            </p>
                            <button
                                type="button"
                                class="btn-primary mt-4 inline-flex w-full items-center justify-center gap-2 !rounded-xl"
                                :disabled="!canAcknowledge || acknowledging"
                                @click="acknowledgeReceipt"
                            >
                                <EnvelopeOpenIcon class="size-4" />
                                {{ acknowledging ? 'Sending…' : 'Acknowledge & email author' }}
                            </button>
                        </template>
                    </AdminPanel>

                    <AdminPanel
                        v-if="canJournalAssign()"
                        title="1. Assign a peer reviewer"
                        description="Moves this manuscript into peer review and emails the selected reviewer."
                    >
                        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                            <p class="text-xs text-slate-500">{{ reviewers.length }} in reviewer pool</p>
                            <RouterLink
                                to="/admin/journal/reviewers"
                                class="inline-flex items-center gap-1 text-xs font-semibold text-brand-700 hover:underline"
                            >
                                <UserPlusIcon class="size-3.5" />
                                Invite reviewer
                            </RouterLink>
                        </div>

                        <p v-if="!reviewers.length" class="rounded-xl bg-amber-50 px-3 py-2 text-sm text-amber-800 ring-1 ring-amber-200/70">
                            No assignable reviewers yet. Invite them under
                            <RouterLink to="/admin/journal/reviewers" class="font-semibold underline">Peer reviewers</RouterLink>
                            — not Editorial Board.
                        </p>

                        <template v-else>
                            <label class="label">Who should review this?</label>
                            <div class="relative">
                                <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                                <input
                                    v-model="reviewerSearch"
                                    type="search"
                                    class="input w-full !rounded-xl pl-9"
                                    placeholder="Search by name or email…"
                                />
                            </div>
                            <select
                                v-model="assignForm.reviewer_id"
                                class="input mt-2 !rounded-xl"
                                :disabled="!filteredReviewers.length"
                            >
                                <option value="">Choose a reviewer</option>
                                <option
                                    v-for="reviewer in filteredReviewers"
                                    :key="reviewer.id"
                                    :value="reviewer.id"
                                >
                                    {{ reviewer.name }} — {{ reviewer.email }}
                                </option>
                            </select>
                            <p v-if="reviewerSearch && !filteredReviewers.length" class="mt-2 text-xs text-amber-700">
                                No reviewers match that search.
                            </p>

                            <div class="mt-4 rounded-xl bg-slate-50 p-3 ring-1 ring-slate-200/80">
                                <div class="flex items-start gap-2">
                                    <FlagIcon class="mt-0.5 size-4 shrink-0 text-slate-500" />
                                    <div class="min-w-0 flex-1">
                                        <label class="label !mt-0">Urgency for the reviewer</label>
                                        <p class="mb-2 text-xs leading-relaxed text-slate-500">
                                            Optional label in their review queue. It does not change permissions or send reminders.
                                        </p>
                                        <div class="grid grid-cols-3 gap-1.5">
                                            <button
                                                v-for="level in ['low', 'normal', 'high']"
                                                :key="level"
                                                type="button"
                                                class="rounded-lg px-2 py-2 text-center text-xs font-semibold capitalize transition ring-1"
                                                :class="assignForm.priority === level
                                                    ? 'bg-brand-600 text-white ring-brand-600'
                                                    : 'bg-white text-slate-600 ring-slate-200 hover:bg-slate-100'"
                                                @click="assignForm.priority = level"
                                            >
                                                {{ level }}
                                            </button>
                                        </div>
                                        <p class="mt-2 text-xs leading-relaxed text-slate-500">{{ priorityHint }}</p>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-3 rounded-xl bg-slate-50 p-3 ring-1 ring-slate-200/80">
                                <div class="flex items-start gap-2">
                                    <CalendarDaysIcon class="mt-0.5 size-4 shrink-0 text-slate-500" />
                                    <div class="min-w-0 flex-1">
                                        <label class="label !mt-0">
                                            Review due date
                                            <span class="font-normal text-slate-400">(optional)</span>
                                        </label>
                                        <p class="mb-2 text-xs leading-relaxed text-slate-500">
                                            Shown to the reviewer and included in their assignment email. Nothing happens automatically when it passes.
                                        </p>
                                        <input v-model="assignForm.due_at" type="date" class="input !rounded-xl" />
                                    </div>
                                </div>
                            </div>

                            <button
                                type="button"
                                class="btn-primary mt-4 w-full !rounded-xl"
                                :disabled="!assignForm.reviewer_id || assigning"
                                @click="assignReviewer"
                            >
                                {{ assigning ? 'Assigning…' : 'Assign & notify reviewer' }}
                            </button>
                        </template>
                    </AdminPanel>

                    <AdminPanel
                        title="2. Final editorial decision"
                        description="After peer review, accept or reject this manuscript. This emails the author and updates status."
                    >
                        <p class="mb-3 rounded-xl bg-sky-50 px-3 py-2 text-xs leading-relaxed text-sky-900 ring-1 ring-sky-200/70">
                            Use this when you are ready to close the review — not when assigning a reviewer.
                        </p>

                        <div class="grid grid-cols-2 gap-2">
                            <button
                                type="button"
                                class="flex flex-col items-start gap-1 rounded-xl p-3 text-left ring-1 transition"
                                :class="reviewForm.decision === 'accept'
                                    ? 'bg-emerald-50 ring-emerald-300'
                                    : 'bg-white ring-slate-200 hover:bg-slate-50'"
                                @click="reviewForm.decision = 'accept'"
                            >
                                <span class="inline-flex items-center gap-1.5 text-sm font-semibold text-emerald-800">
                                    <CheckCircleIcon class="size-4" />
                                    Accept
                                </span>
                                <span class="text-[11px] leading-snug text-slate-500">Approve for publication</span>
                            </button>
                            <button
                                type="button"
                                class="flex flex-col items-start gap-1 rounded-xl p-3 text-left ring-1 transition"
                                :class="reviewForm.decision === 'reject'
                                    ? 'bg-rose-50 ring-rose-300'
                                    : 'bg-white ring-slate-200 hover:bg-slate-50'"
                                @click="reviewForm.decision = 'reject'"
                            >
                                <span class="inline-flex items-center gap-1.5 text-sm font-semibold text-rose-800">
                                    <XCircleIcon class="size-4" />
                                    Reject
                                </span>
                                <span class="text-[11px] leading-snug text-slate-500">Decline & notify author</span>
                            </button>
                        </div>
                        <p class="mt-2 text-xs leading-relaxed text-slate-500">{{ decisionHint }}</p>

                        <label class="label mt-3">
                            Comment for the author
                            <span class="font-normal text-slate-400">(optional)</span>
                        </label>
                        <textarea
                            v-model="reviewForm.review_comment"
                            rows="3"
                            class="input !rounded-xl"
                            placeholder="Shared with the author in the decision email"
                        />
                        <template v-if="reviewForm.decision === 'reject'">
                            <label class="label mt-3">Why is this rejected?</label>
                            <textarea
                                v-model="reviewForm.rejection_reason"
                                rows="3"
                                class="input !rounded-xl"
                                placeholder="Required — included in the author email"
                            />
                        </template>
                        <button
                            type="button"
                            class="btn-primary mt-4 w-full !rounded-xl"
                            :disabled="deciding || (reviewForm.decision === 'reject' && !reviewForm.rejection_reason.trim())"
                            @click="submitReview"
                        >
                            {{ deciding ? 'Saving…' : (reviewForm.decision === 'reject' ? 'Reject & email author' : 'Accept & email author') }}
                        </button>
                    </AdminPanel>

                    <AdminPanel
                        v-if="isApproved && canJournalPublish()"
                        title="3. Production document"
                        description="Upload the final production-ready DOCX after acceptance."
                    >
                        <input
                            type="file"
                            accept=".docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                            class="block w-full text-sm text-slate-600"
                            @change="onProductionFileChange"
                        />
                        <button
                            type="button"
                            class="btn-secondary mt-4 w-full !rounded-xl"
                            :disabled="!productionFile"
                            @click="uploadProductionDocument"
                        >
                            Upload production DOCX
                        </button>
                    </AdminPanel>

                    <AdminPanel
                        v-if="canJournalAssign()"
                        title="Who can read this article?"
                        :description="isApproved
                            ? 'Controls public access to the accepted manuscript.'
                            : 'Takes effect after the manuscript is accepted. You can set it now.'"
                    >
                        <div class="grid grid-cols-1 gap-2">
                            <button
                                type="button"
                                class="flex items-start gap-3 rounded-xl p-3 text-left ring-1 transition"
                                :class="visibility === 'all'
                                    ? 'bg-brand-50 ring-brand-300'
                                    : 'bg-white ring-slate-200 hover:bg-slate-50'"
                                @click="visibility = 'all'"
                            >
                                <EyeIcon class="mt-0.5 size-5 shrink-0 text-brand-700" />
                                <span>
                                    <span class="block text-sm font-semibold text-slate-900">Everyone (public)</span>
                                    <span class="mt-0.5 block text-xs leading-relaxed text-slate-500">
                                        Listed on the public journal site for any visitor.
                                    </span>
                                </span>
                            </button>
                            <button
                                type="button"
                                class="flex items-start gap-3 rounded-xl p-3 text-left ring-1 transition"
                                :class="visibility === 'members_only'
                                    ? 'bg-brand-50 ring-brand-300'
                                    : 'bg-white ring-slate-200 hover:bg-slate-50'"
                                @click="visibility = 'members_only'"
                            >
                                <EyeSlashIcon class="mt-0.5 size-5 shrink-0 text-brand-700" />
                                <span>
                                    <span class="block text-sm font-semibold text-slate-900">Members only</span>
                                    <span class="mt-0.5 block text-xs leading-relaxed text-slate-500">
                                        Only active members can open it. Guests see it as restricted.
                                    </span>
                                </span>
                            </button>
                        </div>
                        <p class="mt-3 text-xs leading-relaxed text-slate-500">{{ visibilityHint }}</p>
                        <button type="button" class="btn-secondary mt-4 w-full !rounded-xl" @click="updateVisibility">
                            Save access setting
                        </button>
                    </AdminPanel>
                </aside>
            </div>
        </template>
    </div>
</template>
