<script setup>
import { computed, ref, onMounted } from 'vue';
import { PlusIcon } from '@heroicons/vue/24/outline';
import AdminPageIntro from '../../components/admin/AdminPageIntro.vue';
import { useAuth } from '../../composables/useAuth';

const { getAdminClient } = useAuth();
const payments = ref([]);
const stats = ref(null);
const members = ref([]);
const loading = ref(true);
const saving = ref(false);
const showForm = ref(false);
const message = ref('');
const error = ref('');

const filter = ref({
    status: '',
    purpose: '',
    gateway: '',
    search: '',
    from: '',
    to: '',
});

const form = ref({
    member_uuid: '',
    amount: '',
    purpose: 'dues',
    payment_method: 'bank_transfer',
    transaction_reference: '',
    description: '',
});

const memberSearch = ref('');

const exportUrl = computed(() => {
    const params = new URLSearchParams({ format: 'csv' });
    Object.entries(filter.value).forEach(([key, value]) => {
        if (value) params.set(key, value);
    });
    return `/api/v1/admin/exports/payments?${params.toString()}`;
});

onMounted(async () => {
    await Promise.all([load(), loadStats()]);
});

async function loadStats() {
    const { data } = await getAdminClient().get('/payments/stats');
    stats.value = data.data;
}

async function load() {
    loading.value = true;
    try {
        const params = Object.fromEntries(
            Object.entries(filter.value).filter(([, value]) => value),
        );
        const { data } = await getAdminClient().get('/payments', { params });
        payments.value = data.data || [];
    } finally {
        loading.value = false;
    }
}

async function searchMembers() {
    if (!memberSearch.value.trim()) {
        members.value = [];
        return;
    }

    const { data } = await getAdminClient().get('/members', {
        params: { search: memberSearch.value, status: 'active' },
    });
    members.value = data.data || [];
}

function selectMember(member) {
    form.value.member_uuid = member.uuid;
    memberSearch.value = `${member.user?.name} (${member.membership_number})`;
    members.value = [];
}

function resetForm() {
    form.value = {
        member_uuid: '',
        amount: '',
        purpose: 'dues',
        payment_method: 'bank_transfer',
        transaction_reference: '',
        description: '',
    };
    memberSearch.value = '';
    error.value = '';
}

async function recordPayment() {
    error.value = '';
    message.value = '';
    saving.value = true;

    try {
        await getAdminClient().post('/payments', form.value);
        message.value = 'Payment recorded successfully.';
        showForm.value = false;
        resetForm();
        await Promise.all([load(), loadStats()]);
    } catch (e) {
        error.value = e.response?.data?.message
            || Object.values(e.response?.data?.errors || {}).flat().join(' ')
            || 'Failed to record payment.';
    } finally {
        saving.value = false;
    }
}

async function markSuccessful(payment) {
    await getAdminClient().post(`/payments/${payment.uuid}/mark-successful`, {
        reason: 'Manual confirmation',
    });
    await Promise.all([load(), loadStats()]);
}

async function markFailed(payment) {
    const reason = window.prompt('Reason for marking as failed:');
    if (!reason) return;

    await getAdminClient().post(`/payments/${payment.uuid}/mark-failed`, { reason });
    await Promise.all([load(), loadStats()]);
}

function formatAmount(amount) {
    return Number(amount).toLocaleString();
}
</script>

