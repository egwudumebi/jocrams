<script setup>
import { computed, ref, onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import {
    ArrowRightIcon,
    CheckBadgeIcon,
    ClipboardDocumentCheckIcon,
    ClockIcon,
    DocumentArrowUpIcon,
    DocumentTextIcon,
    PlusIcon,
} from '@heroicons/vue/24/outline';
import AdminAlert from '../../components/admin/AdminAlert.vue';
import AdminEmptyState from '../../components/admin/AdminEmptyState.vue';
import AdminPageIntro from '../../components/admin/AdminPageIntro.vue';
import AdminPanel from '../../components/admin/AdminPanel.vue';
import FormFieldLabel from '../../components/admin/FormFieldLabel.vue';
import { publicApi } from '../../api/client';
import { useAuth } from '../../composables/useAuth';
import { extractApiError } from '../../utils/apiError';

const { getMemberClient, memberUser, fetchMemberProfile } = useAuth();

const tiers = ref([]);
const applications = ref([]);
const loading = ref(true);
const saving = ref(false);
const uploading = ref(false);
const submitting = ref(false);
const showNewForm = ref(false);
const activeApp = ref(null);
const message = ref('');
const error = ref('');

const form = ref({ membership_tier_id: '', form_data: { organization: '' } });
const docForm = ref({ document_type: 'national_id', file: null });
const fileInput = ref(null);

const documentTypes = [
    { value: 'national_id', label: 'National ID' },
    { value: 'passport', label: 'Passport' },
    { value: 'professional_certificate', label: 'Professional certificate' },
    { value: 'cv', label: 'CV / Resume' },
];

const hasActiveMembership = computed(() => memberUser.value?.member?.status === 'active');

const draftApplications = computed(() => applications.value.filter((app) => app.status === 'draft'));
const pendingApplications = computed(() => applications.value.filter((app) => ['submitted', 'under_review'].includes(app.status)));
const approvedApplications = computed(() => applications.value.filter((app) => app.status === 'approved'));
const rejectedApplications = computed(() => applications.value.filter((app) => app.status === 'rejected'));

const selectedTier = computed(() => tiers.value.find((tier) => String(tier.id) === String(form.value.membership_tier_id)) || null);

const canSubmitActiveApp = computed(() => {
    if (!activeApp.value || activeApp.value.status !== 'draft') {
        return false;
    }

    const minRequired = activeApp.value.tier?.min_documents_required ?? 1;

    return (activeApp.value.documents?.length || 0) >= minRequired;
});

onMounted(async () => {
    await fetchMemberProfile();
    await load();
});

async function load() {
    loading.value = true;
    error.value = '';

    try {
        const [tiersRes, appsRes] = await Promise.all([
            publicApi().get('/membership-tiers'),
            getMemberClient().get('/applications'),
        ]);

        tiers.value = tiersRes.data.data || [];
        applications.value = appsRes.data.data || [];

        if (!activeApp.value && draftApplications.value.length) {
            activeApp.value = draftApplications.value[0];
        }
    } catch (e) {
        error.value = extractApiError(e, 'Unable to load applications.');
    } finally {
        loading.value = false;
    }
}

function statusLabel(status) {
    const map = {
        draft: 'Draft',
        submitted: 'Submitted',
        under_review: 'Under review',
        approved: 'Approved',
        rejected: 'Rejected',
        cancelled: 'Cancelled',
    };

    return map[status] || status;
}

function statusClass(status) {
    const map = {
        draft: 'bg-slate-100 text-slate-700',
        submitted: 'bg-amber-100 text-amber-800',
        under_review: 'bg-amber-100 text-amber-800',
        approved: 'bg-emerald-100 text-emerald-800',
        rejected: 'bg-red-100 text-red-800',
        cancelled: 'bg-slate-100 text-slate-600',
    };

    return map[status] || 'bg-slate-100 text-slate-700';
}

function formatDate(value) {
    return value ? new Date(value).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' }) : '—';
}

function formatDocumentType(type) {
    return documentTypes.find((item) => item.value === type)?.label || type.replaceAll('_', ' ');
}

function openNewApplication() {
    if (hasActiveMembership.value) {
        return;
    }

    showNewForm.value = true;
    activeApp.value = null;
    form.value = { membership_tier_id: '', form_data: { organization: '' } };
}

function manageApplication(app) {
    activeApp.value = app;
    showNewForm.value = false;
}

async function createApplication() {
    message.value = '';
    error.value = '';
    saving.value = true;

    try {
        const { data } = await getMemberClient().post('/applications', form.value);
        applications.value.unshift(data.data);
        activeApp.value = data.data;
        showNewForm.value = false;
        message.value = 'Application created. Upload the required documents, then submit for review.';
    } catch (e) {
        error.value = extractApiError(e, 'Unable to create application.');
    } finally {
        saving.value = false;
    }
}

function onFileChange(event) {
    docForm.value.file = event.target.files?.[0] || null;
}

function openFilePicker() {
    fileInput.value?.click();
}

async function uploadDocument() {
    if (!activeApp.value || !docForm.value.file) {
        return;
    }

    message.value = '';
    error.value = '';
    uploading.value = true;

    const fd = new FormData();
    fd.append('document', docForm.value.file);
    fd.append('document_type', docForm.value.document_type);

    try {
        await getMemberClient().post(`/applications/${activeApp.value.uuid}/documents`, fd, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });

        const { data } = await getMemberClient().get(`/applications/${activeApp.value.uuid}`);
        activeApp.value = data.data;

        const index = applications.value.findIndex((app) => app.uuid === data.data.uuid);
        if (index >= 0) {
            applications.value[index] = data.data;
        }

        docForm.value.file = null;
        if (fileInput.value) {
            fileInput.value.value = '';
        }

        message.value = 'Document uploaded successfully.';
    } catch (e) {
        error.value = extractApiError(e, 'Unable to upload document.');
    } finally {
        uploading.value = false;
    }
}

