<script setup>
import { ref, onMounted } from 'vue';
import {
    ArrowDownTrayIcon,
    CheckCircleIcon,
    DocumentTextIcon,
    XCircleIcon,
} from '@heroicons/vue/24/outline';
import AdminAlert from '../../components/admin/AdminAlert.vue';
import AdminEmptyState from '../../components/admin/AdminEmptyState.vue';
import AdminPageIntro from '../../components/admin/AdminPageIntro.vue';
import AdminPanel from '../../components/admin/AdminPanel.vue';
import { useAuth } from '../../composables/useAuth';
import { extractApiError } from '../../utils/apiError';

const { getAdminClient } = useAuth();

const proofs = ref([]);
const loading = ref(true);
const actingUuid = ref('');
const downloadingUuid = ref('');
const message = ref('');
const error = ref('');
const pendingCount = ref(0);

const filter = ref({
    status: 'pending',
    purpose: '',
    search: '',
});

onMounted(load);

async function load() {
    loading.value = true;
    error.value = '';

    try {
        const params = Object.fromEntries(
            Object.entries(filter.value).filter(([, value]) => value),
        );
        const [listRes, countRes] = await Promise.all([
            getAdminClient().get('/payment-proofs', { params }),
            getAdminClient().get('/payment-proofs/pending-count'),
        ]);
        proofs.value = listRes.data.data || [];
        pendingCount.value = countRes.data.data?.pending ?? 0;
    } catch (e) {
        error.value = extractApiError(e, 'Unable to load payment receipts.');
    } finally {
        loading.value = false;
    }
}

function formatDate(value) {
    return value ? new Date(value).toLocaleString(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }) : '—';
}

function purposeLabel(purpose) {
    const map = {
        dues: 'Annual dues',
        donation: 'Donation',
        event_fee: 'Event registration',
        registration: 'Registration fee',
        renewal: 'Membership renewal',
        journal_submission_fee: 'Journal review fee',
        journal_publication_fee: 'Journal publication fee',
    };

    return map[purpose] || String(purpose || 'Payment').replaceAll('_', ' ');
}

function statusClass(status) {
    const map = {
        pending: 'bg-amber-100 text-amber-800',
        approved: 'bg-emerald-100 text-emerald-800',
        rejected: 'bg-red-100 text-red-800',
    };

    return map[status] || 'bg-slate-100 text-slate-700';
}

async function downloadReceipt(proof) {
    downloadingUuid.value = proof.uuid;
    error.value = '';

    try {
        const response = await getAdminClient().get(`/payment-proofs/${proof.uuid}/download`, {
            responseType: 'blob',
        });

        const disposition = response.headers['content-disposition'] || '';
        const match = disposition.match(/filename="?([^"]+)"?/i);
        const filename = match?.[1] || proof.receipt?.filename || `receipt-${proof.uuid}.pdf`;
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
        error.value = extractApiError(e, 'Unable to download receipt.');
    } finally {
        downloadingUuid.value = '';
    }
}

async function approve(proof) {
    if (!window.confirm(`Approve receipt from ${proof.user?.name || 'member'} for ${proof.currency} ${Number(proof.amount).toLocaleString()}?`)) {
        return;
    }

    actingUuid.value = proof.uuid;
    message.value = '';
    error.value = '';

    try {
        const { data } = await getAdminClient().post(`/payment-proofs/${proof.uuid}/approve`);
        message.value = data.message || 'Receipt approved and payment recorded.';
        await load();
    } catch (e) {
        error.value = extractApiError(e, 'Unable to approve receipt.');
    } finally {
        actingUuid.value = '';
    }
}

async function reject(proof) {
    const reason = window.prompt('Rejection reason (required):');
    if (!reason?.trim()) {
        return;
    }

    actingUuid.value = proof.uuid;
    message.value = '';
    error.value = '';

    try {
        const { data } = await getAdminClient().post(`/payment-proofs/${proof.uuid}/reject`, {
            reason: reason.trim(),
        });
        message.value = data.message || 'Receipt rejected.';
        await load();
    } catch (e) {
        error.value = extractApiError(e, 'Unable to reject receipt.');
    } finally {
        actingUuid.value = '';
    }
}
</script>

