<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRoute, RouterLink } from 'vue-router';
import { useAuth } from '../../../composables/useAuth';
import JournalStatusBadge from '../../../components/journal/JournalStatusBadge.vue';
import { formatJournalDate, downloadJournalDocument } from '../../../utils/journal';

const route = useRoute();
const { getJournalClient, memberToken } = useAuth();
const submission = ref(null);
const comments = ref([]);
const newComment = ref('');
const resubmitFile = ref(null);
const resubmitNote = ref('');
const message = ref('');
const error = ref('');

const submissionKey = computed(() => route.params.id);
const needsPayment = computed(() => submission.value?.status === 'payment_pending');
const paymentHref = computed(() => {
    if (!submission.value) {
        return '/member/payments';
    }

    const params = new URLSearchParams({
        purpose: 'journal_submission_fee',
        related: submission.value.id,
    });

    if (submission.value.submission_fee_amount) {
        params.set('amount', String(submission.value.submission_fee_amount));
    }

    return `/member/payments?${params.toString()}`;
});

onMounted(load);

async function load() {
    const { data } = await getJournalClient().get('/submissions/my');
    submission.value = (data.data || []).find((item) => item.id === submissionKey.value || item.slug === submissionKey.value) || null;

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

async function postComment() {
    await getJournalClient().post(`/submissions/${submission.value.id}/comments`, {
        comment: newComment.value,
        author_role: 'author',
    });
    newComment.value = '';
    message.value = 'Comment posted.';
    await load();
}

function onResubmitFileChange(event) {
    const selected = event.target.files?.[0] || null;

    if (selected && !/\.docx$/i.test(selected.name)) {
        error.value = 'Only DOCX manuscripts are accepted.';
        resubmitFile.value = null;
        return;
    }

    resubmitFile.value = selected;
    error.value = '';
}

async function resubmitRevision() {
    if (! resubmitFile.value) {
        error.value = 'Attach the revised manuscript as a DOCX file.';
        return;
    }

    const fd = new FormData();
    fd.append('document', resubmitFile.value);
    if (resubmitNote.value) {
        fd.append('note', resubmitNote.value);
    }

    try {
        await getJournalClient().post(`/submissions/${submission.value.id}/resubmit`, fd, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        message.value = 'Revision resubmitted successfully.';
        error.value = '';
        await load();
    } catch (e) {
        error.value = e.response?.data?.message || 'Unable to resubmit revision.';
    }
}
</script>

<template>
    <div>
        <RouterLink to="/member/journal" class="text-sm text-brand-600">← My submissions</RouterLink>

        <div v-if="!submission" class="mt-8 card p-8 text-center text-slate-500">Submission not found.</div>

        <template v-else>
            <div class="mt-4 flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">{{ submission.title }}</h1>
                    <p class="mt-1 text-sm text-slate-500">Submitted {{ formatJournalDate(submission.date_submitted) }}</p>
                </div>
                <JournalStatusBadge :status="submission.status" />
            </div>

            <p v-if="message" class="mt-3 text-sm text-green-600">{{ message }}</p>
            <p v-if="error" class="mt-3 text-sm text-red-600">{{ error }}</p>

            <div
                v-if="needsPayment"
                class="mt-6 rounded-2xl border border-amber-200 bg-amber-50/80 p-5"
            >
                <p class="font-semibold text-amber-900">Manuscript review fee required</p>
                <p class="mt-1 text-sm text-amber-800">
                    Transfer the fee to the UBA account, then upload your receipt so an admin can verify it in the app.
                </p>
                <RouterLink :to="paymentHref" class="btn-institutional mt-4 inline-flex">
                    Submit payment receipt
                </RouterLink>
            </div>

            <div class="mt-6 grid gap-6 lg:grid-cols-3">
                <div class="card space-y-4 p-6 lg:col-span-2">
                    <div>
                        <h2 class="font-semibold">Abstract</h2>
                        <p class="mt-2 whitespace-pre-wrap text-sm text-slate-700">{{ submission.abstract }}</p>
                    </div>
                    <div v-if="submission.review_comment">
                        <h2 class="font-semibold">Editorial note</h2>
                        <p class="mt-2 text-sm text-slate-700">{{ submission.review_comment }}</p>
                    </div>
                    <div v-if="submission.rejection_reason">
                        <h2 class="font-semibold text-red-700">Rejection reason</h2>
                        <p class="mt-2 text-sm text-red-700">{{ submission.rejection_reason }}</p>
                    </div>
                    <button class="btn-secondary" @click="downloadDocument">Download manuscript</button>
                </div>

                <div class="space-y-6">
                    <div v-if="submission.status === 'revision_requested'" class="card p-6">
                        <h2 class="font-semibold">Resubmit revision</h2>
                        <p class="mt-1 text-sm text-slate-600">Upload your revised manuscript as a DOCX file to continue review.</p>
                        <textarea v-model="resubmitNote" rows="3" class="input mt-4" placeholder="Optional note to reviewers" />
                        <input type="file" accept=".docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document" class="mt-3" @change="onResubmitFileChange" />
                        <button class="btn-primary mt-4 w-full" @click="resubmitRevision">Resubmit</button>
                    </div>

                    <div class="card p-6">
                        <h2 class="font-semibold">Comments</h2>
                        <div v-if="comments.length === 0" class="mt-3 text-sm text-slate-500">No comments yet.</div>
                        <ul v-else class="mt-3 space-y-3">
                            <li v-for="comment in comments" :key="comment.id" class="rounded-lg bg-slate-50 p-3 text-sm">
                                <p class="font-medium capitalize">{{ comment.author_role }}</p>
                                <p class="mt-1 text-slate-700">{{ comment.comment }}</p>
                            </li>
                        </ul>
                        <textarea v-model="newComment" rows="3" class="input mt-4" placeholder="Add a comment" />
                        <button class="btn-secondary mt-3 w-full" :disabled="!newComment" @click="postComment">Post comment</button>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>
