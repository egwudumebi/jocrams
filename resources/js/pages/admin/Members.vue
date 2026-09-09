<script setup>
import { computed, ref, onMounted } from 'vue';
import {
    EnvelopeIcon,
    PlusIcon,
    UserGroupIcon,
    UserIcon,
    UserPlusIcon,
    XMarkIcon,
} from '@heroicons/vue/24/outline';
import AdminAlert from '../../components/admin/AdminAlert.vue';
import AdminEmptyState from '../../components/admin/AdminEmptyState.vue';
import AdminPageIntro from '../../components/admin/AdminPageIntro.vue';
import AdminPanel from '../../components/admin/AdminPanel.vue';
import AdminToolbar from '../../components/admin/AdminToolbar.vue';
import AdminPhoneInput from '../../components/admin/AdminPhoneInput.vue';
import FormFieldLabel from '../../components/admin/FormFieldLabel.vue';
import { useAuth } from '../../composables/useAuth';
import { publicApi } from '../../api/client';

const { getAdminClient } = useAuth();
const members = ref([]);
const tiers = ref([]);
const search = ref('');
const loading = ref(true);
const saving = ref(false);
const showForm = ref(false);
const message = ref('');
const error = ref('');

const form = ref({
    name: '',
    email: '',
    phone: '',
    membership_tier_id: '',
});

onMounted(async () => {
    await Promise.all([load(), loadTiers()]);
});

async function loadTiers() {
    try {
        const { data } = await publicApi().get('/membership-tiers');
        tiers.value = data.data || [];
        if (tiers.value.length) {
            form.value.membership_tier_id = tiers.value[0].id;
        }
    } catch {
        tiers.value = [];
    }
}

async function load() {
    loading.value = true;
    try {
        const { data } = await getAdminClient().get('/members', { params: { search: search.value || undefined } });
        members.value = data.data || [];
    } finally {
        loading.value = false;
    }
}

function resetForm() {
    form.value = {
        name: '',
        email: '',
        phone: '',
        membership_tier_id: tiers.value[0]?.id || '',
    };
    error.value = '';
}

async function addMember() {
    error.value = '';
    message.value = '';
    saving.value = true;

    try {
        const { data } = await getAdminClient().post('/members', form.value);
        members.value.unshift(data.data);
        message.value = data.password_reset_sent
            ? 'Member added. Password reset email sent.'
            : 'Member added successfully.';
        showForm.value = false;
        resetForm();
    } catch (e) {
        error.value = e.response?.data?.message
            || Object.values(e.response?.data?.errors || {}).flat().join(' ')
            || 'Failed to add member.';
    } finally {
        saving.value = false;
    }
}

async function deactivateMember(member) {
    if (!confirm(`Deactivate ${member.user?.name}?`)) return;

    const { data } = await getAdminClient().post(`/members/${member.uuid}/deactivate`);
    const index = members.value.findIndex((item) => item.uuid === member.uuid);
    if (index >= 0) members.value[index] = data.data;
}

async function reactivateMember(member) {
    const { data } = await getAdminClient().post(`/members/${member.uuid}/reactivate`);
    const index = members.value.findIndex((item) => item.uuid === member.uuid);
    if (index >= 0) members.value[index] = data.data;
}

function isActive(member) {
    return member.status === 'active';
}

function formatDate(value) {
    return value ? new Date(value).toLocaleDateString() : '—';
}

function formatDues(amount) {
    return Number(amount || 0).toLocaleString();
}

const selectedTier = computed(() => tiers.value.find(
    (tier) => String(tier.id) === String(form.value.membership_tier_id),
));
</script>

