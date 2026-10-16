<script setup>
import { computed, ref, onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import {
    ClipboardDocumentIcon,
    EnvelopeIcon,
    MagnifyingGlassIcon,
    TrashIcon,
    UserPlusIcon,
    UsersIcon,
} from '@heroicons/vue/24/outline';
import AdminAlert from '../../../components/admin/AdminAlert.vue';
import AdminEmptyState from '../../../components/admin/AdminEmptyState.vue';
import AdminPageIntro from '../../../components/admin/AdminPageIntro.vue';
import AdminPanel from '../../../components/admin/AdminPanel.vue';
import { useAuth } from '../../../composables/useAuth';
import { extractApiError } from '../../../utils/apiError';
import Swal from 'sweetalert2';
import { notifyError, notifySuccess } from '../../../utils/swal';

const { getJournalClient, fetchAdminProfile } = useAuth();

const reviewers = ref([]);
const loading = ref(true);
const saving = ref(false);
const removingId = ref('');
const form = ref({ name: '', email: '' });
const error = ref('');
const tempPassword = ref('');
const lastAddedName = ref('');
const search = ref('');
const copiedKey = ref('');

const filtered = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (!q) {
        return reviewers.value;
    }

    return reviewers.value.filter((reviewer) => {
        const haystack = `${reviewer.name} ${reviewer.email}`.toLowerCase();
        return haystack.includes(q);
    });
});

const canSubmit = computed(() => Boolean(form.value.name.trim() && form.value.email.trim()) && !saving.value);

onMounted(async () => {
    await fetchAdminProfile();
    await loadReviewers();
});

async function loadReviewers() {
    loading.value = true;
    error.value = '';

    try {
        const { data } = await getJournalClient().get('/admin/reviewers');
        reviewers.value = data.data || [];
    } catch (e) {
        error.value = extractApiError(e, 'Unable to load reviewers.');
    } finally {
        loading.value = false;
    }
}

async function createReviewer() {
    if (!canSubmit.value) {
        return;
    }

    saving.value = true;
    error.value = '';
    tempPassword.value = '';
    lastAddedName.value = form.value.name.trim();

    try {
        const { data } = await getJournalClient().post('/admin/reviewers', {
            name: form.value.name.trim(),
            email: form.value.email.trim(),
        });
        tempPassword.value = data.temporary_password || '';
        form.value = { name: '', email: '' };
        notifySuccess('Reviewer ready', data.message || 'They can now be assigned to manuscripts.');
        await loadReviewers();
    } catch (e) {
        error.value = extractApiError(e, 'Unable to add reviewer.');
        notifyError('Could not add reviewer', error.value);
    } finally {
        saving.value = false;
    }
}

function initials(name) {
    const parts = String(name || '')
        .trim()
        .split(/\s+/)
        .filter(Boolean);
    if (!parts.length) {
        return '?';
    }
    if (parts.length === 1) {
        return parts[0].slice(0, 2).toUpperCase();
    }
    return `${parts[0][0]}${parts[parts.length - 1][0]}`.toUpperCase();
}

async function copyText(value, key) {
    try {
        await navigator.clipboard.writeText(value);
        copiedKey.value = key;
        window.setTimeout(() => {
            if (copiedKey.value === key) {
                copiedKey.value = '';
            }
        }, 1800);
    } catch {
        notifyError('Copy failed', 'Select and copy the text manually.');
    }
}

async function removeReviewer(reviewer) {
    if (!reviewer?.can_remove) {
        notifyError(
            'Cannot remove',
            'This account appears because of editorial permissions, not a dedicated reviewer role.',
        );
        return;
    }

    if (reviewer.active_assignments > 0) {
        notifyError(
            'Active assignments',
            `Reassign or complete ${reviewer.active_assignments} active review(s) before removing this reviewer.`,
        );
        return;
    }

    const email = String(reviewer.email || '').trim();
    const result = await Swal.fire({
        title: 'Remove reviewer access?',
        html: `
            <p style="text-align:left;margin:0 0 12px;">
                This removes peer-review access for <strong>${reviewer.name}</strong>.
                Their login account is kept.
            </p>
            <p style="text-align:left;margin:0 0 8px;font-size:13px;color:#64748b;">
                To confirm, type <strong>REMOVE</strong> below.
            </p>
            <p style="text-align:left;margin:0;font-size:13px;color:#334155;">
                Reviewer: <code style="font-size:12px;">${email}</code>
            </p>
        `,
        input: 'text',
        inputPlaceholder: 'REMOVE',
        icon: 'warning',
        showCancelButton: true,
        focusCancel: true,
        confirmButtonText: 'Remove access',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#64748b',
        reverseButtons: true,
        customClass: {
            popup: 'rounded-2xl',
            confirmButton: '!rounded-xl !px-5 !py-2.5 !font-semibold',
            cancelButton: '!rounded-xl !px-5 !py-2.5 !font-semibold',
        },
        inputValidator: (value) => {
            if (String(value || '').trim().toUpperCase() !== 'REMOVE') {
                return 'Type REMOVE (all caps) to confirm.';
            }
            return null;
        },
    });

    if (!result.isConfirmed) {
        return;
    }

    removingId.value = reviewer.id;
    error.value = '';

    try {
        const { data } = await getJournalClient().delete(`/admin/reviewers/${reviewer.id}`);
        notifySuccess('Reviewer removed', data.message || 'Reviewer access removed.');
        await loadReviewers();
    } catch (e) {
        error.value = extractApiError(e, 'Unable to remove reviewer.');
        notifyError('Could not remove reviewer', error.value);
    } finally {
        removingId.value = '';
    }
}
</script>

