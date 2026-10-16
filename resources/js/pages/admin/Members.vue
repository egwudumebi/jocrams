<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import {
    ArrowLeftIcon,
    ArrowRightIcon,
    CalendarDaysIcon,
    CheckCircleIcon,
    EnvelopeIcon,
    IdentificationIcon,
    MagnifyingGlassIcon,
    PhoneIcon,
    PlusIcon,
    SparklesIcon,
    UserGroupIcon,
    UserIcon,
    UserPlusIcon,
    XMarkIcon,
} from '@heroicons/vue/24/outline';
import AdminAlert from '../../components/admin/AdminAlert.vue';
import AdminEmptyState from '../../components/admin/AdminEmptyState.vue';
import AdminPageIntro from '../../components/admin/AdminPageIntro.vue';
import AdminToolbar from '../../components/admin/AdminToolbar.vue';
import AdminPhoneInput from '../../components/admin/AdminPhoneInput.vue';
import FormFieldLabel from '../../components/admin/FormFieldLabel.vue';
import { useAuth } from '../../composables/useAuth';
import { publicApi } from '../../api/client';
import { extractApiError } from '../../utils/apiError';
import { downloadAdminExport, messageFromBlobError } from '../../utils/download';
import { confirmAction, notifyError, notifySuccess } from '../../utils/swal';

const { getAdminClient } = useAuth();

const members = ref([]);
const tiers = ref([]);
const search = ref('');
const statusFilter = ref('all');
const loading = ref(true);
const saving = ref(false);
const exporting = ref(false);
const drawerOpen = ref(false);
const message = ref('');
const error = ref('');
const fieldErrors = ref({});
const page = ref(1);
const meta = ref({
    current_page: 1,
    last_page: 1,
    per_page: 20,
    total: 0,
    from: null,
    to: null,
});
let searchTimer = null;

const form = ref(emptyForm());

const orgOptions = [
    {
        id: 'sicama',
        label: 'SICAMA',
        description: 'Association member with the official SICAMA ID card.',
        badge: 'Teal card',
    },
];

const cardRoleOptions = ['Member', 'EXCO Member', 'Chairman, BOT', 'President', 'Vice President'];

onMounted(async () => {
    await Promise.all([load(), loadTiers()]);
    document.addEventListener('keydown', onKeydown);
});

onUnmounted(() => {
    document.removeEventListener('keydown', onKeydown);
    document.body.style.overflow = '';
});

watch(drawerOpen, (open) => {
    document.body.style.overflow = open ? 'hidden' : '';
});

function emptyForm() {
    return {
        name: '',
        email: '',
        phone: '',
        membership_tier_id: '',
        organization: 'sicama',
        card_role: 'Member',
        expires_at: defaultExpiry(),
        send_password_reset: true,
    };
}

function defaultExpiry() {
    const date = new Date();
    date.setFullYear(date.getFullYear() + 1);
    return date.toISOString().slice(0, 10);
}

function onKeydown(event) {
    if (event.key === 'Escape' && drawerOpen.value && !saving.value) {
        closeDrawer();
    }
}

async function loadTiers() {
    try {
        const { data } = await publicApi().get('/membership-tiers');
        tiers.value = data.data || [];
        if (tiers.value.length && !form.value.membership_tier_id) {
            const preferred = tiers.value.find((tier) => tier.slug === 'sicama') || tiers.value[0];
            form.value.membership_tier_id = preferred.id;
        }
    } catch {
        tiers.value = [];
    }
}

async function load(resetPage = false) {
    if (resetPage) {
        page.value = 1;
    }

    loading.value = true;
    try {
        const { data } = await getAdminClient().get('/members', {
            params: {
                search: search.value.trim() || undefined,
                status: statusFilter.value === 'all' ? undefined : statusFilter.value,
                page: page.value,
                per_page: 20,
            },
        });
        members.value = data.data || [];
        meta.value = {
            current_page: data.current_page ?? data.meta?.current_page ?? 1,
            last_page: data.last_page ?? data.meta?.last_page ?? 1,
            per_page: data.per_page ?? data.meta?.per_page ?? 20,
            total: data.total ?? data.meta?.total ?? members.value.length,
            from: data.from ?? data.meta?.from ?? null,
            to: data.to ?? data.meta?.to ?? null,
        };
    } finally {
        loading.value = false;
    }
}

