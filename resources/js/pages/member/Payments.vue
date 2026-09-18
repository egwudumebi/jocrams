<script setup>
import { computed, ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import {
    ArrowDownTrayIcon,
    BanknotesIcon,
    CheckCircleIcon,
    ClockIcon,
    DocumentTextIcon,
    ReceiptRefundIcon,
} from '@heroicons/vue/24/outline';
import AdminAlert from '../../components/admin/AdminAlert.vue';
import AdminEmptyState from '../../components/admin/AdminEmptyState.vue';
import AdminPageIntro from '../../components/admin/AdminPageIntro.vue';
import AdminPanel from '../../components/admin/AdminPanel.vue';
import BankTransferCard from '../../components/payments/BankTransferCard.vue';
import PaymentProofForm from '../../components/payments/PaymentProofForm.vue';
import { useAuth } from '../../composables/useAuth';
import { useOrgInfo } from '../../composables/useOrgInfo';
import { extractApiError } from '../../utils/apiError';

const { getMemberClient, memberUser, fetchMemberProfile } = useAuth();
const { bank, fees, loadOrgInfo } = useOrgInfo();
const route = useRoute();

const payments = ref([]);
const proofs = ref([]);
const loading = ref(true);
const downloadingUuid = ref('');
const message = ref('');
const error = ref('');

const hasActiveMembership = computed(() => memberUser.value?.member?.status === 'active');
const renewal = computed(() => memberUser.value?.member?.renewal ?? null);
const canRenewMembership = computed(() => renewal.value?.can_renew ?? false);
const annualDues = computed(() => Number(memberUser.value?.member?.tier?.annual_dues || 0));
const currency = computed(() => memberUser.value?.member?.tier?.currency || 'NGN');
const defaultProofAmount = computed(() => {
    if (route.query.amount) {
        return Number(route.query.amount);
    }

    if (route.query.purpose === 'registration') {
        return Number(fees.value?.membership_registration || 20000);
    }

    if (canRenewMembership.value || hasActiveMembership.value) {
        return annualDues.value || Number(fees.value?.membership_registration || 20000);
    }

    return Number(fees.value?.membership_registration || 20000);
});

const defaultPurpose = computed(() => {
    if (typeof route.query.purpose === 'string' && route.query.purpose) {
        return route.query.purpose;
    }

    return hasActiveMembership.value ? 'dues' : 'registration';
});

const relatedUuid = computed(() => (typeof route.query.related === 'string' ? route.query.related : ''));

const successfulPayments = computed(() => payments.value.filter((payment) => payment.status === 'successful'));
const pendingProofs = computed(() => proofs.value.filter((proof) => proof.status === 'pending'));
const totalPaid = computed(() => successfulPayments.value.reduce((sum, payment) => sum + Number(payment.amount || 0), 0));

onMounted(async () => {
    await Promise.all([fetchMemberProfile(), loadOrgInfo()]);
    await Promise.all([loadPayments(), loadProofs()]);
});

async function loadPayments() {
    loading.value = true;
    error.value = '';

    try {
        const { data } = await getMemberClient().get('/payments');
        payments.value = data.data || [];
    } catch (e) {
        payments.value = [];
        // Inactive members cannot list gateway payments — proofs still work.
        if (e.response?.status !== 403) {
            error.value = extractApiError(e, 'Unable to load payment history.');
        }
    } finally {
        loading.value = false;
    }
}

async function loadProofs() {
    try {
        const { data } = await getMemberClient().get('/payment-proofs');
        proofs.value = data.data || [];
    } catch {
        proofs.value = [];
    }
}

function formatDate(value) {
    return value ? new Date(value).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' }) : '—';
}

function renewalNotice() {
    if (!renewal.value || canRenewMembership.value) {
        return '';
    }

    if (renewal.value.renewal_opens_at) {
        return `Renewal opens on ${formatDate(renewal.value.renewal_opens_at)}. Your membership remains active until ${formatDate(renewal.value.expires_at)}.`;
    }

    return 'Your membership is still active. Renewal is not required yet.';
}

function formatAmount(payment) {
    return `${payment.currency || 'NGN'} ${Number(payment.amount || 0).toLocaleString()}`;
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

function statusLabel(status) {
    const map = {
        successful: 'Successful',
        pending: 'Pending review',
        processing: 'Processing',
        failed: 'Failed',
        cancelled: 'Cancelled',
        expired: 'Expired',
        approved: 'Approved',
        rejected: 'Rejected',
    };

    return map[status] || status;
}

function statusClass(status) {
    const map = {
        successful: 'bg-emerald-100 text-emerald-800',
        approved: 'bg-emerald-100 text-emerald-800',
        pending: 'bg-amber-100 text-amber-800',
        processing: 'bg-amber-100 text-amber-800',
        failed: 'bg-red-100 text-red-800',
        rejected: 'bg-red-100 text-red-800',
        cancelled: 'bg-slate-100 text-slate-600',
        expired: 'bg-slate-100 text-slate-600',
    };

    return map[status] || 'bg-slate-100 text-slate-700';
}

async function downloadReceipt(payment) {
    downloadingUuid.value = payment.uuid;
    message.value = '';
    error.value = '';

    try {
        const response = await getMemberClient().get(`/payments/${payment.uuid}/receipt`, {
            responseType: 'blob',
            headers: { Accept: 'application/pdf' },
        });

        const blob = new Blob([response.data], { type: 'application/pdf' });
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `receipt-${payment.reference}.pdf`;
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(url);
        message.value = 'Receipt download started.';
    } catch (e) {
        error.value = extractApiError(e, 'Unable to download receipt.');
    } finally {
        downloadingUuid.value = '';
    }
}

async function onProofSubmitted() {
    message.value = 'Receipt submitted. An administrator will verify it in the app.';
    await loadProofs();
}
</script>

<template>
    <div>
        <AdminPageIntro description="Pay by UBA bank transfer, upload your receipt in the app, and wait for admin verification." />

        <AdminAlert v-if="message" type="success">{{ message }}</AdminAlert>
        <AdminAlert v-if="error" type="error">{{ error }}</AdminAlert>

        <div class="mb-6">
            <BankTransferCard
                :bank="bank"
                :fees="fees"
                show-fees
                title="Pay directly to UBA"
                description="Transfer the required fee, then submit your receipt below so an admin can verify it on the platform."
            />
        </div>

        <div class="mb-6 grid gap-6 xl:grid-cols-2">
            <PaymentProofForm
                :purpose="defaultPurpose"
                :amount="defaultProofAmount"
                :related-uuid="relatedUuid"
                @submitted="onProofSubmitted"
            />

            <AdminPanel title="Submitted receipts" description="Track verification status for receipts you uploaded.">
                <div v-if="proofs.length === 0" class="rounded-2xl border border-dashed border-slate-200 p-8 text-center text-sm text-slate-500">
                    No receipts submitted yet.
                </div>
                <ul v-else class="space-y-3">
                    <li
                        v-for="proof in proofs"
                        :key="proof.uuid"
                        class="rounded-2xl border border-slate-100 p-4"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="font-semibold text-slate-900">{{ purposeLabel(proof.purpose) }}</p>
                                <p class="mt-1 text-sm text-slate-600">
                                    {{ proof.currency }} {{ Number(proof.amount).toLocaleString() }}
                                    <span v-if="proof.payer_reference"> · Ref {{ proof.payer_reference }}</span>
                                </p>
                                <p class="mt-1 text-xs text-slate-400">{{ formatDate(proof.created_at) }}</p>
                                <p v-if="proof.rejection_reason" class="mt-2 text-sm text-red-600">{{ proof.rejection_reason }}</p>
                            </div>
                            <span class="badge capitalize" :class="statusClass(proof.status)">
                                {{ statusLabel(proof.status) }}
                            </span>
                        </div>
                    </li>
                </ul>
            </AdminPanel>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="card-modern p-5">
                <div class="flex items-center gap-4">
                    <span class="metric-icon !size-12 !rounded-xl">
                        <ReceiptRefundIcon class="size-6" />
                    </span>
                    <div>
                        <p class="text-sm text-slate-500">Verified payments</p>
                        <p class="text-2xl font-bold text-slate-900">{{ loading ? '—' : payments.length }}</p>
                    </div>
                </div>
            </div>
            <div class="card-modern p-5">
                <div class="flex items-center gap-4">
                    <span class="metric-icon !size-12 !rounded-xl">
                        <CheckCircleIcon class="size-6" />
                    </span>
                    <div>
                        <p class="text-sm text-slate-500">Successful</p>
                        <p class="text-2xl font-bold text-slate-900">{{ loading ? '—' : successfulPayments.length }}</p>
                    </div>
                </div>
            </div>
            <div class="card-modern p-5">
                <div class="flex items-center gap-4">
                    <span class="metric-icon !size-12 !rounded-xl">
                        <ClockIcon class="size-6" />
                    </span>
                    <div>
                        <p class="text-sm text-slate-500">Pending receipts</p>
                        <p class="text-2xl font-bold text-slate-900">{{ pendingProofs.length }}</p>
                    </div>
                </div>
            </div>
            <div class="card-modern p-5">
                <div class="flex items-center gap-4">
                    <span class="metric-icon !size-12 !rounded-xl">
                        <BanknotesIcon class="size-6" />
                    </span>
                    <div>
                        <p class="text-sm text-slate-500">Total paid</p>
                        <p class="text-2xl font-bold text-slate-900">
                            {{ loading ? '—' : `${currency} ${totalPaid.toLocaleString()}` }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-6 grid gap-6 xl:grid-cols-[0.9fr_1.1fr]">
            <AdminPanel
                v-if="hasActiveMembership"
                title="Membership billing"
                description="Your current tier and annual dues information."
            >
                <dl class="grid gap-3 text-sm">
                    <div class="rounded-xl border border-slate-100 bg-surface-muted/40 p-4">
                        <dt class="text-slate-500">Membership tier</dt>
                        <dd class="mt-1 font-semibold text-slate-900">{{ memberUser?.member?.tier?.name || '—' }}</dd>
                    </div>
                    <div class="rounded-xl border border-slate-100 bg-surface-muted/40 p-4">
                        <dt class="text-slate-500">Annual dues</dt>
                        <dd class="mt-1 font-semibold text-slate-900">
                            {{ currency }} {{ annualDues.toLocaleString() }}
                        </dd>
                    </div>
                    <div class="rounded-xl border border-slate-100 bg-surface-muted/40 p-4">
                        <dt class="text-slate-500">Membership expires</dt>
                        <dd class="mt-1 font-semibold text-slate-900">
                            {{ memberUser?.member?.expires_at ? formatDate(memberUser.member.expires_at) : '—' }}
                        </dd>
                    </div>
                </dl>

                <div class="mt-5 rounded-2xl border border-institutional/15 bg-gradient-to-br from-institutional/8 via-white to-white p-5">
                    <p class="text-sm font-semibold text-institutional">Need to renew?</p>
                    <p v-if="canRenewMembership" class="mt-1 text-sm text-slate-600">
                        Transfer your annual dues to the UBA account, then submit the receipt above for admin verification.
                    </p>
                    <p v-else class="mt-1 text-sm text-slate-600">
                        {{ renewalNotice() }}
                    </p>
                </div>
            </AdminPanel>

            <AdminPanel
                title="Verified payment history"
                description="Payments confirmed by an administrator after receipt review."
                :class="hasActiveMembership ? '' : 'xl:col-span-2'"
            >
                <div v-if="loading" class="flex justify-center py-16">
                    <div class="size-10 animate-spin rounded-full border-2 border-institutional/20 border-t-institutional" />
                </div>

                <AdminEmptyState
                    v-else-if="payments.length === 0"
                    title="No verified payments yet"
                    description="After an admin approves your uploaded receipt, the payment will appear here."
                >
                    <template #icon>
                        <DocumentTextIcon class="size-6" />
                    </template>
                </AdminEmptyState>

                <div v-else class="overflow-hidden rounded-2xl border border-slate-100">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-4 py-3 text-left font-medium text-slate-600">Date</th>
                                    <th class="px-4 py-3 text-left font-medium text-slate-600">Reference</th>
                                    <th class="px-4 py-3 text-left font-medium text-slate-600">Purpose</th>
                                    <th class="px-4 py-3 text-left font-medium text-slate-600">Amount</th>
                                    <th class="px-4 py-3 text-left font-medium text-slate-600">Status</th>
                                    <th class="px-4 py-3 text-right font-medium text-slate-600">Receipt</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                <tr v-for="payment in payments" :key="payment.uuid" class="hover:bg-surface-muted/40">
                                    <td class="px-4 py-3 text-slate-600">
                                        {{ formatDate(payment.paid_at || payment.created_at) }}
                                    </td>
                                    <td class="px-4 py-3 font-mono text-xs text-slate-700">{{ payment.reference }}</td>
                                    <td class="px-4 py-3 capitalize text-slate-800">{{ purposeLabel(payment.purpose) }}</td>
                                    <td class="px-4 py-3 font-medium text-slate-900">{{ formatAmount(payment) }}</td>
                                    <td class="px-4 py-3">
                                        <span class="badge capitalize" :class="statusClass(payment.status)">
                                            {{ statusLabel(payment.status) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <button
                                            v-if="payment.status === 'successful'"
                                            type="button"
                                            class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-semibold text-institutional hover:bg-institutional/10"
                                            :disabled="downloadingUuid === payment.uuid"
                                            @click="downloadReceipt(payment)"
                                        >
                                            <ArrowDownTrayIcon class="size-3.5" />
                                            {{ downloadingUuid === payment.uuid ? 'Preparing…' : 'Download' }}
                                        </button>
                                        <span v-else class="text-xs text-slate-400">—</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </AdminPanel>
        </div>
    </div>
</template>