<template>
    <div>
        <AdminPageIntro
            title="Payment ledger"
            description="Review gateway transactions, record offline payments, and export the ledger."
        />

        <div v-if="stats" class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="card p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">Total revenue</p>
                <p class="mt-1 text-2xl font-bold text-slate-900">₦{{ formatAmount(stats.total_revenue) }}</p>
            </div>
            <div class="card p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">Successful</p>
                <p class="mt-1 text-2xl font-bold text-green-700">{{ stats.successful_count }}</p>
            </div>
            <div class="card p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">Pending</p>
                <p class="mt-1 text-2xl font-bold text-amber-600">{{ stats.pending_count }}</p>
            </div>
            <div class="card p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">Failed</p>
                <p class="mt-1 text-2xl font-bold text-red-600">{{ stats.failed_count }}</p>
            </div>
        </div>

        <div class="mt-6 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div class="flex flex-wrap gap-2">
                <select v-model="filter.status" class="input max-w-[140px]" @change="load">
                    <option value="">All statuses</option>
                    <option value="pending">Pending</option>
                    <option value="processing">Processing</option>
                    <option value="successful">Successful</option>
                    <option value="failed">Failed</option>
                </select>
                <select v-model="filter.purpose" class="input max-w-[140px]" @change="load">
                    <option value="">All purposes</option>
                    <option value="dues">Dues</option>
                    <option value="event_fee">Event fee</option>
                    <option value="donation">Donation</option>
                </select>
                <select v-model="filter.gateway" class="input max-w-[140px]" @change="load">
                    <option value="">All gateways</option>
                    <option value="paystack">Paystack</option>
                    <option value="flutterwave">Flutterwave</option>
                    <option value="manual">Manual</option>
                </select>
                <input v-model="filter.search" type="search" placeholder="Reference or email" class="input max-w-xs" @keyup.enter="load" />
                <input v-model="filter.from" type="date" class="input max-w-[150px]" @change="load" />
                <input v-model="filter.to" type="date" class="input max-w-[150px]" @change="load" />
                <button type="button" class="btn-secondary" @click="load">Apply</button>
            </div>
            <div class="flex gap-2">
                <button type="button" class="btn-primary inline-flex items-center gap-2" @click="showForm = true; resetForm()">
                    <PlusIcon class="size-4" />
                    Record payment
                </button>
                <a :href="exportUrl" class="btn-secondary">Export CSV</a>
            </div>
        </div>

        <p v-if="message" class="mt-4 text-sm text-green-600">{{ message }}</p>

        <div v-if="showForm" class="mt-6 card p-6">
            <h2 class="text-lg font-semibold text-slate-900">Record manual payment</h2>
            <p v-if="error" class="mt-3 text-sm text-red-600">{{ error }}</p>
            <form class="mt-4 grid gap-4 sm:grid-cols-2" @submit.prevent="recordPayment">
                <div class="relative sm:col-span-2">
                    <label class="label-caps">Member</label>
                    <input
                        v-model="memberSearch"
                        type="search"
                        placeholder="Search by name, email, or member number"
                        class="input mt-1 w-full"
                        @input="searchMembers"
                    />
                    <ul v-if="members.length" class="absolute z-10 mt-1 max-h-48 w-full overflow-auto rounded-xl border border-slate-200 bg-white shadow-lg">
                        <li
                            v-for="member in members"
                            :key="member.uuid"
                            class="cursor-pointer px-4 py-2 text-sm hover:bg-slate-50"
                            @click="selectMember(member)"
                        >
                            {{ member.user?.name }} — {{ member.membership_number }}
                        </li>
                    </ul>
                </div>
                <div>
                    <label class="label-caps">Amount (NGN)</label>
                    <input v-model="form.amount" type="number" min="0.01" step="0.01" required class="input mt-1 w-full" />
                </div>
                <div>
                    <label class="label-caps">Purpose</label>
                    <select v-model="form.purpose" required class="input mt-1 w-full">
                        <option value="dues">Dues</option>
                        <option value="event_fee">Event fee</option>
                        <option value="donation">Donation</option>
                        <option value="registration">Registration</option>
                    </select>
                </div>
                <div>
                    <label class="label-caps">Payment method</label>
                    <select v-model="form.payment_method" required class="input mt-1 w-full">
                        <option value="bank_transfer">Bank transfer</option>
                        <option value="cash">Cash</option>
                        <option value="pos">POS</option>
                        <option value="cheque">Cheque</option>
                    </select>
                </div>
                <div>
                    <label class="label-caps">Transaction reference</label>
                    <input v-model="form.transaction_reference" type="text" required class="input mt-1 w-full" />
                </div>
                <div class="sm:col-span-2">
                    <label class="label-caps">Description</label>
                    <input v-model="form.description" type="text" class="input mt-1 w-full" />
                </div>
                <div class="sm:col-span-2 flex gap-3">
                    <button type="submit" class="btn-primary" :disabled="saving || !form.member_uuid">
                        {{ saving ? 'Saving...' : 'Record payment' }}
                    </button>
                    <button type="button" class="btn-secondary" @click="showForm = false">Cancel</button>
                </div>
            </form>
        </div>

        <div class="mt-6 card overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-slate-600">Reference</th>
                        <th class="px-4 py-3 text-left font-medium text-slate-600">Member</th>
                        <th class="px-4 py-3 text-left font-medium text-slate-600">Purpose</th>
                        <th class="px-4 py-3 text-left font-medium text-slate-600">Gateway</th>
                        <th class="px-4 py-3 text-left font-medium text-slate-600">Amount</th>
                        <th class="px-4 py-3 text-left font-medium text-slate-600">Status</th>
                        <th class="px-4 py-3 text-left font-medium text-slate-600">Paid</th>
                        <th class="px-4 py-3 text-left font-medium text-slate-600">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-if="loading">
                        <td colspan="8" class="px-4 py-8 text-center text-slate-500">Loading...</td>
                    </tr>
                    <tr v-for="p in payments" :key="p.uuid">
                        <td class="px-4 py-3 font-mono text-xs">{{ p.reference }}</td>
                        <td class="px-4 py-3">{{ p.user?.name || p.user?.email || '—' }}</td>
                        <td class="px-4 py-3 capitalize">{{ p.purpose?.replace('_', ' ') }}</td>
                        <td class="px-4 py-3 capitalize">{{ p.gateway }}</td>
                        <td class="px-4 py-3">₦{{ formatAmount(p.amount) }}</td>
                        <td class="px-4 py-3"><span class="badge bg-slate-100 capitalize">{{ p.status }}</span></td>
                        <td class="px-4 py-3">{{ p.paid_at ? new Date(p.paid_at).toLocaleDateString() : '—' }}</td>
                        <td class="px-4 py-3 space-x-2">
                            <button
                                v-if="p.status === 'pending' || p.status === 'processing'"
                                type="button"
                                class="text-xs font-medium text-green-700 hover:underline"
                                @click="markSuccessful(p)"
                            >
                                Mark paid
                            </button>
                            <button
                                v-if="p.status === 'pending' || p.status === 'processing'"
                                type="button"
                                class="text-xs font-medium text-red-600 hover:underline"
                                @click="markFailed(p)"
                            >
                                Mark failed
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