async function exportMembersCsv() {
    exporting.value = true;
    message.value = '';
    error.value = '';

    try {
        const params = new URLSearchParams({ format: 'csv' });
        if (search.value.trim()) {
            params.set('search', search.value.trim());
        }
        if (statusFilter.value !== 'all') {
            params.set('status', statusFilter.value);
        }

        await downloadAdminExport(
            getAdminClient(),
            `/exports/members?${params.toString()}`,
            `members-${new Date().toISOString().slice(0, 10)}.csv`,
        );
        message.value = 'Members CSV downloaded.';
    } catch (e) {
        error.value = await messageFromBlobError(e, 'Unable to export members. Please try again.');
    } finally {
        exporting.value = false;
    }
}

function goToPage(nextPage) {
    if (nextPage < 1 || nextPage > meta.value.last_page || nextPage === page.value) {
        return;
    }

    page.value = nextPage;
    load();
}

watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => load(true), 300);
});

watch(statusFilter, () => load(true));

function openDrawer() {
    form.value = {
        ...emptyForm(),
        membership_tier_id: tiers.value.find((tier) => tier.slug === 'sicama')?.id
            || tiers.value[0]?.id
            || '',
    };
    error.value = '';
    fieldErrors.value = {};
    drawerOpen.value = true;
}

function closeDrawer() {
    if (saving.value) {
        return;
    }
    drawerOpen.value = false;
    error.value = '';
    fieldErrors.value = {};
}

const selectedTier = computed(() => tiers.value.find(
    (tier) => String(tier.id) === String(form.value.membership_tier_id),
));

const isSicama = computed(() => form.value.organization === 'sicama');

const pageSicamaCount = computed(() => members.value.filter((m) =>
    String(m.membership_number || '').startsWith('SICAMA')
    || m.metadata?.organization === 'sicama',
).length);

const previewNumber = computed(() => 'SICAMA 2026-27/XXXX');

function formatDate(value) {
    return value ? new Date(value).toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    }) : '—';
}

function formatDues(amount) {
    return Number(amount || 0).toLocaleString();
}

function statusClass(status) {
    if (status === 'active') return 'bg-emerald-100 text-emerald-800';
    if (status === 'suspended') return 'bg-amber-100 text-amber-800';
    return 'bg-slate-100 text-slate-700';
}

function orgLabel(member) {
    if (String(member.membership_number || '').startsWith('SICAMA') || member.metadata?.organization === 'sicama') {
        return 'SICAMA';
    }
    if (String(member.membership_number || '').startsWith('MEM-')) {
        return 'Pending payment';
    }
    return 'SICAMA';
}

async function addMember() {
    error.value = '';
    fieldErrors.value = {};
    message.value = '';
    saving.value = true;

    try {
        const payload = {
            name: form.value.name.trim(),
            email: form.value.email.trim(),
            phone: form.value.phone || null,
            membership_tier_id: Number(form.value.membership_tier_id),
            organization: form.value.organization,
            card_role: isSicama.value ? form.value.card_role : 'Member',
            expires_at: form.value.expires_at || null,
            send_password_reset: Boolean(form.value.send_password_reset),
        };

        const { data } = await getAdminClient().post('/members', payload);
        members.value.unshift(data.data);
        drawerOpen.value = false;

        await notifySuccess(
            'Member created',
            data.password_reset_sent
                ? `${data.data.user?.name} is active. Password setup email sent · ${data.data.membership_number}`
                : `${data.data.user?.name} is active · ${data.data.membership_number}`,
        );

        message.value = data.password_reset_sent
            ? 'Member added. Password reset email sent.'
            : 'Member added successfully.';
    } catch (e) {
        fieldErrors.value = e.response?.data?.errors || {};
        error.value = extractApiError(e, 'Failed to add member.');
        await notifyError('Could not create member', error.value);
    } finally {
        saving.value = false;
    }
}

async function deactivateMember(member) {
    const ok = await confirmAction({
        title: `Deactivate ${member.user?.name}?`,
        text: 'They will lose active member access until reactivated.',
        confirmText: 'Deactivate',
        tone: 'danger',
    });
    if (!ok) return;

    try {
        const { data } = await getAdminClient().post(`/members/${member.uuid}/deactivate`);
        const index = members.value.findIndex((item) => item.uuid === member.uuid);
        if (index >= 0) members.value[index] = data.data;
        await notifySuccess('Member deactivated');
    } catch (e) {
        await notifyError('Deactivate failed', extractApiError(e));
    }
}