async function submitApplication() {
    if (!activeApp.value) {
        return;
    }

    message.value = '';
    error.value = '';
    submitting.value = true;

    try {
        const { data } = await getMemberClient().post(`/applications/${activeApp.value.uuid}/submit`);
        activeApp.value = data.data;

        const index = applications.value.findIndex((app) => app.uuid === data.data.uuid);
        if (index >= 0) {
            applications.value[index] = data.data;
        }

        message.value = 'Application submitted for review.';
        await load();
    } catch (e) {
        error.value = extractApiError(e, 'Unable to submit application.');
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <div>
        <AdminPageIntro
            :description="hasActiveMembership
                ? 'Your membership is already active. Application history appears here if you applied through the portal.'
                : 'Start a membership application, upload supporting documents, and track review progress from one place.'"
        >
            <template #actions>
                <button
                    v-if="!hasActiveMembership"
                    type="button"
                    class="btn-institutional inline-flex items-center gap-2"
                    @click="openNewApplication"
                >
                    <PlusIcon class="size-4" />
                    New application
                </button>
            </template>
        </AdminPageIntro>

        <AdminAlert v-if="message" type="success">{{ message }}</AdminAlert>
        <AdminAlert v-if="error" type="error">{{ error }}</AdminAlert>

        <div v-if="hasActiveMembership" class="card-modern mb-6 border-emerald-200 bg-emerald-50 p-5">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="font-semibold text-emerald-900">You already have an active membership</p>
                    <p class="mt-1 text-sm text-emerald-800">
                        Your account is active as a {{ memberUser.member.tier?.name || 'member' }}. You can view credentials and manage your profile from the portal.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <RouterLink to="/member/credentials" class="btn-secondary !rounded-xl">View credentials</RouterLink>
                    <RouterLink to="/member/dashboard" class="btn-institutional !rounded-xl">Go to dashboard</RouterLink>
                </div>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-4">
            <div class="card-modern p-5">
                <div class="flex items-center gap-4">
                    <span class="metric-icon !size-12 !rounded-xl">
                        <DocumentTextIcon class="size-6" />
                    </span>
                    <div>
                        <p class="text-sm text-slate-500">Total applications</p>
                        <p class="text-2xl font-bold text-slate-900">{{ loading ? '—' : applications.length }}</p>
                    </div>
                </div>
            </div>
            <div class="card-modern p-5">
                <div class="flex items-center gap-4">
                    <span class="metric-icon !size-12 !rounded-xl">
                        <ClipboardDocumentCheckIcon class="size-6" />
                    </span>
                    <div>
                        <p class="text-sm text-slate-500">Drafts</p>
                        <p class="text-2xl font-bold text-slate-900">{{ loading ? '—' : draftApplications.length }}</p>
                    </div>
                </div>
            </div>
            <div class="card-modern p-5">
                <div class="flex items-center gap-4">
                    <span class="metric-icon !size-12 !rounded-xl">
                        <ClockIcon class="size-6" />
                    </span>
                    <div>
                        <p class="text-sm text-slate-500">Under review</p>
                        <p class="text-2xl font-bold text-slate-900">{{ loading ? '—' : pendingApplications.length }}</p>
                    </div>
                </div>
            </div>
            <div class="card-modern p-5">
                <div class="flex items-center gap-4">
                    <span class="metric-icon !size-12 !rounded-xl">
                        <CheckBadgeIcon class="size-6" />
                    </span>
                    <div>
                        <p class="text-sm text-slate-500">Approved</p>
                        <p class="text-2xl font-bold text-slate-900">{{ loading ? '—' : approvedApplications.length }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-6 grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
            <AdminPanel
                title="Your applications"
                :description="hasActiveMembership
                    ? 'Application history for your account. New applications are not needed while your membership is active.'
                    : 'Track status, continue drafts, and review submitted requests.'"
            >
                <div v-if="loading" class="flex justify-center py-16">
                    <div class="size-10 animate-spin rounded-full border-2 border-institutional/20 border-t-institutional" />
                </div>

                <AdminEmptyState
                    v-else-if="applications.length === 0 && hasActiveMembership"
                    title="No application history"
                    description="Your membership was activated directly by an administrator, so there is no self-service application on record. Use your credentials and profile to manage your membership."
                >
                    <template #icon>
                        <CheckBadgeIcon class="size-6" />
                    </template>
                    <template #action>
                        <div class="flex flex-wrap justify-center gap-2">
                            <RouterLink to="/member/credentials" class="btn-secondary">View credentials</RouterLink>
                            <RouterLink to="/member/profile" class="btn-institutional">Manage profile</RouterLink>
                        </div>
                    </template>
                </AdminEmptyState>

                <AdminEmptyState
                    v-else-if="applications.length === 0"
                    title="No applications yet"
                    description="Start a membership application to choose your tier, upload documents, and submit for review."
                >
                    <template #icon>
                        <DocumentTextIcon class="size-6" />
                    </template>
                    <template #action>
                        <button type="button" class="btn-institutional" @click="openNewApplication">Start application</button>
                    </template>
                </AdminEmptyState>

                <div v-else class="divide-y divide-slate-100">
                    <article
                        v-for="app in applications"
                        :key="app.uuid"
                        class="flex flex-col gap-4 p-4 transition sm:flex-row sm:items-center sm:justify-between"
                        :class="activeApp?.uuid === app.uuid ? 'bg-brand-50/60' : 'hover:bg-surface-muted/50'"
                    >
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="font-semibold text-slate-900">{{ app.tier?.name || 'Membership application' }}</h3>
                                <span class="badge capitalize" :class="statusClass(app.status)">
                                    {{ statusLabel(app.status) }}
                                </span>
                            </div>
                            <p class="mt-1 text-sm text-slate-500">
                                {{ app.form_data?.organization || 'No organization provided' }}
                            </p>
                            <p class="mt-2 text-xs text-slate-400">
                                Created {{ formatDate(app.created_at) }}
                                <span v-if="app.submitted_at"> · Submitted {{ formatDate(app.submitted_at) }}</span>
                            </p>
                        </div>
                        <button
                            type="button"
                            class="inline-flex shrink-0 items-center gap-1 text-sm font-semibold text-institutional hover:underline"
                            @click="manageApplication(app)"
                        >
                            {{ app.status === 'draft' ? 'Continue' : 'View details' }}
                            <ArrowRightIcon class="size-4" />
                        </button>
                    </article>
                </div>
            </AdminPanel>

            <div class="space-y-6">
                <AdminPanel
                    v-if="showNewForm && !hasActiveMembership"
                    title="New application"
                    description="Choose a membership tier and provide basic details to begin."
                >
                    <div class="grid gap-4">
                        <div>
                            <FormFieldLabel label="Membership tier" required help="Annual dues are shown for each tier." />
                            <select v-model="form.membership_tier_id" class="input !rounded-xl">
                                <option value="">Select tier</option>
                                <option v-for="tier in tiers" :key="tier.id" :value="tier.id">
                                    {{ tier.name }} — ₦{{ Number(tier.annual_dues).toLocaleString() }}/year
                                </option>
                            </select>
                        </div>

                        <div v-if="selectedTier" class="rounded-xl border border-slate-100 bg-surface-muted/60 p-4 text-sm text-slate-600">
                            <p class="font-medium text-slate-800">{{ selectedTier.name }}</p>
                            <p v-if="selectedTier.description" class="mt-1">{{ selectedTier.description }}</p>
                            <p class="mt-2">
                                Requires {{ selectedTier.min_documents_required || 1 }} document(s)
                                · {{ selectedTier.requires_approval ? 'Admin review required' : 'Auto-approved on submit' }}
                            </p>
                        </div>

                        <div>
                            <FormFieldLabel label="Organization" help="Your workplace, institution, or affiliated body." />
                            <input v-model="form.form_data.organization" class="input !rounded-xl" placeholder="e.g. University of Lagos" />
                        </div>
                    </div>

                    <div class="mt-6 flex flex-wrap gap-2">
                        <button
                            type="button"
                            class="btn-institutional"
                            :disabled="!form.membership_tier_id || saving"
                            @click="createApplication"
                        >
                            {{ saving ? 'Creating…' : 'Create application' }}
                        </button>
                        <button type="button" class="btn-secondary" @click="showNewForm = false">Cancel</button>
                    </div>
                </AdminPanel>

                <AdminPanel
                    v-else-if="activeApp"
                    :title="activeApp.status === 'draft' ? 'Complete application' : 'Application details'"
                    :description="activeApp.status === 'draft'
                        ? 'Upload required documents and submit when you are ready.'
                        : 'Review the current status and uploaded documents for this application.'"
                >
                    <dl class="grid gap-3 text-sm sm:grid-cols-2">
                        <div class="rounded-xl border border-slate-100 bg-surface-muted/40 p-4">
                            <dt class="text-slate-500">Tier</dt>
                            <dd class="mt-1 font-medium text-slate-900">{{ activeApp.tier?.name }}</dd>
                        </div>
                        <div class="rounded-xl border border-slate-100 bg-surface-muted/40 p-4">
                            <dt class="text-slate-500">Status</dt>
                            <dd class="mt-1">
                                <span class="badge capitalize" :class="statusClass(activeApp.status)">
                                    {{ statusLabel(activeApp.status) }}
                                </span>
                            </dd>
                        </div>
                        <div class="rounded-xl border border-slate-100 bg-surface-muted/40 p-4">
                            <dt class="text-slate-500">Organization</dt>
                            <dd class="mt-1 font-medium text-slate-900">{{ activeApp.form_data?.organization || '—' }}</dd>
                        </div>
                        <div class="rounded-xl border border-slate-100 bg-surface-muted/40 p-4">
                            <dt class="text-slate-500">Documents uploaded</dt>
                            <dd class="mt-1 font-medium text-slate-900">
                                {{ activeApp.documents?.length || 0 }} / {{ activeApp.tier?.min_documents_required || 1 }} required
                            </dd>
                        </div>
                    </dl>

                    <div v-if="activeApp.rejection_reason" class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                        <p class="font-medium">Rejection reason</p>
                        <p class="mt-1">{{ activeApp.rejection_reason }}</p>
                    </div>

                    <div v-if="activeApp.documents?.length" class="mt-5">
                        <h4 class="text-sm font-semibold text-slate-800">Uploaded documents</h4>
                        <ul class="mt-3 space-y-2">
                            <li
                                v-for="document in activeApp.documents"
                                :key="document.id"
                                class="flex items-center justify-between rounded-xl border border-slate-100 px-4 py-3 text-sm"
                            >
                                <span class="font-medium capitalize text-slate-800">{{ formatDocumentType(document.document_type) }}</span>
                                <span class="text-slate-500">{{ document.media_file?.original_name || 'Uploaded file' }}</span>
                            </li>
                        </ul>
                    </div>

                    <div v-if="activeApp.status === 'draft'" class="mt-6 border-t border-slate-100 pt-6">
                        <h4 class="text-sm font-semibold text-slate-800">Upload a document</h4>
                        <div class="mt-4 grid gap-4">
                            <div>
                                <FormFieldLabel label="Document type" required />
                                <select v-model="docForm.document_type" class="input !rounded-xl">
                                    <option v-for="type in documentTypes" :key="type.value" :value="type.value">
                                        {{ type.label }}
                                    </option>
                                </select>
                            </div>

                            <div>
                                <FormFieldLabel label="File" required help="PDF, JPG, or PNG up to 10 MB." />
                                <input
                                    ref="fileInput"
                                    type="file"
                                    accept=".pdf,.jpg,.jpeg,.png"
                                    class="hidden"
                                    @change="onFileChange"
                                />
                                <button
                                    type="button"
                                    class="flex w-full items-center justify-between gap-3 rounded-xl border border-dashed border-slate-300 bg-white px-4 py-4 text-left transition hover:border-institutional/40 hover:bg-surface-muted/40"
                                    @click="openFilePicker"
                                >
                                    <span class="flex items-center gap-3">
                                        <span class="flex size-10 items-center justify-center rounded-xl bg-institutional/10 text-institutional">
                                            <DocumentArrowUpIcon class="size-5" />
                                        </span>
                                        <span>
                                            <span class="block text-sm font-medium text-slate-900">
                                                {{ docForm.file?.name || 'Choose a file to upload' }}
                                            </span>
                                            <span class="mt-0.5 block text-xs text-slate-500">PDF, JPG, or PNG</span>
                                        </span>
                                    </span>
                                    <span class="text-sm font-semibold text-institutional">Browse</span>
                                </button>
                            </div>
                        </div>

                        <div class="mt-5 flex flex-wrap gap-2">
                            <button
                                type="button"
                                class="btn-secondary"
                                :disabled="!docForm.file || uploading"
                                @click="uploadDocument"
                            >
                                {{ uploading ? 'Uploading…' : 'Upload document' }}
                            </button>
                            <button
                                type="button"
                                class="btn-institutional"
                                :disabled="!canSubmitActiveApp || submitting"
                                @click="submitApplication"
                            >
                                {{ submitting ? 'Submitting…' : 'Submit for review' }}
                            </button>
                        </div>
                        <p v-if="!canSubmitActiveApp" class="mt-3 text-xs text-slate-500">
                            Upload at least {{ activeApp.tier?.min_documents_required || 1 }} document(s) before submitting.
                        </p>
                    </div>
                </AdminPanel>

                <AdminPanel
                    v-else-if="hasActiveMembership"
                    title="Active membership"
                    description="Your account is fully set up. Manage your membership from the links below."
                >
                    <dl class="grid gap-3 text-sm sm:grid-cols-2">
                        <div class="rounded-xl border border-slate-100 bg-surface-muted/40 p-4">
                            <dt class="text-slate-500">Tier</dt>
                            <dd class="mt-1 font-medium text-slate-900">{{ memberUser?.member?.tier?.name || 'Full Member' }}</dd>
                        </div>
                        <div class="rounded-xl border border-slate-100 bg-surface-muted/40 p-4">
                            <dt class="text-slate-500">Membership number</dt>
                            <dd class="mt-1 font-medium text-slate-900">{{ memberUser?.member?.membership_number || '—' }}</dd>
                        </div>
                        <div class="rounded-xl border border-slate-100 bg-surface-muted/40 p-4">
                            <dt class="text-slate-500">Status</dt>
                            <dd class="mt-1">
                                <span class="badge bg-emerald-100 text-emerald-800 capitalize">{{ memberUser?.member?.status || 'active' }}</span>
                            </dd>
                        </div>
                        <div class="rounded-xl border border-slate-100 bg-surface-muted/40 p-4">
                            <dt class="text-slate-500">Expires</dt>
                            <dd class="mt-1 font-medium text-slate-900">
                                {{ memberUser?.member?.expires_at ? formatDate(memberUser.member.expires_at) : '—' }}
                            </dd>
                        </div>
                    </dl>
                    <div class="mt-5 flex flex-wrap gap-2">
                        <RouterLink to="/member/credentials" class="btn-secondary">View credentials</RouterLink>
                        <RouterLink to="/member/payments" class="btn-institutional">Payment history</RouterLink>
                    </div>
                </AdminPanel>

                <AdminPanel
                    v-else-if="!loading && !hasActiveMembership"
                    title="Get started"
                    description="Select an existing draft to continue, or create a new membership application."
                >
                    <button type="button" class="btn-institutional inline-flex items-center gap-2" @click="openNewApplication">
                        <PlusIcon class="size-4" />
                        Start new application
                    </button>
                </AdminPanel>
            </div>
        </div>

        <AdminPanel
            v-if="rejectedApplications.length"
            class="mt-6"
            title="Rejected applications"
            description="Review feedback and start a new application if you wish to reapply."
        >
            <div class="divide-y divide-slate-100">
                <article v-for="app in rejectedApplications" :key="app.uuid" class="p-4">
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="font-semibold text-slate-900">{{ app.tier?.name }}</h3>
                        <span class="badge capitalize" :class="statusClass(app.status)">Rejected</span>
                    </div>
                    <p v-if="app.rejection_reason" class="mt-2 text-sm text-red-700">{{ app.rejection_reason }}</p>
                </article>
            </div>
        </AdminPanel>
    </div>
</template>
