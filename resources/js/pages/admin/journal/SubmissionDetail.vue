<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRoute, RouterLink } from 'vue-router';
import { useAuth } from '../../../composables/useAuth';
import JournalStatusBadge from '../../../components/journal/JournalStatusBadge.vue';
import { formatJournalDate, downloadJournalDocument } from '../../../utils/journal';

const route = useRoute();
const { getJournalClient, adminToken, fetchAdminProfile, canJournalAssign, canJournalPublish } = useAuth();
const submission = ref(null);
const reviewers = ref([]);
const timeline = ref([]);
const comments = ref([]);
const assignForm = ref({ reviewer_id: '', priority: 'normal', due_at: '' });
const reviewForm = ref({ decision: 'accept', review_comment: '', rejection_reason: '' });
const visibility = ref('all');
const productionFile = ref(null);
const message = ref('');
const error = ref('');

const submissionKey = computed(() => route.params.id);
const isApproved = computed(() => submission.value?.status === 'approved');

onMounted(load);

async function load() {
    await fetchAdminProfile();

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
        timeline.value = timelineRes.data.data || [];
        comments.value = commentsRes.data.data || [];
    }
}

async function downloadDocument(variant = 'manuscript') {
    await downloadJournalDocument(submission.value.document_url, adminToken.value, variant);
}

async function assignReviewer() {
    await getJournalClient().post(`/submissions/${submission.value.id}/assign`, assignForm.value);
    message.value = 'Reviewer assigned.';
    await load();
}

async function submitReview() {
    await getJournalClient().post(`/submissions/${submission.value.id}/review`, reviewForm.value);
    message.value = 'Review recorded.';
    await load();
}

async function updateVisibility() {
    await getJournalClient().put(`/submissions/${submission.value.id}/visibility`, { visibility: visibility.value });
    message.value = 'Visibility updated.';
    await load();
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
    <div>
        <RouterLink to="/admin/journal" class="text-sm text-brand-600">← All submissions</RouterLink>

        <div v-if="!submission" class="mt-8 card p-8 text-center text-slate-500">Submission not found.</div>

        <template v-else>
            <div class="mt-4 flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">{{ submission.title }}</h1>
                    <p class="mt-1 text-sm text-slate-500">{{ submission.author_name }} · {{ submission.author_email }}</p>
                </div>
                <JournalStatusBadge :status="submission.status" />
            </div>

            <p v-if="message" class="mt-3 text-sm text-green-600">{{ message }}</p>
            <p v-if="error" class="mt-3 text-sm text-red-600">{{ error }}</p>

            <div class="mt-6 grid gap-6 xl:grid-cols-3">
                <div class="card space-y-4 p-6 xl:col-span-2">
                    <p class="whitespace-pre-wrap text-sm text-slate-700">{{ submission.abstract }}</p>
                    <div class="flex flex-wrap gap-3">
                        <button class="btn-secondary" @click="downloadDocument('manuscript')">Download manuscript</button>
                        <button v-if="isApproved && canJournalPublish()" class="btn-secondary" @click="downloadDocument('production')">Download production file</button>
                    </div>

                    <div>
                        <h2 class="font-semibold">Timeline</h2>
                        <ul class="mt-3 space-y-2 text-sm text-slate-600">
                            <li v-for="event in timeline" :key="event.id">{{ event.event }} · {{ formatJournalDate(event.occurred_at) }}</li>
                        </ul>
                    </div>

                    <div>
                        <h2 class="font-semibold">Comments</h2>
                        <ul class="mt-3 space-y-2 text-sm">
                            <li v-for="comment in comments" :key="comment.id" class="rounded-lg bg-slate-50 p-3">
                                <span class="font-medium capitalize">{{ comment.author_role }}</span>: {{ comment.comment }}
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="space-y-6">
                    <div v-if="canJournalAssign()" class="card p-6">
                        <h2 class="font-semibold">Assign reviewer</h2>
                        <select v-model="assignForm.reviewer_id" class="input mt-3">
                            <option value="">Select reviewer</option>
                            <option v-for="reviewer in reviewers" :key="reviewer.id" :value="reviewer.id">
                                {{ reviewer.name }} ({{ reviewer.email }})
                            </option>
                        </select>
                        <select v-model="assignForm.priority" class="input mt-3">
                            <option value="low">Low</option>
                            <option value="normal">Normal</option>
                            <option value="high">High</option>
                        </select>
                        <input v-model="assignForm.due_at" type="date" class="input mt-3" />
                        <button class="btn-primary mt-4 w-full" :disabled="!assignForm.reviewer_id" @click="assignReviewer">Assign</button>
                    </div>

                    <div class="card p-6">
                        <h2 class="font-semibold">Review decision</h2>
                        <select v-model="reviewForm.decision" class="input mt-3">
                            <option value="accept">Accept</option>
                            <option value="reject">Reject</option>
                        </select>
                        <textarea v-model="reviewForm.review_comment" rows="3" class="input mt-3" />
                        <textarea
                            v-if="reviewForm.decision === 'reject'"
                            v-model="reviewForm.rejection_reason"
                            rows="3"
                            class="input mt-3"
                            placeholder="Rejection reason"
                        />
                        <button class="btn-primary mt-4 w-full" @click="submitReview">Submit review</button>
                    </div>

                    <div v-if="isApproved && canJournalPublish()" class="card p-6">
                        <h2 class="font-semibold">Production document</h2>
                        <p class="mt-1 text-sm text-slate-600">Upload the final production-ready DOCX after acceptance.</p>
                        <input
                            type="file"
                            accept=".docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                            class="mt-3"
                            @change="onProductionFileChange"
                        />
                        <button class="btn-secondary mt-4 w-full" :disabled="!productionFile" @click="uploadProductionDocument">
                            Upload production DOCX
                        </button>
                    </div>

                    <div v-if="canJournalAssign()" class="card p-6">
                        <h2 class="font-semibold">Visibility</h2>
                        <select v-model="visibility" class="input mt-3">
                            <option value="all">Public</option>
                            <option value="members_only">Members only</option>
                        </select>
                        <button class="btn-secondary mt-4 w-full" @click="updateVisibility">Update visibility</button>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>
