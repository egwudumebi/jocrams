<script setup>
import { computed, onMounted, ref } from 'vue';
import {
    ArrowDownTrayIcon,
    CheckCircleIcon,
    ClipboardDocumentCheckIcon,
    DocumentTextIcon,
    EnvelopeIcon,
    PhoneIcon,
    UserCircleIcon,
    XCircleIcon,
    XMarkIcon,
} from '@heroicons/vue/24/outline';
import AdminAlert from '../../components/admin/AdminAlert.vue';
import AdminEmptyState from '../../components/admin/AdminEmptyState.vue';
import AdminPageIntro from '../../components/admin/AdminPageIntro.vue';
import { useAuth } from '../../composables/useAuth';
import { extractApiError } from '../../utils/apiError';
import { confirmAction, notifyError, notifySuccess } from '../../utils/swal';

const { getAdminClient } = useAuth();

const approvals = ref([]);
const loading = ref(true);
const selected = ref(null);
const actingUuid = ref('');
const downloadingId = ref(null);
const message = ref('');
const error = ref('');
const rejectOpen = ref(false);
const rejectReason = ref('');

const selectedApp = computed(() => selected.value?.application || selected.value?.approvable || null);

onMounted(load);

async function load() {
    loading.value = true;
    error.value = '';

    try {
        const { data } = await getAdminClient().get('/approvals');
        approvals.value = data.data || [];

        if (selected.value) {
            const still = approvals.value.find((item) => item.uuid === selected.value.uuid);
            selected.value = still || null;
        }
    } catch (e) {
        error.value = extractApiError(e, 'Unable to load applications.');
    } finally {
        loading.value = false;
    }
}

function openDetail(item) {
    selected.value = item;
    rejectOpen.value = false;
    rejectReason.value = '';
    error.value = '';
}

function closeDetail() {
    selected.value = null;
    rejectOpen.value = false;
    rejectReason.value = '';
}

