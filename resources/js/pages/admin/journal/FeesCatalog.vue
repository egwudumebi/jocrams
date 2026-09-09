<script setup>
import { computed, ref, onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import {
    BanknotesIcon,
    PencilSquareIcon,
    PlusIcon,
    TrashIcon,
} from '@heroicons/vue/24/outline';
import AdminAlert from '../../../components/admin/AdminAlert.vue';
import AdminEmptyState from '../../../components/admin/AdminEmptyState.vue';
import AdminPageIntro from '../../../components/admin/AdminPageIntro.vue';
import AdminPanel from '../../../components/admin/AdminPanel.vue';
import { useAuth } from '../../../composables/useAuth';
import { extractApiError } from '../../../utils/apiError';

const { getJournalClient, fetchAdminProfile } = useAuth();

const fees = ref([]);
const meta = ref({ categories: [], currencies: ['NGN'] });
const loading = ref(true);
const saving = ref(false);
const message = ref('');
const error = ref('');
const editingUuid = ref(null);
const filterCategory = ref('');

const emptyForm = () => ({
    name: '',
    code: '',
    category: 'journal',
    fee_type: 'submission',
    amount: '',
    currency: 'NGN',
    description: '',
    sort_order: 0,
    is_active: true,
});

const form = ref(emptyForm());

const feeTypeOptions = computed(() => {
    const category = meta.value.categories.find((item) => item.value === form.value.category);

    return category?.fee_types || [];
});

const categoryLabel = (value) => meta.value.categories.find((item) => item.value === value)?.label || value;

const feeTypeLabel = (category, value) => {
    const match = meta.value.categories
        .find((item) => item.value === category)
        ?.fee_types?.find((type) => type.value === value);

    return match?.label || value;
};

function formatMoney(amount, currency = 'NGN') {
    return new Intl.NumberFormat('en-NG', { style: 'currency', currency }).format(amount || 0);
}

onMounted(async () => {
    await fetchAdminProfile();
    await Promise.all([loadMeta(), load()]);
});

async function loadMeta() {
    try {
        const { data } = await getJournalClient().get('/admin/fee-catalog/meta');
        meta.value = data.data || meta.value;
    } catch {
        meta.value = { categories: [], currencies: ['NGN'] };
    }
}

async function load() {
    loading.value = true;
    error.value = '';

    try {
        const params = filterCategory.value ? { category: filterCategory.value } : {};
        const { data } = await getJournalClient().get('/admin/fee-catalog', { params });
        fees.value = data.data || [];
    } catch (e) {
        error.value = extractApiError(e, 'Unable to load fees catalog.');
    } finally {
        loading.value = false;
    }
}

function onCategoryChange() {
    const types = feeTypeOptions.value;
    if (types.length && !types.some((type) => type.value === form.value.fee_type)) {
        form.value.fee_type = types[0].value;
    }
}

function resetForm() {
    form.value = emptyForm();
    editingUuid.value = null;
}

function startEdit(fee) {
    editingUuid.value = fee.uuid;
    form.value = {
        name: fee.name,
        code: fee.code || '',
        category: fee.category,
        fee_type: fee.fee_type,
        amount: fee.amount,
        currency: fee.currency || 'NGN',
        description: fee.description || '',
        sort_order: fee.sort_order ?? 0,
        is_active: fee.is_active,
    };
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

async function save() {
    saving.value = true;
    message.value = '';
    error.value = '';

    const payload = {
        ...form.value,
        amount: Number(form.value.amount) || 0,
        sort_order: Number(form.value.sort_order) || 0,
        code: form.value.code || null,
    };

    try {
        if (editingUuid.value) {
            await getJournalClient().put(`/admin/fee-catalog/${editingUuid.value}`, payload);
            message.value = 'Fee updated.';
        } else {
            await getJournalClient().post('/admin/fee-catalog', payload);
            message.value = 'Fee added to catalog.';
        }

        resetForm();
        await load();
    } catch (e) {
        error.value = extractApiError(e, 'Unable to save fee.');
    } finally {
        saving.value = false;
    }
}

async function toggleActive(fee) {
    try {
        await getJournalClient().put(`/admin/fee-catalog/${fee.uuid}`, {
            is_active: !fee.is_active,
        });
        await load();
    } catch (e) {
        error.value = extractApiError(e, 'Unable to update fee status.');
    }
}

async function removeFee(fee) {
    if (!window.confirm(`Remove "${fee.name}" from the catalog?`)) {
        return;
    }

    try {
        await getJournalClient().delete(`/admin/fee-catalog/${fee.uuid}`);
        message.value = 'Fee removed.';
        if (editingUuid.value === fee.uuid) {
            resetForm();
        }
        await load();
    } catch (e) {
        error.value = extractApiError(e, 'Unable to remove fee.');
    }
}
</script>

<template>
    <div>
        <AdminPageIntro description="Create and manage membership, journal, and event fees. Active fees can be applied when configuring calls for papers and other workflows." />

        <div class="mb-4 flex flex-wrap gap-2">
            <RouterLink to="/admin/journal" class="btn-secondary !rounded-xl text-sm">← Submissions</RouterLink>
            <RouterLink to="/admin/journal/calls-for-papers" class="btn-secondary !rounded-xl text-sm">Calls for papers</RouterLink>
        </div>

        <AdminAlert v-if="message" type="success">{{ message }}</AdminAlert>
        <AdminAlert v-if="error" type="error">{{ error }}</AdminAlert>

        <AdminPanel
            :title="editingUuid ? 'Edit fee' : 'Add fee'"
            :description="editingUuid ? 'Update this catalog entry.' : 'Define a reusable fee for membership, journal submissions, publication, or events.'"
            class="mb-6"
        >
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="label">Name</label>
                    <input v-model="form.name" class="input !rounded-xl" placeholder="e.g. Standard Submission Fee" required />
                </div>
                <div>
                    <label class="label">Category</label>
                    <select v-model="form.category" class="input !rounded-xl" @change="onCategoryChange">
                        <option v-for="category in meta.categories" :key="category.value" :value="category.value">
                            {{ category.label }}
                        </option>
                    </select>
                </div>
                <div>
                    <label class="label">Fee type</label>
                    <select v-model="form.fee_type" class="input !rounded-xl">
                        <option v-for="type in feeTypeOptions" :key="type.value" :value="type.value">
                            {{ type.label }}
                        </option>
                    </select>
                </div>
                <div>
                    <label class="label">Amount</label>
                    <input v-model="form.amount" type="number" min="0" step="0.01" class="input !rounded-xl" required />
                </div>
                <div>
                    <label class="label">Currency</label>
                    <select v-model="form.currency" class="input !rounded-xl">
                        <option v-for="currency in meta.currencies" :key="currency" :value="currency">
                            {{ currency }}
                        </option>
                    </select>
                </div>
                <div>
                    <label class="label">Code (optional)</label>
                    <input v-model="form.code" class="input !rounded-xl" placeholder="journal_submission_standard" />
                </div>
                <div>
                    <label class="label">Sort order</label>
                    <input v-model="form.sort_order" type="number" min="0" class="input !rounded-xl" />
                </div>
                <div class="sm:col-span-2">
                    <label class="label">Description (optional)</label>
                    <textarea v-model="form.description" rows="2" class="input !rounded-xl" placeholder="When this fee applies…" />
                </div>
            </div>

            <div class="mt-4 flex flex-wrap gap-2">
                <button class="btn-primary !rounded-xl" type="button" :disabled="saving || !form.name" @click="save">
                    <PlusIcon v-if="!editingUuid" class="mr-1 inline size-4" />
                    <PencilSquareIcon v-else class="mr-1 inline size-4" />
                    {{ saving ? 'Saving…' : editingUuid ? 'Update fee' : 'Add fee' }}
                </button>
                <button v-if="editingUuid" class="btn-secondary !rounded-xl" type="button" @click="resetForm">Cancel edit</button>
            </div>
        </AdminPanel>

        <AdminPanel title="Fees catalog">
            <div class="mb-4 flex flex-wrap items-center gap-3">
                <label class="text-sm font-medium text-slate-700">Filter by category</label>
                <select v-model="filterCategory" class="input !w-auto !rounded-xl text-sm" @change="load">
                    <option value="">All categories</option>
                    <option v-for="category in meta.categories" :key="category.value" :value="category.value">
                        {{ category.label }}
                    </option>
                </select>
            </div>

            <AdminEmptyState
                v-if="!loading && !fees.length"
                title="No fees in catalog"
                description="Add membership dues, journal submission fees, publication fees, or event registration fees above."
            >
                <template #icon><BanknotesIcon class="size-6" /></template>
            </AdminEmptyState>

            <ul v-else class="-mx-4 divide-y divide-slate-100 sm:-mx-5">
                <li v-for="fee in fees" :key="fee.uuid" class="admin-list-item items-start">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="font-medium text-slate-900">{{ fee.name }}</p>
                            <span
                                class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset"
                                :class="fee.is_active ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/20' : 'bg-slate-100 text-slate-500 ring-slate-500/10'"
                            >
                                {{ fee.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>
                        <p class="mt-1 text-lg font-semibold text-slate-800">{{ formatMoney(fee.amount, fee.currency) }}</p>
                        <div class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-xs text-slate-500">
                            <span>{{ categoryLabel(fee.category) }}</span>
                            <span>·</span>
                            <span>{{ feeTypeLabel(fee.category, fee.fee_type) }}</span>
                            <span v-if="fee.code">· {{ fee.code }}</span>
                        </div>
                        <p v-if="fee.description" class="mt-2 text-sm text-slate-500">{{ fee.description }}</p>
                    </div>
                    <div class="flex shrink-0 flex-wrap gap-2">
                        <button class="btn-secondary !rounded-xl text-sm" type="button" @click="startEdit(fee)">
                            <PencilSquareIcon class="inline size-4" />
                        </button>
                        <button class="btn-secondary !rounded-xl text-sm" type="button" @click="toggleActive(fee)">
                            {{ fee.is_active ? 'Deactivate' : 'Activate' }}
                        </button>
                        <button class="btn-secondary !rounded-xl text-sm text-red-600" type="button" @click="removeFee(fee)">
                            <TrashIcon class="inline size-4" />
                        </button>
                    </div>
                </li>
            </ul>
        </AdminPanel>
    </div>
</template>