<template>
    <div>
        <AdminPageIntro description="Review bank-transfer receipts submitted by members for memberships, journal fees, and events. Approve to record the payment in the app." />

        <AdminAlert v-if="message" type="success">{{ message }}</AdminAlert>
        <AdminAlert v-if="error" type="error">{{ error }}</AdminAlert>

        <div class="mb-6 grid gap-4 sm:grid-cols-3">
            <div class="card-modern p-5">
                <p class="text-sm text-slate-500">Pending review</p>
                <p class="mt-1 text-3xl font-bold text-amber-700">{{ pendingCount }}</p>
            </div>
            <div class="card-modern p-5 sm:col-span-2">
                <p class="text-sm font-medium text-slate-700">Filter receipts</p>
                <div class="mt-3 grid gap-3 sm:grid-cols-3">
                    <select v-model="filter.status" class="input" @change="load">
                        <option value="">All statuses</option>
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                    </select>
                    <select v-model="filter.purpose" class="input" @change="load">
                        <option value="">All purposes</option>
                        <option value="dues">Dues</option>
                        <option value="registration">Registration</option>
                        <option value="journal_submission_fee">Journal review</option>
                        <option value="journal_publication_fee">Journal publication</option>
                        <option value="event_fee">Event fee</option>
                    </select>
                    <input
                        v-model="filter.search"
                        type="search"
                        class="input"
                        placeholder="Search name, email, ref…"
                        @keyup.enter="load"
                    />
                </div>
            </div>
        </div>

        <AdminPanel title="Submitted receipts" description="Open a receipt, verify the transfer, then approve or reject.">
            <div v-if="loading" class="flex justify-center py-16">
                <div class="size-10 animate-spin rounded-full border-2 border-institutional/20 border-t-institutional" />
            </div>

            <AdminEmptyState
                v-else-if="proofs.length === 0"
                title="No receipts to show"
                description="When members upload bank-transfer evidence, they will appear here for verification."
            >
                <template #icon>
                    <DocumentTextIcon class="size-6" />
                </template>
            </AdminEmptyState>

            <ul v-else class="space-y-4">
                <li
                    v-for="proof in proofs"
                    :key="proof.uuid"
                    class="rounded-2xl border border-slate-100 p-5"
                >
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-semibold text-slate-900">{{ proof.user?.name || 'Unknown member' }}</p>
                                <span class="badge capitalize" :class="statusClass(proof.status)">{{ proof.status }}</span>
                            </div>
                            <p class="mt-1 text-sm text-slate-600">{{ proof.user?.email }}</p>
                            <p class="mt-2 text-sm text-slate-800">
                                <span class="font-medium">{{ purposeLabel(proof.purpose) }}</span>
                                · {{ proof.currency }} {{ Number(proof.amount).toLocaleString() }}
                                <span v-if="proof.payer_reference"> · Bank ref {{ proof.payer_reference }}</span>
                            </p>
                            <p v-if="proof.member?.membership_number" class="mt-1 text-xs text-slate-500">
                                Membership {{ proof.member.membership_number }}
                            </p>
                            <p v-if="proof.member_note" class="mt-2 text-sm text-slate-600">Note: {{ proof.member_note }}</p>
                            <p v-if="proof.rejection_reason" class="mt-2 text-sm text-red-600">Rejected: {{ proof.rejection_reason }}</p>
                            <p class="mt-2 text-xs text-slate-400">
                                Submitted {{ formatDate(proof.created_at) }}
                                <span v-if="proof.reviewed_at"> · Reviewed {{ formatDate(proof.reviewed_at) }}</span>
                                <span v-if="proof.reviewer?.name"> by {{ proof.reviewer.name }}</span>
                            </p>
                            <p v-if="proof.receipt?.filename" class="mt-1 text-xs text-slate-500">
                                File: {{ proof.receipt.filename }}
                            </p>
                        </div>

                        <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row lg:flex-col">
                            <button
                                type="button"
                                class="btn-secondary inline-flex items-center justify-center gap-1.5 !rounded-xl"
                                :disabled="downloadingUuid === proof.uuid"
                                @click="downloadReceipt(proof)"
                            >
                                <ArrowDownTrayIcon class="size-4" />
                                {{ downloadingUuid === proof.uuid ? 'Downloading…' : 'View receipt' }}
                            </button>
                            <template v-if="proof.status === 'pending'">
                                <button
                                    type="button"
                                    class="btn-primary inline-flex items-center justify-center gap-1.5 !rounded-xl"
                                    :disabled="actingUuid === proof.uuid"
                                    @click="approve(proof)"
                                >
                                    <CheckCircleIcon class="size-4" />
                                    {{ actingUuid === proof.uuid ? 'Working…' : 'Approve' }}
                                </button>
                                <button
                                    type="button"
                                    class="btn-danger inline-flex items-center justify-center gap-1.5 !rounded-xl"
                                    :disabled="actingUuid === proof.uuid"
                                    @click="reject(proof)"
                                >
                                    <XCircleIcon class="size-4" />
                                    Reject
                                </button>
                            </template>
                        </div>
                    </div>
                </li>
            </ul>
        </AdminPanel>
    </div>
</template>