async function reactivateMember(member) {
    try {
        const { data } = await getAdminClient().post(`/members/${member.uuid}/reactivate`);
        const index = members.value.findIndex((item) => item.uuid === member.uuid);
        if (index >= 0) members.value[index] = data.data;
        await notifySuccess('Member reactivated');
    } catch (e) {
        await notifyError('Reactivate failed', extractApiError(e));
    }
}

function fieldError(key) {
    const value = fieldErrors.value[key];
    return Array.isArray(value) ? value[0] : '';
}
</script>

<template>
    <div>
        <AdminPageIntro description="Create members instantly, issue the right ID card, and manage the live directory.">
            <template #actions>
                <button
                    type="button"
                    class="btn-primary inline-flex items-center gap-2 !rounded-xl"
                    @click="openDrawer"
                >
                    <PlusIcon class="size-4" />
                    Create member
                </button>
            </template>
        </AdminPageIntro>

        <div class="mb-6 grid gap-3 sm:grid-cols-3">
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Total matching</p>
                <p class="mt-1 font-display text-2xl font-bold text-slate-900">{{ meta.total }}</p>
                <p class="mt-1 text-sm text-slate-500">Across all pages</p>
            </div>
            <div class="rounded-2xl border border-emerald-100 bg-gradient-to-br from-emerald-50 to-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wider text-emerald-700">This page</p>
                <p class="mt-1 font-display text-2xl font-bold text-emerald-900">{{ members.length }}</p>
                <p class="mt-1 text-sm text-emerald-700/80">
                    {{ meta.from || 0 }}–{{ meta.to || 0 }} of {{ meta.total }}
                </p>
            </div>
            <div class="rounded-2xl border border-cyan-100 bg-gradient-to-br from-cyan-50 to-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wider text-cyan-800">SICAMA on page</p>
                <p class="mt-1 font-display text-2xl font-bold text-cyan-950">{{ pageSicamaCount }}</p>
                <p class="mt-1 text-sm text-cyan-800/80">Association credentials</p>
            </div>
        </div>

        <AdminToolbar>
            <template #search>
                <div class="flex flex-col gap-2 sm:flex-row">
                    <div class="relative min-w-0 flex-1">
                        <MagnifyingGlassIcon class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                        <input
                            v-model="search"
                            type="search"
                            placeholder="Search name, email, or membership number…"
                            class="input-modern !pl-10"
                            @keyup.enter="load"
                        />
                    </div>
                    <select v-model="statusFilter" class="select-modern sm:w-40">
                        <option value="all">All statuses</option>
                        <option value="active">Active</option>
                        <option value="suspended">Suspended</option>
                        <option value="expired">Expired</option>
                    </select>
                    <button type="button" class="btn-secondary shrink-0 !rounded-xl" @click="load(true)">Search</button>
                </div>
            </template>
            <template #actions>
                <button
                    type="button"
                    class="btn-primary inline-flex w-full items-center justify-center gap-2 !rounded-xl sm:w-auto"
                    @click="openDrawer"
                >
                    <UserPlusIcon class="size-4" />
                    Create member
                </button>
                <button
                    type="button"
                    class="btn-secondary inline-flex w-full items-center justify-center gap-2 !rounded-xl sm:w-auto"
                    :disabled="exporting"
                    @click="exportMembersCsv"
                >
                    {{ exporting ? 'Preparing…' : 'Export CSV' }}
                </button>
            </template>
        </AdminToolbar>

        <AdminAlert v-if="message" type="success" class="mb-4">{{ message }}</AdminAlert>

        <div v-if="loading" class="empty-state bg-white">Loading members…</div>

        <AdminEmptyState
            v-else-if="!members.length"
            title="No members yet"
            description="Create the first member and their ID card will be issued automatically."
        >
            <template #icon><UserGroupIcon class="size-6" /></template>
            <template #action>
                <button type="button" class="btn-primary !rounded-xl" @click="openDrawer">Create member</button>
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
                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold capitalize" :class="statusClass(m.status)">
                            {{ m.status }}
                        </span>
                    </div>
                    <dl class="grid grid-cols-2 gap-2 text-xs text-slate-500">
                        <div>
                            <dt class="uppercase tracking-wide">Member #</dt>
                            <dd class="mt-0.5 font-mono text-slate-700">{{ m.membership_number || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="uppercase tracking-wide">Org</dt>
                            <dd class="mt-0.5 text-slate-700">{{ orgLabel(m) }}</dd>
                        </div>
                        <div>
                            <dt class="uppercase tracking-wide">Tier</dt>
                            <dd class="mt-0.5 text-slate-700">{{ m.tier?.name || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="uppercase tracking-wide">Expires</dt>
                            <dd class="mt-0.5 text-slate-700">{{ formatDate(m.expires_at) }}</dd>
                        </div>
                    </dl>
                    <div class="flex gap-3">
                        <button
                            v-if="m.status === 'active'"
                            type="button"
                            class="text-sm font-medium text-red-600 hover:underline"
                            @click="deactivateMember(m)"
                        >
                            Deactivate
                        </button>
                        <button
                            v-else-if="m.status === 'suspended'"
                            type="button"
                            class="text-sm font-medium text-emerald-700 hover:underline"
                            @click="reactivateMember(m)"
                        >
                            Reactivate
                        </button>
                    </div>
                </article>
            </div>

            <div class="hidden overflow-x-auto md:block admin-panel">
                <table class="min-w-[860px] w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-slate-600">Member</th>
                            <th class="px-4 py-3 text-left font-medium text-slate-600">Number</th>
                            <th class="px-4 py-3 text-left font-medium text-slate-600">Org</th>
                            <th class="px-4 py-3 text-left font-medium text-slate-600">Tier</th>
                            <th class="px-4 py-3 text-left font-medium text-slate-600">Status</th>
                            <th class="px-4 py-3 text-left font-medium text-slate-600">Expires</th>
                            <th class="px-4 py-3 text-left font-medium text-slate-600">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="m in members" :key="m.uuid" class="hover:bg-slate-50/70">
                            <td class="px-4 py-3">
                                <div class="font-medium text-slate-900">{{ m.user?.name }}</div>
                                <div class="text-xs text-slate-500">{{ m.user?.email }}</div>
                            </td>
                            <td class="px-4 py-3 font-mono text-xs">{{ m.membership_number }}</td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold"
                                    :class="orgLabel(m) === 'SICAMA' ? 'bg-cyan-100 text-cyan-900' : 'bg-blue-100 text-blue-900'"
                                >
                                    {{ orgLabel(m) }}
                                </span>
                            </td>
                            <td class="px-4 py-3">{{ m.tier?.name }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold capitalize" :class="statusClass(m.status)">
                                    {{ m.status }}
                                </span>
                            </td>
                            <td class="px-4 py-3">{{ formatDate(m.expires_at) }}</td>
                            <td class="px-4 py-3">
                                <button
                                    v-if="m.status === 'active'"
                                    type="button"
                                    class="text-sm font-medium text-red-600 hover:underline"
                                    @click="deactivateMember(m)"
                                >
                                    Deactivate
                                </button>
                                <button
                                    v-else-if="m.status === 'suspended'"
                                    type="button"
                                    class="text-sm font-medium text-emerald-700 hover:underline"
                                    @click="reactivateMember(m)"
                                >
                                    Reactivate
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <nav
                v-if="meta.last_page > 1"
                class="mt-4 flex flex-col items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-3 sm:flex-row"
                aria-label="Members pagination"
            >
                <p class="text-sm text-slate-500">
                    Showing {{ meta.from }}–{{ meta.to }} of {{ meta.total }}
                </p>
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="btn-secondary inline-flex items-center gap-1.5 !rounded-xl !px-3 !py-2"
                        :disabled="meta.current_page <= 1 || loading"
                        @click="goToPage(meta.current_page - 1)"
                    >
                        <ArrowLeftIcon class="size-4" />
                        Previous
                    </button>
                    <span class="min-w-[5.5rem] text-center text-sm font-medium text-slate-700">
                        {{ meta.current_page }} / {{ meta.last_page }}
                    </span>
                    <button
                        type="button"
                        class="btn-secondary inline-flex items-center gap-1.5 !rounded-xl !px-3 !py-2"
                        :disabled="meta.current_page >= meta.last_page || loading"
                        @click="goToPage(meta.current_page + 1)"
                    >
                        Next
                        <ArrowRightIcon class="size-4" />
                    </button>
                </div>
            </nav>
        </template>

        <!-- Create member drawer -->
        <Teleport to="body">
            <div v-if="drawerOpen" class="fixed inset-0 z-50">
                <div
                    class="absolute inset-0 bg-slate-900/45 backdrop-blur-[2px] transition-opacity"
                    @click="closeDrawer"
                />

                <aside
                    class="absolute inset-y-0 right-0 flex w-full max-w-xl flex-col bg-white shadow-2xl"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="create-member-title"
                >
                    <header class="relative overflow-hidden border-b border-slate-100 px-5 py-5 sm:px-6">
                        <div class="pointer-events-none absolute inset-0 bg-gradient-to-br from-[#156082]/10 via-white to-[#0a3d91]/5" />
                        <div class="relative flex items-start justify-between gap-3">
                            <div class="flex items-start gap-3">
                                <span class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-[#156082] text-white shadow-lg shadow-cyan-900/20">
                                    <SparklesIcon class="size-6" />
                                </span>
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#156082]">Admin</p>
                                    <h2 id="create-member-title" class="mt-1 font-display text-xl font-bold text-slate-900">
                                        Create member
                                    </h2>
                                    <p class="mt-1 text-sm text-slate-600">
                                        Instant account + ID card. Password setup email optional.
                                    </p>
                                </div>
                            </div>
                            <button
                                type="button"
                                class="rounded-xl p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-800"
                                :disabled="saving"
                                aria-label="Close"
                                @click="closeDrawer"
                            >
                                <XMarkIcon class="size-5" />
                            </button>
                        </div>
                    </header>

                    <form class="flex min-h-0 flex-1 flex-col" @submit.prevent="addMember">
                        <div class="flex-1 space-y-6 overflow-y-auto px-5 py-5 sm:px-6">
                            <AdminAlert v-if="error" type="error">{{ error }}</AdminAlert>

                            <section>
                                <h3 class="text-sm font-semibold text-slate-900">Card type</h3>
                                <p class="mt-1 text-xs text-slate-500">Choose which official credential to issue.</p>
                                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                    <button
                                        v-for="org in orgOptions"
                                        :key="org.id"
                                        type="button"
                                        class="rounded-2xl border p-4 text-left transition"
                                        :class="form.organization === org.id
                                            ? 'border-[#156082] bg-[#156082]/5 ring-2 ring-[#156082]/25'
                                            : 'border-slate-200 hover:border-slate-300'"
                                        @click="form.organization = org.id; if (org.id === 'sicama' && !form.card_role) form.card_role = 'Member'"
                                    >
                                        <div class="flex items-center justify-between gap-2">
                                            <span class="font-semibold text-slate-900">{{ org.label }}</span>
                                            <span class="rounded-full bg-white px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-slate-600 shadow-sm">
                                                {{ org.badge }}
                                            </span>
                                        </div>
                                        <p class="mt-2 text-xs leading-relaxed text-slate-600">{{ org.description }}</p>
                                    </button>
                                </div>
                            </section>

                            <section class="space-y-4">
                                <h3 class="text-sm font-semibold text-slate-900">Profile</h3>
                                <div class="form-field">
                                    <FormFieldLabel label="Full name" required />
                                    <div class="relative">
                                        <UserIcon class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                                        <input
                                            v-model="form.name"
                                            type="text"
                                            required
                                            class="input-modern !pl-10"
                                            placeholder="Ada Okonkwo"
                                            autocomplete="name"
                                        />
                                    </div>
                                    <p v-if="fieldError('name')" class="mt-1 text-xs text-red-600">{{ fieldError('name') }}</p>
                                </div>
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div class="form-field">
                                        <FormFieldLabel label="Email" required help="Login + notifications." />
                                        <div class="relative">
                                            <EnvelopeIcon class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                                            <input
                                                v-model="form.email"
                                                type="email"
                                                required
                                                class="input-modern !pl-10"
                                                placeholder="member@example.com"
                                                autocomplete="email"
                                            />
                                        </div>
                                        <p v-if="fieldError('email')" class="mt-1 text-xs text-red-600">{{ fieldError('email') }}</p>
                                    </div>
                                    <div class="form-field">
                                        <FormFieldLabel label="Phone" help="Optional." />
                                        <AdminPhoneInput v-model="form.phone" default-country="NG" />
                                    </div>
                                </div>
                            </section>

                            <section class="space-y-4">
                                <h3 class="text-sm font-semibold text-slate-900">Membership</h3>
                                <div>
                                    <FormFieldLabel label="Tier" required />
                                    <div class="mt-2 grid gap-2">
                                        <button
                                            v-for="tier in tiers"
                                            :key="tier.id"
                                            type="button"
                                            class="flex items-start justify-between gap-3 rounded-xl border px-3.5 py-3 text-left transition"
                                            :class="String(form.membership_tier_id) === String(tier.id)
                                                ? 'border-institutional bg-brand-50/70'
                                                : 'border-slate-200 hover:border-slate-300'"
                                            @click="form.membership_tier_id = tier.id"
                                        >
                                            <div>
                                                <p class="font-medium text-slate-900">{{ tier.name }}</p>
                                                <p v-if="tier.description" class="mt-0.5 line-clamp-2 text-xs text-slate-500">
                                                    {{ tier.description }}
                                                </p>
                                            </div>
                                            <div class="shrink-0 text-right">
                                                <p class="text-sm font-semibold text-institutional">
                                                    ₦{{ formatDues(tier.annual_dues) }}
                                                </p>
                                                <CheckCircleIcon
                                                    v-if="String(form.membership_tier_id) === String(tier.id)"
                                                    class="ml-auto mt-1 size-5 text-institutional"
                                                />
                                            </div>
                                        </button>
                                    </div>
                                    <p v-if="fieldError('membership_tier_id')" class="mt-1 text-xs text-red-600">
                                        {{ fieldError('membership_tier_id') }}
                                    </p>
                                </div>

                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div v-if="isSicama" class="form-field">
                                        <FormFieldLabel label="Card role" help="Printed on the SICAMA ID card." />
                                        <select v-model="form.card_role" class="select-modern">
                                            <option v-for="role in cardRoleOptions" :key="role" :value="role">
                                                {{ role }}
                                            </option>
                                        </select>
                                    </div>
                                    <div class="form-field" :class="{ 'sm:col-span-2': !isSicama }">
                                        <FormFieldLabel label="Valid until" required />
                                        <div class="relative">
                                            <CalendarDaysIcon class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                                            <input
                                                v-model="form.expires_at"
                                                type="date"
                                                required
                                                class="input-modern !pl-10"
                                            />
                                        </div>
                                    </div>
                                </div>

                                <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 bg-slate-50/70 p-3.5">
                                    <input
                                        v-model="form.send_password_reset"
                                        type="checkbox"
                                        class="mt-1 size-4 rounded border-slate-300 text-institutional focus:ring-institutional"
                                    />
                                    <span>
                                        <span class="block text-sm font-medium text-slate-900">Send password setup email</span>
                                        <span class="mt-0.5 block text-xs text-slate-500">
                                            Recommended for new accounts so they can sign in to the member portal.
                                        </span>
                                    </span>
                                </label>
                            </section>

                            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-slate-950 text-white shadow-lg">
                                <div
                                    class="px-4 py-3"
                                    :class="isSicama ? 'bg-[#156082]' : 'bg-[#0a3d91]'"
                                >
                                    <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.16em] text-white/80">
                                        <IdentificationIcon class="size-4" />
                                        Credential preview
                                    </div>
                                </div>
                                <div class="space-y-2 px-4 py-4">
                                    <p class="text-lg font-semibold">{{ form.name || 'Member name' }}</p>
                                    <p class="text-sm text-white/70">{{ isSicama ? (form.card_role || 'Member') : (selectedTier?.name || 'Member') }}</p>
                                    <p class="font-mono text-sm text-cyan-200">{{ previewNumber }}</p>
                                    <p class="text-xs text-white/55">
                                        {{ form.email || 'email@example.com' }} · Valid until {{ form.expires_at || '—' }}
                                    </p>
                                </div>
                            </section>
                        </div>

                        <footer class="border-t border-slate-100 bg-white px-5 py-4 sm:px-6">
                            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <p class="text-xs text-slate-500">
                                    <PhoneIcon class="mr-1 inline size-3.5" />
                                    ID card PDF is generated immediately on create.
                                </p>
                                <div class="flex gap-2">
                                    <button
                                        type="button"
                                        class="btn-secondary flex-1 !rounded-xl sm:flex-none"
                                        :disabled="saving"
                                        @click="closeDrawer"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="submit"
                                        class="btn-primary inline-flex flex-1 items-center justify-center gap-2 !rounded-xl sm:flex-none"
                                        :disabled="saving || !form.membership_tier_id"
                                    >
                                        <UserPlusIcon class="size-4" />
                                        {{ saving ? 'Creating…' : 'Create & issue card' }}
                                    </button>
                                </div>
                            </div>
                        </footer>
                    </form>
                </aside>
            </div>
        </Teleport>
    </div>
</template>