<template>
    <div>
        <AdminPageIntro description="Search, add, export, and manage active association members." />

        <AdminToolbar>
            <template #search>
                <div class="flex gap-2">
                    <input
                        v-model="search"
                        type="search"
                        placeholder="Search by name, email, or member number..."
                        class="input-modern"
                        @keyup.enter="load"
                    />
                    <button type="button" class="btn-secondary shrink-0 !rounded-xl" @click="load">Search</button>
                </div>
            </template>
            <template #actions>
                <button type="button" class="btn-primary inline-flex w-full items-center justify-center gap-2 !rounded-xl sm:w-auto" @click="showForm = true; resetForm()">
                    <PlusIcon class="size-4" />
                    Add member
                </button>
                <a :href="`/api/v1/admin/exports/members?format=csv&search=${search}`" class="btn-secondary w-full !rounded-xl text-center sm:w-auto">Export CSV</a>
            </template>
        </AdminToolbar>

        <AdminAlert v-if="message" type="success">{{ message }}</AdminAlert>

        <AdminPanel v-if="showForm" class="mb-6">
            <template #header>
                <div class="flex items-start gap-3">
                    <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-institutional">
                        <UserPlusIcon class="size-6" aria-hidden="true" />
                    </span>
                    <div>
                        <h2 class="admin-panel-title">Add member manually</h2>
                        <p class="admin-panel-description">
                            Create a member account, assign a tier, and optionally send a password setup email.
                        </p>
                    </div>
                </div>
            </template>
            <template #actions>
                <button
                    type="button"
                    class="btn-secondary inline-flex w-full items-center justify-center gap-2 !rounded-xl sm:w-auto"
                    @click="showForm = false; error = ''"
                >
                    <XMarkIcon class="size-4" />
                    Close
                </button>
            </template>

            <form class="space-y-6" @submit.prevent="addMember">
                <AdminAlert v-if="error" type="error">{{ error }}</AdminAlert>

                <section class="form-section">
                    <h3 class="form-section-title">Member details</h3>
                    <div class="admin-form-grid">
                        <div class="form-field sm:col-span-2">
                            <FormFieldLabel
                                label="Full name"
                                required
                                help="The member's legal or preferred name as it should appear on records and credentials."
                            />
                            <div class="relative">
                                <UserIcon class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                                <input
                                    v-model="form.name"
                                    type="text"
                                    required
                                    class="input-modern !pl-10"
                                    placeholder="Ada Okonkwo"
                                />
                            </div>
                        </div>
                        <div class="form-field">
                            <FormFieldLabel
                                label="Email"
                                required
                                help="Used for login, notifications, and password reset messages."
                            />
                            <div class="relative">
                                <EnvelopeIcon class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                                <input
                                    v-model="form.email"
                                    type="email"
                                    required
                                    class="input-modern !pl-10"
                                    placeholder="member@example.com"
                                />
                            </div>
                        </div>
                        <div class="form-field">
                            <FormFieldLabel
                                label="Phone"
                                help="Optional contact number for reminders and support follow-ups."
                            />
                            <AdminPhoneInput v-model="form.phone" default-country="NG" />
                        </div>
                    </div>
                </section>

                <section class="form-section">
                    <h3 class="form-section-title">Membership tier</h3>
                    <div class="admin-form-grid">
                        <div class="form-field sm:col-span-2">
                            <FormFieldLabel
                                label="Tier"
                                required
                                help="Determines annual dues, benefits, and which resources the member can access."
                            />
                            <select v-model="form.membership_tier_id" required class="select-modern">
                                <option v-for="tier in tiers" :key="tier.uuid" :value="tier.id">
                                    {{ tier.name }} · NGN {{ formatDues(tier.annual_dues) }}/year
                                </option>
                            </select>
                        </div>
                    </div>

                    <div
                        v-if="selectedTier"
                        class="mt-4 rounded-xl border border-brand-100 bg-brand-50/50 p-4"
                    >
                        <p class="font-medium text-slate-900">{{ selectedTier.name }}</p>
                        <p v-if="selectedTier.description" class="mt-1 text-sm text-slate-600">
                            {{ selectedTier.description }}
                        </p>
                        <p class="mt-2 text-sm font-semibold text-institutional">
                            NGN {{ formatDues(selectedTier.annual_dues) }} / year
                        </p>
                    </div>
                </section>

                <div class="flex flex-col-reverse gap-2 border-t border-slate-100 pt-4 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-xs text-slate-500">
                        A welcome email with password setup instructions may be sent automatically.
                    </p>
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <button
                            type="button"
                            class="btn-secondary w-full !rounded-xl sm:w-auto"
                            :disabled="saving"
                            @click="showForm = false; error = ''"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            class="btn-primary inline-flex w-full items-center justify-center gap-2 !rounded-xl sm:w-auto"
                            :disabled="saving"
                        >
                            <UserPlusIcon class="size-4" />
                            {{ saving ? 'Creating…' : 'Create member' }}
                        </button>
                    </div>
                </div>
            </form>
        </AdminPanel>

        <div v-if="loading" class="empty-state bg-white">Loading members...</div>

        <AdminEmptyState
            v-else-if="!members.length && !showForm"
            title="No members found"
            description="Add a member manually or wait for approved applications."
        >
            <template #icon><UserGroupIcon class="size-6" /></template>
            <template #action>
                <button type="button" class="btn-primary !rounded-xl" @click="showForm = true; resetForm()">Add member</button>
            </template>
        </AdminEmptyState>

        <template v-else>
            <div class="divide-y divide-slate-100 md:hidden admin-panel">
                <article v-for="m in members" :key="m.uuid" class="space-y-3 p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-medium text-slate-900">{{ m.user?.name }}</p>
                            <p class="mt-1 text-sm text-slate-600">{{ m.user?.email }}</p>
                        </div>
                        <span class="badge shrink-0 capitalize">{{ m.status }}</span>
                    </div>
                    <dl class="grid grid-cols-2 gap-2 text-xs text-slate-500">
                        <div><dt class="uppercase tracking-wide">Member #</dt><dd class="mt-0.5 font-mono text-slate-700">{{ m.membership_number || '—' }}</dd></div>
                        <div><dt class="uppercase tracking-wide">Tier</dt><dd class="mt-0.5 text-slate-700">{{ m.tier?.name || '—' }}</dd></div>
                        <div class="col-span-2"><dt class="uppercase tracking-wide">Expires</dt><dd class="mt-0.5 text-slate-700">{{ formatDate(m.expires_at) }}</dd></div>
                    </dl>
                    <div>
                        <button
                            v-if="isActive(m)"
                            type="button"
                            class="text-sm font-medium text-red-600 hover:underline"
                            @click="deactivateMember(m)"
                        >
                            Deactivate
                        </button>
                        <button
                            v-else-if="m.status === 'suspended'"
                            type="button"
                            class="text-sm font-medium text-green-700 hover:underline"
                            @click="reactivateMember(m)"
                        >
                            Reactivate
                        </button>
                    </div>
                </article>
            </div>

            <div class="hidden overflow-x-auto md:block admin-panel">
                <table class="min-w-[720px] w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-slate-600">Member #</th>
                            <th class="px-4 py-3 text-left font-medium text-slate-600">Name</th>
                            <th class="px-4 py-3 text-left font-medium text-slate-600">Email</th>
                            <th class="px-4 py-3 text-left font-medium text-slate-600">Tier</th>
                            <th class="px-4 py-3 text-left font-medium text-slate-600">Status</th>
                            <th class="px-4 py-3 text-left font-medium text-slate-600">Expires</th>
                            <th class="px-4 py-3 text-left font-medium text-slate-600">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="m in members" :key="m.uuid" class="hover:bg-slate-50/60">
                            <td class="px-4 py-3 font-mono text-xs">{{ m.membership_number }}</td>
                            <td class="px-4 py-3 font-medium">{{ m.user?.name }}</td>
                            <td class="px-4 py-3">{{ m.user?.email }}</td>
                            <td class="px-4 py-3">{{ m.tier?.name }}</td>
                            <td class="px-4 py-3"><span class="badge bg-slate-100 capitalize">{{ m.status }}</span></td>
                            <td class="px-4 py-3">{{ formatDate(m.expires_at) }}</td>
                            <td class="px-4 py-3">
                                <button
                                    v-if="isActive(m)"
                                    type="button"
                                    class="text-sm font-medium text-red-600 hover:underline"
                                    @click="deactivateMember(m)"
                                >
                                    Deactivate
                                </button>
                                <button
                                    v-else-if="m.status === 'suspended'"
                                    type="button"
                                    class="text-sm font-medium text-green-700 hover:underline"
                                    @click="reactivateMember(m)"
                                >
                                    Reactivate
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>
    </div>
</template>
