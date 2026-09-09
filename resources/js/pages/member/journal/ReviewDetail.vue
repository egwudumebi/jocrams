<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRoute, RouterLink } from 'vue-router';
import { useAuth } from '../../../composables/useAuth';
import JournalStatusBadge from '../../../components/journal/JournalStatusBadge.vue';
import { formatJournalDate, downloadJournalDocument } from '../../../utils/journal';

const route = useRoute();
const { getJournalClient, memberToken } = useAuth();
const submission = ref(null);
const timeline = ref([]);
const comments = ref([]);
const reviewForm = ref({ decision: 'accept', review_comment: '', rejection_reason: '' });
const revisionNote = ref('');
const newComment = ref('');
const message = ref('');
const error = ref('');

const submissionKey = computed(() => route.params.id);
const assignmentAccepted = computed(() => submission.value?.assignment_status === 'accepted');
const assignmentPending = computed(() => submission.value?.assignment_status === 'assigned');
const canReview = computed(() => assignmentAccepted.value);

onMounted(load);

async function load() {
    const [queueRes, timelineRes] = await Promise.all([
        getJournalClient().get('/queue'),
        getJournalClient().get(`/submissions/${submissionKey.value}/timeline`),
    ]);

    submission.value = (queueRes.data.data || []).find((item) => item.id === submissionKey.value || item.slug === submissionKey.value) || null;
    timeline.value = timelineRes.data.data || [];

    if (submission.value) {
        try {
            const commentsRes = await getJournalClient().get(`/submissions/${submission.value.id}/comments`);
            comments.value = commentsRes.data.data || [];
        } catch {
            comments.value = [];
        }
    }
}

async function downloadDocument() {
    if (! submission.value?.document_url) {
        return;
    }
    await downloadJournalDocument(submission.value.document_url, memberToken.value);
}

async function respondToAssignment(decision) {
    error.value = '';
    try {
        await getJournalClient().post(`/submissions/${submission.value.id}/assignment/respond`, {
            assignment_id: submission.value.assignment_id,
            decision,
        });
        message.value = decision === 'accept' ? 'Assignment accepted.' : 'Assignment declined.';
        await load();
    } catch (e) {
        error.value = e.response?.data?.message || 'Unable to update assignment.';
    }
}

async function submitReview() {
    error.value = '';
    try {
        await getJournalClient().post(`/submissions/${submission.value.id}/review`, reviewForm.value);
        message.value = 'Review submitted.';
        await load();
    } catch (e) {
        error.value = e.response?.data?.message || 'Unable to submit review.';
    }
}

async function requestRevision() {
    error.value = '';
    try {
        await getJournalClient().post(`/submissions/${submission.value.id}/request-revision`, { note: revisionNote.value });
        message.value = 'Revision requested.';
        await load();
    } catch (e) {
        error.value = e.response?.data?.message || 'Unable to request revision.';
    }
}

async function postComment() {
    await getJournalClient().post(`/submissions/${submission.value.id}/comments`, {
        comment: newComment.value,
        author_role: 'reviewer',
    });
    newComment.value = '';
    await load();
}
</script>

<template>
    <div>
        <RouterLink to="/member/journal/review" class="text-sm text-brand-600">← Reviewer queue</RouterLink>

        <div v-if="!submission" class="mt-8 card p-8 text-center text-slate-500">Submission not found in your queue.</div>

        <template v-else>
            <div class="mt-4 flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">{{ submission.title }}</h1>
                    <p class="mt-1 text-sm text-slate-500">{{ submission.author_name }} · {{ submission.category }}</p>
                </div>
                <JournalStatusBadge :status="submission.status" />
            </div>

            <p v-if="message" class="mt-3 text-sm text-green-600">{{ message }}</p>
            <p v-if="error" class="mt-3 text-sm text-red-600">{{ error }}</p>

            <div v-if="assignmentPending" class="mt-6 card border-amber-200 bg-amber-50 p-5">
                <h2 class="font-semibold text-amber-900">Reviewer assignment pending</h2>
                <p class="mt-1 text-sm text-amber-800">Accept this assignment before downloading the manuscript or submitting your review.</p>
                <div class="mt-4 flex flex-wrap gap-3">
                    <button class="btn-primary" @click="respondToAssignment('accept')">Accept assignment</button>
                    <button class="btn-secondary" @click="respondToAssignment('decline')">Decline</button>
                </div>
            </div>

            <div class="mt-6 grid gap-6 lg:grid-cols-3">
                <div class="card space-y-4 p-6 lg:col-span-2">
                    <p class="whitespace-pre-wrap text-sm text-slate-700">{{ submission.abstract }}</p>
                    <button class="btn-secondary" :disabled="assignmentPending" @click="downloadDocument">Download manuscript</button>

                    <div>
                        <h2 class="font-semibold">Timeline</h2>
                        <ul class="mt-3 space-y-2 text-sm text-slate-600">
                            <li v-for="event in timeline" :key="event.id">
                                {{ event.event }} · {{ formatJournalDate(event.occurred_at) }}
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="space-y-6">
                    <div class="card p-6">
                        <h2 class="font-semibold">Review decision</h2>
                        <p v-if="assignmentPending" class="mt-2 text-sm text-slate-500">Accept the assignment to unlock review actions.</p>
                        <select v-model="reviewForm.decision" class="input mt-3" :disabled="!canReview">
                            <option value="accept">Accept</option>
                            <option value="reject">Reject</option>
                        </select>
                        <textarea v-model="reviewForm.review_comment" rows="3" class="input mt-3" placeholder="Review comment" :disabled="!canReview" />
                        <textarea
                            v-if="reviewForm.decision === 'reject'"
                            v-model="reviewForm.rejection_reason"
                            rows="3"
                            class="input mt-3"
                            placeholder="Rejection reason (required)"
                            :disabled="!canReview"
                        />
                        <button class="btn-primary mt-4 w-full" :disabled="!canReview" @click="submitReview">Submit review</button>
                    </div>

                    <div class="card p-6">
                        <h2 class="font-semibold">Request revision</h2>
                        <textarea v-model="revisionNote" rows="3" class="input mt-3" placeholder="Revision note" :disabled="!canReview" />
                        <button class="btn-secondary mt-4 w-full" :disabled="!canReview" @click="requestRevision">Request revision</button>
                    </div>

                    <div class="card p-6">
                        <h2 class="font-semibold">Comments</h2>
                        <ul class="mt-3 space-y-2 text-sm">
                            <li v-for="comment in comments" :key="comment.id" class="rounded-lg bg-slate-50 p-3">
                                <span class="font-medium capitalize">{{ comment.author_role }}</span>: {{ comment.comment }}
                            </li>
                        </ul>
                        <textarea v-model="newComment" rows="3" class="input mt-3" :disabled="assignmentPending" />
                        <button class="btn-secondary mt-3 w-full" :disabled="!newComment || assignmentPending" @click="postComment">Add comment</button>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>