<template>
    <div class="space-y-6">
        <AdminPageIntro description="Invite scholars who can log in and review manuscripts. This is separate from the public editorial board.">
            <template #actions>
                <RouterLink to="/admin/journal" class="btn-secondary w-full !rounded-xl text-center sm:w-auto">
                    Back to submissions
                </RouterLink>
                <RouterLink to="/admin/journal/editorial-board" class="btn-secondary w-full !rounded-xl text-center sm:w-auto">
                    Public editorial board
                </RouterLink>
            </template>
        </AdminPageIntro>

        <div class="overflow-hidden rounded-2xl border border-slate-200/80 bg-gradient-to-br from-slate-50 via-white to-brand-50/40 shadow-sm">
            <div class="grid gap-0 lg:grid-cols-[1.1fr_0.9fr]">
                <div class="border-b border-slate-200/70 p-5 sm:p-6 lg:border-b-0 lg:border-r">
                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-brand-800/80">Workflow</p>
                    <h2 class="mt-2 text-lg font-semibold text-slate-900">How peer review access works</h2>
                    <ol class="mt-5 space-y-4">
                        <li class="flex gap-3">
                            <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-brand-600 text-sm font-semibold text-white">1</span>
                            <div>
                                <p class="font-medium text-slate-900">Add by name and email</p>
                                <p class="mt-0.5 text-sm text-slate-600">Existing accounts get the reviewer role; new emails get a temporary password.</p>
                            </div>
                        </li>
                        <li class="flex gap-3">
                            <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-brand-600 text-sm font-semibold text-white">2</span>
                            <div>
                                <p class="font-medium text-slate-900">They appear in Assign reviewer</p>
                                <p class="mt-0.5 text-sm text-slate-600">Open any submission and choose them from the reviewer dropdown.</p>
                            </div>
                        </li>
                        <li class="flex gap-3">
                            <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-brand-600 text-sm font-semibold text-white">3</span>
                            <div>
                                <p class="font-medium text-slate-900">Keep board titles separate</p>
                                <p class="mt-0.5 text-sm text-slate-600">
                                    Public titles live on the
                                    <RouterLink to="/admin/journal/editorial-board" class="font-semibold text-brand-700 underline decoration-brand-300 underline-offset-2">
                                        editorial board
                                    </RouterLink>
                                    page and do not grant login access here.
                                </p>
                            </div>
                        </li>
                    </ol>
                </div>
                <div class="flex flex-col justify-center gap-4 p-5 sm:p-6">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Assignable now</p>
                        <p class="mt-1 text-4xl font-semibold tracking-tight text-slate-900">{{ reviewers.length }}</p>
                        <p class="mt-1 text-sm text-slate-600">
                            {{ reviewers.length === 1 ? 'person can be assigned' : 'people can be assigned' }} to manuscripts
                        </p>
                    </div>
                    <div class="rounded-xl border border-dashed border-slate-300/90 bg-white/70 px-4 py-3 text-sm text-slate-600">
                        Tip: after adding someone new, share their temporary password securely and ask them to change it on first login.
                    </div>
                </div>
            </div>
        </div>

        <AdminAlert v-if="error" type="error">{{ error }}</AdminAlert>

        <div
            v-if="tempPassword"
            class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4 sm:px-5"
        >
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="font-semibold text-amber-950">
                        Temporary password for {{ lastAddedName || 'new reviewer' }}
                    </p>
                    <p class="mt-1 font-mono text-lg font-semibold tracking-wide text-amber-900">
                        {{ tempPassword }}
                    </p>
                    <p class="mt-1 text-sm text-amber-800/90">
                        Copy this now — it will not be shown again after you leave this page.
                    </p>
                </div>
                <button
                    type="button"
                    class="btn-secondary inline-flex items-center gap-2 !rounded-xl !border-amber-300 !bg-white text-sm"
                    @click="copyText(tempPassword, 'temp')"
                >
                    <ClipboardDocumentIcon class="size-4" />
                    {{ copiedKey === 'temp' ? 'Copied' : 'Copy password' }}
                </button>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,22rem)_minmax(0,1fr)]">
            <AdminPanel title="Invite reviewer" description="One person at a time. They receive access to the reviewer queue after login.">
                <form class="space-y-4" @submit.prevent="createReviewer">
                    <div>
                        <label class="label" for="reviewer-name">Full name</label>
                        <input
                            id="reviewer-name"
                            v-model="form.name"
                            class="input !rounded-xl"
                            placeholder="Dr. Ada Okoro"
                            autocomplete="name"
                        />
                    </div>
                    <div>
                        <label class="label" for="reviewer-email">Work email</label>
                        <div class="relative">
                            <EnvelopeIcon class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                            <input
                                id="reviewer-email"
                                v-model="form.email"
                                type="email"
                                class="input !rounded-xl pl-9"
                                placeholder="ada.okoro@university.edu"
                                autocomplete="email"
                            />
                        </div>
                    </div>
                    <button
                        class="btn-primary inline-flex w-full items-center justify-center gap-2 !rounded-xl"
                        type="submit"
                        :disabled="!canSubmit"
                    >
                        <UserPlusIcon class="size-4" />
                        {{ saving ? 'Adding…' : 'Add to reviewer pool' }}
                    </button>
                </form>
            </AdminPanel>

            <AdminPanel>
                <template #header>
                    <div class="flex min-w-0 flex-1 flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                        <div class="min-w-0">
                            <h2 class="admin-panel-title">Reviewer pool</h2>
                            <p class="admin-panel-description">
                                Everyone listed here shows up in the submission Assign reviewer menu.
                            </p>
                        </div>
                        <div class="relative w-full sm:max-w-xs">
                            <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                            <input
                                v-model="search"
                                type="search"
                                class="input w-full !rounded-xl pl-9"
                                placeholder="Filter by name or email"
                                aria-label="Filter reviewers"
                            />
                        </div>
                    </div>
                </template>

                <div v-if="loading" class="empty-state bg-white">Loading reviewer pool…</div>

                <AdminEmptyState
                    v-else-if="!reviewers.length"
                    title="Your reviewer pool is empty"
                    description="Invite the first peer reviewer using the form. Editors with journal access may already appear once permissions are set."
                >
                    <template #icon><UsersIcon class="size-6" /></template>
                </AdminEmptyState>

                <AdminEmptyState
                    v-else-if="!filtered.length"
                    title="No matches"
                    description="Try a different name or email fragment."
                >
                    <template #icon><MagnifyingGlassIcon class="size-6" /></template>
                </AdminEmptyState>

                <template v-else>
                    <p class="mb-3 text-sm text-slate-500">
                        Showing {{ filtered.length }} of {{ reviewers.length }}
                    </p>

                    <ul class="grid gap-3 sm:grid-cols-2">
                        <li
                            v-for="reviewer in filtered"
                            :key="reviewer.id"
                            class="group rounded-2xl border border-slate-200/90 bg-slate-50/50 p-4 transition hover:border-brand-200 hover:bg-white hover:shadow-sm"
                        >
                            <div class="flex items-start gap-3">
                                <div
                                    class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-brand-600 text-sm font-semibold tracking-wide text-white"
                                    aria-hidden="true"
                                >
                                    {{ initials(reviewer.name) }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="truncate font-semibold text-slate-900">{{ reviewer.name }}</p>
                                        <span class="inline-flex rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-emerald-700 ring-1 ring-inset ring-emerald-600/15">
                                            Ready
                                        </span>
                                        <span
                                            v-if="!reviewer.has_reviewer_role"
                                            class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-slate-600 ring-1 ring-inset ring-slate-500/15"
                                        >
                                            Editor access
                                        </span>
                                    </div>
                                    <p class="mt-1 truncate text-sm text-slate-600">{{ reviewer.email }}</p>
                                    <p v-if="reviewer.active_assignments > 0" class="mt-1 text-xs font-medium text-amber-700">
                                        {{ reviewer.active_assignments }} active assignment{{ reviewer.active_assignments === 1 ? '' : 's' }}
                                    </p>
                                    <div class="mt-3 flex flex-wrap items-center gap-3">
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-1.5 text-xs font-semibold text-brand-700 opacity-90 transition hover:opacity-100"
                                            @click="copyText(reviewer.email, reviewer.id)"
                                        >
                                            <ClipboardDocumentIcon class="size-3.5" />
                                            {{ copiedKey === reviewer.id ? 'Email copied' : 'Copy email' }}
                                        </button>
                                        <button
                                            v-if="reviewer.can_remove"
                                            type="button"
                                            class="inline-flex items-center gap-1.5 text-xs font-semibold text-red-600 hover:text-red-700 disabled:opacity-50"
                                            :disabled="removingId === reviewer.id"
                                            @click="removeReviewer(reviewer)"
                                        >
                                            <TrashIcon class="size-3.5" />
                                            {{ removingId === reviewer.id ? 'Removing…' : 'Remove access' }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </li>
                    </ul>
                </template>
            </AdminPanel>
        </div>
    </div>
</template>
