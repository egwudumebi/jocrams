<script setup>
import { computed, ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import {
    ArrowDownTrayIcon,
    BanknotesIcon,
    CheckCircleIcon,
    ClockIcon,
    CreditCardIcon,
    ReceiptRefundIcon,
} from '@heroicons/vue/24/outline';
import AdminAlert from '../../components/admin/AdminAlert.vue';
import AdminEmptyState from '../../components/admin/AdminEmptyState.vue';
import AdminPageIntro from '../../components/admin/AdminPageIntro.vue';
import AdminPanel from '../../components/admin/AdminPanel.vue';
import { publicApi } from '../../api/client';
import { useAuth } from '../../composables/useAuth';
import { extractApiError } from '../../utils/apiError';

const { getMemberClient, memberUser, memberToken, fetchMemberProfile } = useAuth();
const route = useRoute();
const router = useRouter();

const payments = ref([]);
const loading = ref(true);
const payingDues = ref(false);
const downloadingUuid = ref('');
const message = ref('');
const error = ref('');

const hasActiveMembership = computed(() => memberUser.value?.member?.status === 'active');
const renewal = computed(() => memberUser.value?.member?.renewal ?? null);
const canRenewMembership = computed(() => renewal.value?.can_renew ?? false);
const annualDues = computed(() => Number(memberUser.value?.member?.tier?.annual_dues || 0));
const currency = computed(() => memberUser.value?.member?.tier?.currency || 'NGN');

const successfulPayments = computed(() => payments.value.filter((payment) => payment.status === 'successful'));
const pendingPayments = computed(() => payments.value.filter((payment) => ['pending', 'processing'].includes(payment.status)));
const totalPaid = computed(() => successfulPayments.value.reduce((sum, payment) => sum + Number(payment.amount || 0), 0));

onMounted(async () => {
    await fetchMemberProfile();

    const reference = route.query.reference || route.query.trxref;
    if (typeof reference === 'string' && reference) {
        await verifyPayment(reference);
    } else {
        await load();
    }
});

async function verifyPayment(reference) {
    loading.value = true;
    message.value = '';
    error.value = '';

    try {
        const { data } = await publicApi(memberToken.value).get(`/payments/verify/${encodeURIComponent(reference)}`);
        message.value = data.message || 'Payment verified successfully.';

        if (data.redirect_to && data.redirect_to !== '/member/payments') {
            router.replace({ path: data.redirect_to, query: { reference } });
            return;
        }

        await fetchMemberProfile();
    } catch (e) {
        error.value = extractApiError(e, 'Unable to verify payment.');
    } finally {
        await load();
        router.replace({ path: route.path, query: {} });
    }
}

async function load() {
    loading.value = true;
    error.value = '';

    try {
        const { data } = await getMemberClient().get('/payments');
        payments.value = data.data || [];
    } catch (e) {
        payments.value = [];
        error.value = extractApiError(e, 'Unable to load payment history.');
    } finally {
        loading.value = false;
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
        event: 'Event registration',
        application: 'Application fee',
        renewal: 'Membership renewal',
    };

    return map[purpose] || String(purpose || 'Payment').replaceAll('_', ' ');
}

function statusLabel(status) {
    const map = {
        successful: 'Successful',
        pending: 'Pending',
        processing: 'Processing',
        failed: 'Failed',
        cancelled: 'Cancelled',
        expired: 'Expired',
    };

    return map[status] || status;
}

function statusClass(status) {
    const map = {
        successful: 'bg-emerald-100 text-emerald-800',
        pending: 'bg-amber-100 text-amber-800',
        processing: 'bg-amber-100 text-amber-800',
        failed: 'bg-red-100 text-red-800',
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

async function payDues() {
    payingDues.value = true;
    message.value = '';
    error.value = '';

    try {
        const { data } = await getMemberClient().post('/payments/dues', {
            gateway: 'paystack',
            idempotency_key: `dues-${Date.now()}`,
        });

        if (data.data?.authorization_url) {
            window.location.href = data.data.authorization_url;
            return;
        }

        error.value = 'Unable to start payment. Please try again.';
    } catch (e) {
        error.value = extractApiError(e, 'Unable to initiate dues payment.');
    } finally {
        payingDues.value = false;
    }
}
</script>

<template>
    <div>
        <AdminPageIntro description="Review your payment history, track transaction status, and pay annual membership dues securely.">
            <template #actions>
                <button
                    v-if="hasActiveMembership && canRenewMembership"
                    type="button"
                    class="btn-institutional inline-flex items-center gap-2"
                    :disabled="payingDues"
                    @click="payDues"
                >
                    <CreditCardIcon class="size-4" />
                    {{ payingDues ? 'Redirecting…' : 'Pay annual dues' }}
                </button>
            </template>
        </AdminPageIntro>

        <AdminAlert v-if="message" type="success">{{ message }}</AdminAlert>
        <AdminAlert v-if="error" type="error">{{ error }}</AdminAlert>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="card-modern p-5">
                <div class="flex items-center gap-4">
                    <span class="metric-icon !size-12 !rounded-xl">
                        <ReceiptRefundIcon class="size-6" />
                    </span>
                    <div>
                        <p class="text-sm text-slate-500">Total payments</p>
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
                        <p class="text-sm text-slate-500">Pending</p>
                        <p class="text-2xl font-bold text-slate-900">{{ loading ? '—' : pendingPayments.length }}</p>
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
                        Pay your annual dues securely online. You will be redirected to our payment gateway to complete the transaction.
                    </p>
                    <p v-else class="mt-1 text-sm text-slate-600">
                        {{ renewalNotice() }}
                    </p>
                    <button
                        type="button"
                        class="mt-4 inline-flex items-center gap-2"
                        :class="canRenewMembership ? 'btn-institutional' : 'btn-secondary cursor-not-allowed opacity-60'"
                        :disabled="payingDues || !canRenewMembership"
                        @click="payDues"
                    >
                        <CreditCardIcon class="size-4" />
                        {{ payingDues ? 'Redirecting…' : 'Pay annual dues' }}
                    </button>
                </div>
            </AdminPanel>

            <AdminPanel
                title="Payment history"
                description="All transactions linked to your member account."
                :class="hasActiveMembership ? '' : 'xl:col-span-2'"
            >
                <div v-if="loading" class="flex justify-center py-16">
                    <div class="size-10 animate-spin rounded-full border-2 border-institutional/20 border-t-institutional" />
                </div>

                <AdminEmptyState
                    v-else-if="payments.length === 0"
                    title="No payments recorded"
                    description="When you pay annual dues, event fees, or other membership charges, they will appear here with reference numbers and status."
                >
                    <template #icon>
                        <ReceiptRefundIcon class="size-6" />
                    </template>
                    <template v-if="hasActiveMembership && canRenewMembership" #action>
                        <button type="button" class="btn-institutional" :disabled="payingDues" @click="payDues">
                            Pay annual dues
                        </button>
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
