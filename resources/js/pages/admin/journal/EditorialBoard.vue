<script setup>
import { ref, onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import { PlusIcon, TrashIcon, UserGroupIcon } from '@heroicons/vue/24/outline';
import AdminAlert from '../../../components/admin/AdminAlert.vue';
import AdminEmptyState from '../../../components/admin/AdminEmptyState.vue';
import AdminPageIntro from '../../../components/admin/AdminPageIntro.vue';
import AdminPanel from '../../../components/admin/AdminPanel.vue';
import RichTextEditor from '../../../components/forms/RichTextEditor.vue';
import { useAuth } from '../../../composables/useAuth';
import { editorialBoardRoles } from '../../../config/editorialBoardRoles';
import { extractApiError } from '../../../utils/apiError';
import { confirmAction } from '../../../utils/swal';

const { getJournalClient, fetchAdminProfile } = useAuth();

const members = ref([]);
const loading = ref(true);
const saving = ref(false);
const message = ref('');
const error = ref('');

const form = ref({
    name: '',
    role_title: '',
    affiliation: '',
    bio: '',
    sort_order: 0,
    is_active: true,
});

onMounted(async () => {
    await fetchAdminProfile();
    await load();
});

async function load() {
    loading.value = true;
    error.value = '';

    try {
        const { data } = await getJournalClient().get('/admin/editorial-board');
        members.value = data.data || [];
    } catch (e) {
        error.value = extractApiError(e, 'Unable to load editorial board.');
    } finally {
        loading.value = false;
    }
}

async function save() {
    saving.value = true;
    message.value = '';
    error.value = '';

    try {
        await getJournalClient().post('/admin/editorial-board', {
            ...form.value,
            sort_order: Number(form.value.sort_order) || 0,
        });
        message.value = 'Board member added.';
        form.value = { name: '', role_title: '', affiliation: '', bio: '', sort_order: 0, is_active: true };
        await load();
    } catch (e) {
        error.value = extractApiError(e, 'Unable to add board member.');
    } finally {
        saving.value = false;
    }
}

async function toggleActive(member) {
    try {
        await getJournalClient().put(`/admin/editorial-board/${member.uuid}`, {
            is_active: !member.is_active,
        });
        await load();
    } catch (e) {
        error.value = extractApiError(e, 'Unable to update member.');
    }
}

async function removeMember(member) {
    const confirmed = await confirmAction({
        title: 'Remove board member?',
        text: `${member.name} will be removed from the public editorial board listing.`,
        confirmText: 'Remove',
        tone: 'danger',
    });
    if (!confirmed) {
        return;
    }

    try {
        await getJournalClient().delete(`/admin/editorial-board/${member.uuid}`);
        message.value = 'Board member removed.';
        await load();
    } catch (e) {
        error.value = extractApiError(e, 'Unable to remove board member.');
    }
}
</script>

<template>
    <div>
        <AdminPageIntro description="Public-facing editorial leadership shown on the website and on calls for papers.">
            <template #actions>
                <RouterLink to="/admin/journal" class="btn-secondary w-full !rounded-xl text-center sm:w-auto">
                    Submissions
                </RouterLink>
                <RouterLink to="/admin/journal/calls-for-papers" class="btn-secondary w-full !rounded-xl text-center sm:w-auto">
                    Calls for papers
                </RouterLink>
                <RouterLink to="/admin/journal/reviewers" class="btn-primary w-full !rounded-xl text-center sm:w-auto">
                    Peer reviewers
                </RouterLink>
            </template>
        </AdminPageIntro>

        <AdminAlert type="info">
            This page is <strong>not</strong> where peer reviewers are added.
            Board roles like “Peer Review Editor” are display titles for the public site.
            To assign manuscripts, use
            <RouterLink to="/admin/journal/reviewers" class="font-semibold underline">Peer reviewers</RouterLink>.
        </AdminAlert>

        <AdminAlert v-if="message" type="success">{{ message }}</AdminAlert>
        <AdminAlert v-if="error" type="error">{{ error }}</AdminAlert>

        <AdminPanel title="Add board member" class="mb-6">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label">Name</label>
                    <input v-model="form.name" class="input !rounded-xl" required />
                </div>
                <div>
                    <label class="label">Role / title</label>
                    <select v-model="form.role_title" class="input !rounded-xl" required>
                        <option value="" disabled>Select a role</option>
                        <option v-for="role in editorialBoardRoles" :key="role" :value="role">
                            {{ role }}
                        </option>
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label class="label">Affiliation</label>
                    <input v-model="form.affiliation" class="input !rounded-xl" placeholder="University or institution" />
                </div>
                <div class="sm:col-span-2">
                    <label class="label">Bio</label>
                    <RichTextEditor v-model="form.bio" placeholder="Short biography (optional)" min-height="6rem" />
                </div>
                <div>
                    <label class="label">Sort order</label>
                    <input v-model="form.sort_order" type="number" min="0" class="input !rounded-xl" />
                </div>
            </div>
            <button class="btn-primary mt-4 !rounded-xl" type="button" :disabled="saving || !form.name || !form.role_title" @click="save">
                <PlusIcon class="mr-1 inline size-4" />
                {{ saving ? 'Adding…' : 'Add member' }}
            </button>
        </AdminPanel>

        <div class="mb-6 grid gap-4 sm:grid-cols-3">
            <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Total</p>
                <p class="mt-1 text-2xl font-semibold text-slate-900">{{ members.length }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Active</p>
                <p class="mt-1 text-2xl font-semibold text-emerald-700">{{ members.filter((m) => m.is_active).length }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Inactive</p>
                <p class="mt-1 text-2xl font-semibold text-slate-500">{{ members.filter((m) => !m.is_active).length }}</p>
            </div>
        </div>

        <AdminPanel title="Current board" description="Visible on the public editorial board and available when composing calls for papers.">
            <div v-if="loading" class="empty-state bg-white">Loading board members…</div>

            <AdminEmptyState
                v-else-if="!members.length"
                title="No board members"
                description="Add leadership and editorial roles that should appear on the public site."
            >
                <template #icon><UserGroupIcon class="size-6" /></template>
            </AdminEmptyState>

            <ul v-else class="-mx-4 divide-y divide-slate-100 sm:-mx-5">
                <li v-for="member in members" :key="member.uuid" class="admin-list-item items-start">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="font-medium text-slate-900">{{ member.name }}</p>
                            <span
                                class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset"
                                :class="member.is_active ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/20' : 'bg-slate-100 text-slate-500 ring-slate-500/10'"
                            >
                                {{ member.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>
                        <p class="mt-1 text-sm font-medium text-slate-700">{{ member.role_title }}</p>
                        <p v-if="member.affiliation" class="text-sm text-slate-500">{{ member.affiliation }}</p>
                    </div>
                    <div class="flex shrink-0 gap-2">
                        <button class="btn-secondary !rounded-xl text-sm" type="button" @click="toggleActive(member)">
                            {{ member.is_active ? 'Deactivate' : 'Activate' }}
                        </button>
                        <button class="btn-secondary !rounded-xl text-sm text-red-600" type="button" @click="removeMember(member)">
                            <TrashIcon class="inline size-4" />
                        </button>
                    </div>
                </li>
            </ul>
        </AdminPanel>
    </div>
</template>