function formatDate(value) {
    if (! value) {
        return '—';
    }

    return new Date(value).toLocaleString(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function formatMoney(amount, currency = 'NGN') {
    if (amount == null) {
        return '—';
    }

    return `${currency} ${Number(amount).toLocaleString()}`;
}

function docLabel(type) {
    return String(type || 'document').replaceAll('_', ' ');
}

async function downloadDocument(doc) {
    if (! selectedApp.value?.uuid || ! doc?.id) {
        return;
    }

    downloadingId.value = doc.id;
    error.value = '';

    try {
        const response = await getAdminClient().get(
            `/applications/${selectedApp.value.uuid}/documents/${doc.id}/download`,
            { responseType: 'blob' },
        );
        const disposition = response.headers['content-disposition'] || '';
        const match = disposition.match(/filename="?([^"]+)"?/i);
        const filename = match?.[1] || doc.filename || `document-${doc.id}`;
        const blob = new Blob([response.data], { type: response.headers['content-type'] || 'application/octet-stream' });
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(url);
    } catch (e) {
        error.value = extractApiError(e, 'Unable to download document.');
    } finally {
        downloadingId.value = null;
    }
}

async function approve(item) {
    const app = item.application || item.approvable;
    if (! app?.uuid) {
        return;
    }

    const confirmed = await confirmAction({
        title: 'Approve membership?',
        text: `Approve membership for ${app.applicant_name}?`,
        confirmText: 'Approve',
        cancelText: 'Cancel',
        tone: 'primary',
    });

    if (! confirmed) {
        return;
    }

    actingUuid.value = item.uuid;
    error.value = '';

    try {
        await getAdminClient().post(`/applications/${app.uuid}/approve`, {
            notes: 'Approved via admin panel',
        });
        message.value = `${app.applicant_name} approved.`;
        await notifySuccess('Membership approved', `${app.applicant_name} is now an active member.`);
        if (selected.value?.uuid === item.uuid) {
            closeDetail();
        }
        await load();
    } catch (e) {
        const msg = extractApiError(e, 'Unable to approve application.');
        error.value = msg;
        await notifyError('Approval failed', msg);
    } finally {
        actingUuid.value = '';
    }
}

function startReject(item) {
    selected.value = item;
    rejectOpen.value = true;
    rejectReason.value = '';
}

async function confirmReject() {
    const app = selectedApp.value;
    if (! app?.uuid || ! rejectReason.value.trim()) {
        return;
    }

    const confirmed = await confirmAction({
        title: 'Reject application?',
        text: `Reject ${app.applicant_name}'s membership application?`,
        confirmText: 'Reject',
        cancelText: 'Keep reviewing',
        tone: 'danger',
    });

    if (! confirmed) {
        return;
    }

    actingUuid.value = selected.value.uuid;
    error.value = '';

    try {
        await getAdminClient().post(`/applications/${app.uuid}/reject`, {
            reason: rejectReason.value.trim(),
        });
        message.value = `${app.applicant_name} rejected.`;
        await notifySuccess('Application rejected', `${app.applicant_name} has been notified.`);
        closeDetail();
        await load();
    } catch (e) {
        const msg = extractApiError(e, 'Unable to reject application.');
        error.value = msg;
        await notifyError('Rejection failed', msg);
    } finally {
        actingUuid.value = '';
    }
}
</script>

<template>
    <div>
        <AdminPageIntro description="Review membership applications, open uploaded documents, and approve or reject with full applicant context." />

        <AdminAlert v-if="message" type="success" class="mb-4">{{ message }}</AdminAlert>
        <AdminAlert v-if="error" type="error" class="mb-4">{{ error }}</AdminAlert>

        <div v-if="loading" class="flex justify-center py-16">
            <div class="size-8 animate-spin rounded-full border-2 border-institutional/20 border-t-institutional" />
        </div>

        <AdminEmptyState
            v-else-if="approvals.length === 0"
            title="No pending applications"
            description="New membership applications appear here when members submit their requests and documents."
        >
            <template #icon><ClipboardDocumentCheckIcon class="size-6" /></template>
        </AdminEmptyState>

        <div v-else class="grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
            <div class="space-y-4">
                <article
                    v-for="item in approvals"
                    :key="item.uuid"
                    class="cursor-pointer rounded-2xl border bg-white p-5 shadow-sm transition hover:border-institutional/30 hover:shadow-md"
                    :class="selected?.uuid === item.uuid ? 'border-institutional ring-2 ring-institutional/15' : 'border-slate-200'"
                    @click="openDetail(item)"
                >
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="min-w-0">
                            <div class="flex items-center gap-3">
                                <span class="flex size-11 items-center justify-center rounded-full bg-institutional/10 text-sm font-bold text-institutional">
                                    {{ (item.application?.applicant_name || item.approvable?.applicant_name || '?').slice(0, 1).toUpperCase() }}
                                </span>
                                <div>
                                    <p class="font-semibold text-slate-900">
                                        {{ item.application?.applicant_name || item.approvable?.applicant_name }}
                                    </p>
                                    <p class="text-sm text-slate-500">
                                        {{ item.application?.applicant_email || item.approvable?.applicant_email }}
                                    </p>
                                </div>
                            </div>
                            <div class="mt-4 flex flex-wrap gap-2 text-xs">
                                <span class="rounded-full bg-amber-50 px-2.5 py-1 font-medium text-amber-800">Under review</span>
                                <span
                                    v-if="item.application?.tier?.name"
                                    class="rounded-full bg-slate-100 px-2.5 py-1 font-medium text-slate-700"
                                >
                                    {{ item.application.tier.name }}
                                </span>
                                <span
                                    v-if="item.application?.documents_count != null"
                                    class="rounded-full bg-sky-50 px-2.5 py-1 font-medium text-sky-800"
                                >
                                    {{ item.application.documents_count }} document{{ item.application.documents_count === 1 ? '' : 's' }}
                                </span>
                            </div>
                            <p class="mt-3 text-xs text-slate-400">Submitted {{ formatDate(item.application?.submitted_at || item.created_at) }}</p>
                        </div>
                        <div class="flex gap-2" @click.stop>
                            <button
                                type="button"
                                class="btn-primary !rounded-xl !px-3 !py-2 text-sm"
                                :disabled="actingUuid === item.uuid"
                                @click="approve(item)"
                            >
                                Approve
                            </button>
                            <button
                                type="button"
                                class="btn-danger !rounded-xl !px-3 !py-2 text-sm"
                                :disabled="actingUuid === item.uuid"
                                @click="startReject(item)"
                            >
                                Reject
                            </button>
                        </div>
                    </div>
                </article>
            </div>

            <aside class="xl:sticky xl:top-24 xl:self-start">
                <div
                    v-if="! selectedApp"
                    class="rounded-2xl border border-dashed border-slate-200 bg-slate-50/80 p-10 text-center"
                >
                    <UserCircleIcon class="mx-auto size-10 text-slate-300" />
                    <p class="mt-3 font-medium text-slate-700">Select an application</p>
                    <p class="mt-1 text-sm text-slate-500">View applicant details, form answers, and download uploaded documents.</p>
                </div>

                <div v-else class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex items-start justify-between gap-3 bg-gradient-to-br from-institutional-dark to-institutional px-5 py-5 text-white">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-white/70">Application review</p>
                            <h2 class="mt-1 font-display text-xl font-bold">{{ selectedApp.applicant_name }}</h2>
                            <p class="mt-1 text-sm text-white/80">{{ selectedApp.tier?.name || 'Membership application' }}</p>
                        </div>
                        <button type="button" class="rounded-lg p-1.5 text-white/80 hover:bg-white/10 hover:text-white" @click="closeDetail">
                            <XMarkIcon class="size-5" />
                        </button>
                    </div>

                    <div class="space-y-6 p-5">
                        <section>
                            <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-400">Applicant</h3>
                            <dl class="mt-3 space-y-2.5 text-sm">
                                <div class="flex items-center gap-2 text-slate-700">
                                    <EnvelopeIcon class="size-4 text-institutional" />
                                    <a :href="`mailto:${selectedApp.applicant_email}`" class="hover:text-institutional">{{ selectedApp.applicant_email }}</a>
                                </div>
                                <div v-if="selectedApp.applicant_phone" class="flex items-center gap-2 text-slate-700">
                                    <PhoneIcon class="size-4 text-institutional" />
                                    <a :href="`tel:${selectedApp.applicant_phone}`">{{ selectedApp.applicant_phone }}</a>
                                </div>
                                <div v-if="selectedApp.organization" class="text-slate-700">
                                    <span class="text-slate-500">Organization / institution:</span>
                                    {{ selectedApp.organization }}
                                </div>
                                <div v-if="selectedApp.qualification" class="text-slate-700">
                                    <span class="text-slate-500">Qualification:</span>
                                    {{ selectedApp.qualification }}
                                </div>
                                <div v-if="selectedApp.years_experience != null" class="text-slate-700">
                                    <span class="text-slate-500">Years of experience:</span>
                                    {{ selectedApp.years_experience }}
                                </div>
                            </dl>
                        </section>

                        <section>
                            <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-400">Membership tier</h3>
                            <div class="mt-3 rounded-xl bg-slate-50 px-4 py-3">
                                <p class="font-medium text-slate-900">{{ selectedApp.tier?.name || '—' }}</p>
                                <p class="mt-1 text-sm text-slate-600">
                                    {{ formatMoney(selectedApp.tier?.annual_dues, selectedApp.tier?.currency) }}
                                    · {{ selectedApp.documents_count || 0 }}/{{ selectedApp.tier?.min_documents_required || 0 }} documents
                                </p>
                            </div>
                        </section>

                        <section>
                            <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-400">Uploaded documents</h3>
                            <ul v-if="selectedApp.documents?.length" class="mt-3 space-y-2">
                                <li
                                    v-for="doc in selectedApp.documents"
                                    :key="doc.id"
                                    class="flex items-center gap-3 rounded-xl border border-slate-100 px-3 py-3"
                                >
                                    <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-red-50 text-red-600">
                                        <DocumentTextIcon class="size-5" />
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-medium text-slate-900">{{ doc.filename || docLabel(doc.document_type) }}</p>
                                        <p class="text-xs capitalize text-slate-500">{{ docLabel(doc.document_type) }}</p>
                                    </div>
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1 rounded-lg bg-institutional/8 px-2.5 py-1.5 text-xs font-semibold text-institutional hover:bg-institutional hover:text-white"
                                        :disabled="downloadingId === doc.id"
                                        @click="downloadDocument(doc)"
                                    >
                                        <ArrowDownTrayIcon class="size-3.5" />
                                        {{ downloadingId === doc.id ? '…' : 'View' }}
                                    </button>
                                </li>
                            </ul>
                            <p v-else class="mt-3 rounded-xl border border-dashed border-slate-200 px-4 py-6 text-center text-sm text-slate-500">
                                No documents uploaded yet.
                            </p>
                        </section>

                        <div v-if="rejectOpen" class="rounded-xl border border-red-100 bg-red-50/60 p-4">
                            <label class="text-xs font-semibold uppercase tracking-wide text-red-700">Rejection reason</label>
                            <textarea
                                v-model="rejectReason"
                                rows="3"
                                class="input mt-2 !rounded-xl"
                                placeholder="Explain why this application is being rejected…"
                            />
                            <div class="mt-3 flex gap-2">
                                <button
                                    type="button"
                                    class="btn-danger !rounded-xl"
                                    :disabled="! rejectReason.trim() || actingUuid === selected.uuid"
                                    @click="confirmReject"
                                >
                                    <XCircleIcon class="mr-1.5 size-4" />
                                    Confirm reject
                                </button>
                                <button type="button" class="btn-secondary !rounded-xl" @click="rejectOpen = false">Cancel</button>
                            </div>
                        </div>

                        <div v-else class="flex flex-wrap gap-2 border-t border-slate-100 pt-4">
                            <button
                                type="button"
                                class="btn-primary !rounded-xl"
                                :disabled="actingUuid === selected.uuid"
                                @click="approve(selected)"
                            >
                                <CheckCircleIcon class="mr-1.5 size-4" />
                                Approve membership
                            </button>
                            <button
                                type="button"
                                class="btn-danger !rounded-xl"
                                :disabled="actingUuid === selected.uuid"
                                @click="rejectOpen = true"
                            >
                                Reject
                            </button>
                        </div>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</template>
