<script setup>
import { computed, ref, onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import {
    MegaphoneIcon,
    PencilSquareIcon,
    PlusIcon,
    TrashIcon,
    UserGroupIcon,
} from '@heroicons/vue/24/outline';
import AdminAlert from '../../../components/admin/AdminAlert.vue';
import AdminEmptyState from '../../../components/admin/AdminEmptyState.vue';
import AdminPageIntro from '../../../components/admin/AdminPageIntro.vue';
import AdminPanel from '../../../components/admin/AdminPanel.vue';
import RichTextEditor from '../../../components/forms/RichTextEditor.vue';
import { useAuth } from '../../../composables/useAuth';
import { extractApiError } from '../../../utils/apiError';

const { getJournalClient, fetchAdminProfile } = useAuth();

const calls = ref([]);
const boardMembers = ref([]);
const volumeOptions = ref([]);
const submissionFeeCatalog = ref([]);
const publicationFeeCatalog = ref([]);
const loading = ref(true);
const saving = ref(false);
const message = ref('');
const error = ref('');
const editingUuid = ref(null);

const emptyForm = () => ({
    journal_issue_uuid: '',
    title: '',
    excerpt: '',
    body: '',
    opens_at: '',
    closes_at: '',
    submission_fee: '',
    publication_fee: '',
    currency: 'NGN',
    status: 'draft',
    topics: '',
    editorial_board_member_ids: [],
});

const form = ref(emptyForm());

const issueOptions = computed(() => {
    const options = [];

    for (const volume of volumeOptions.value) {
        for (const issue of volume.issues || []) {
            options.push({
                uuid: issue.uuid,
                label: issue.label || `${volume.label} · Issue ${issue.issue_number}`,
            });
        }
    }

    return options;
});

const selectedBoardLabels = computed(() =>
    boardMembers.value
        .filter((member) => form.value.editorial_board_member_ids.includes(member.uuid))
        .map((member) => member.name),
);

onMounted(async () => {
    await fetchAdminProfile();
    await Promise.all([loadCalls(), loadBoardMembers(), loadVolumeOptions(), loadFeeCatalog()]);
});

async function loadCalls() {
    loading.value = true;
    error.value = '';

    try {
        const { data } = await getJournalClient().get('/admin/calls-for-papers');
        calls.value = data.data || [];
    } catch (e) {
        error.value = extractApiError(e, 'Unable to load calls for papers.');
    } finally {
        loading.value = false;
    }
}

async function loadBoardMembers() {
    try {
        const { data } = await getJournalClient().get('/admin/editorial-board');
        boardMembers.value = (data.data || []).filter((member) => member.is_active);
    } catch {
        boardMembers.value = [];
    }
}

async function loadVolumeOptions() {
    try {
        const { data } = await getJournalClient().get('/admin/volumes/options');
        volumeOptions.value = data.data || [];
    } catch {
        volumeOptions.value = [];
    }
}

async function loadFeeCatalog() {
    try {
        const [submissionResponse, publicationResponse] = await Promise.all([
            getJournalClient().get('/admin/fee-catalog', { params: { category: 'journal', fee_type: 'submission', active_only: true } }),
            getJournalClient().get('/admin/fee-catalog', { params: { category: 'journal', fee_type: 'publication', active_only: true } }),
        ]);
        submissionFeeCatalog.value = submissionResponse.data.data || [];
        publicationFeeCatalog.value = publicationResponse.data.data || [];
    } catch {
        submissionFeeCatalog.value = [];
        publicationFeeCatalog.value = [];
    }
}

function applyCatalogFee(type, uuid) {
    if (!uuid) {
        return;
    }

    const catalog = type === 'submission' ? submissionFeeCatalog.value : publicationFeeCatalog.value;
    const fee = catalog.find((item) => item.uuid === uuid);

    if (!fee) {
        return;
    }

    if (type === 'submission') {
        form.value.submission_fee = fee.amount;
    } else {
        form.value.publication_fee = fee.amount;
    }

    form.value.currency = fee.currency || form.value.currency;
}

function formatMoney(amount, currency = 'NGN') {
    return new Intl.NumberFormat('en-NG', { style: 'currency', currency }).format(amount || 0);
}

function formatDate(value) {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleDateString(undefined, { dateStyle: 'medium' });
}

function statusClass(status) {
    if (status === 'open') {
        return 'bg-emerald-50 text-emerald-700 ring-emerald-600/20';
    }

    if (status === 'closed') {
        return 'bg-slate-100 text-slate-600 ring-slate-500/10';
    }

    return 'bg-amber-50 text-amber-700 ring-amber-600/20';
}

function resetForm() {
    form.value = emptyForm();
    editingUuid.value = null;
}

function startEdit(call) {
    editingUuid.value = call.uuid;
    form.value = {
        journal_issue_uuid: call.issue?.uuid || '',
        title: call.title || '',
        excerpt: call.excerpt || '',
        body: call.body || '',
        opens_at: call.opens_at ? call.opens_at.slice(0, 16) : '',
        closes_at: call.closes_at ? call.closes_at.slice(0, 16) : '',
        submission_fee: call.submission_fee ?? '',
        publication_fee: call.publication_fee ?? '',
        currency: call.currency || 'NGN',
        status: call.status || 'draft',
        topics: (call.topics || []).join(', '),
        editorial_board_member_ids: (call.editorial_board || []).map((member) => member.uuid),
    };
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function toggleBoardMember(uuid) {
    const ids = form.value.editorial_board_member_ids;
    form.value.editorial_board_member_ids = ids.includes(uuid)
        ? ids.filter((id) => id !== uuid)
        : [...ids, uuid];
}

async function save() {
    saving.value = true;
    message.value = '';
    error.value = '';

    const payload = {
        ...form.value,
        submission_fee: form.value.submission_fee === '' ? 0 : Number(form.value.submission_fee),
        publication_fee: form.value.publication_fee === '' ? 0 : Number(form.value.publication_fee),
        topics: form.value.topics
            ? form.value.topics.split(',').map((topic) => topic.trim()).filter(Boolean)
            : [],
        opens_at: form.value.opens_at || null,
        closes_at: form.value.closes_at || null,
    };

    try {
        if (editingUuid.value) {
            await getJournalClient().put(`/admin/calls-for-papers/${editingUuid.value}`, payload);
            message.value = 'Call for papers updated.';
        } else {
            await getJournalClient().post('/admin/calls-for-papers', payload);
            message.value = 'Call for papers created.';
        }

        resetForm();
        await loadCalls();
    } catch (e) {
        error.value = extractApiError(e, 'Unable to save call for papers.');
    } finally {
        saving.value = false;
    }
}

async function removeCall(call) {
    if (!window.confirm(`Delete "${call.title}"?`)) {
        return;
    }

    try {
        await getJournalClient().delete(`/admin/calls-for-papers/${call.uuid}`);
        message.value = 'Call for papers deleted.';
        if (editingUuid.value === call.uuid) {
            resetForm();
        }
        await loadCalls();
    } catch (e) {
        error.value = extractApiError(e, 'Unable to delete call for papers.');
    }
}
</script>

<template>
    <div>
        <AdminPageIntro description="Publish calls for submissions tied to a journal issue. Each issue belongs to a volume." />

        <div class="mb-4 flex flex-wrap gap-2">
            <RouterLink to="/admin/journal" class="btn-secondary !rounded-xl text-sm">← Submissions</RouterLink>
            <RouterLink to="/admin/journal/volumes-issues" class="btn-secondary !rounded-xl text-sm">Volumes & issues</RouterLink>
            <RouterLink to="/admin/journal/fees" class="btn-secondary !rounded-xl text-sm">Fees catalog</RouterLink>
            <RouterLink to="/admin/journal/editorial-board" class="btn-secondary !rounded-xl text-sm">
                <UserGroupIcon class="mr-1 inline size-4" />
                Editorial board
            </RouterLink>
        </div>

        <AdminAlert v-if="message" type="success">{{ message }}</AdminAlert>
        <AdminAlert v-if="error" type="error">{{ error }}</AdminAlert>

        <AdminPanel
            :title="editingUuid ? 'Edit call for papers' : 'New call for papers'"
            description="Set submission and publication fees, schedule, guidelines, and assign editorial board members."
            class="mb-6"
        >
            <div class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="label">Journal issue</label>
                        <select v-model="form.journal_issue_uuid" class="input !rounded-xl" required>
                            <option value="" disabled>Select volume & issue</option>
                            <option v-for="issue in issueOptions" :key="issue.uuid" :value="issue.uuid">
                                {{ issue.label }}
                            </option>
                        </select>
                        <p v-if="!issueOptions.length" class="mt-1 text-xs text-slate-500">
                            No issues yet.
                            <RouterLink to="/admin/journal/volumes-issues" class="font-medium text-brand-600 hover:underline">
                                Create volumes and issues
                            </RouterLink>
                            first.
                        </p>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="label">Title</label>
                        <input v-model="form.title" class="input !rounded-xl" placeholder="Special issue on educational research" required />
                    </div>
                    <div class="sm:col-span-2">
                        <label class="label">Short excerpt</label>
                        <input v-model="form.excerpt" class="input !rounded-xl" placeholder="Brief summary shown in listings" />
                    </div>
                    <div>
                        <label class="label">Opens at</label>
                        <input v-model="form.opens_at" type="datetime-local" class="input !rounded-xl" />
                    </div>
                    <div>
                        <label class="label">Closes at</label>
                        <input v-model="form.closes_at" type="datetime-local" class="input !rounded-xl" />
                    </div>
                    <div>
                        <label class="label">Submission fee</label>
                        <select
                            v-if="submissionFeeCatalog.length"
                            class="input !mb-2 !rounded-xl"
                            @change="applyCatalogFee('submission', $event.target.value)"
                        >
                            <option value="">Apply from catalog…</option>
                            <option v-for="fee in submissionFeeCatalog" :key="fee.uuid" :value="fee.uuid">
                                {{ fee.name }} — {{ formatMoney(fee.amount, fee.currency) }}
                            </option>
                        </select>
                        <input v-model="form.submission_fee" type="number" min="0" step="0.01" class="input !rounded-xl" placeholder="0" />
                    </div>
                    <div>
                        <label class="label">Publication fee</label>
                        <select
                            v-if="publicationFeeCatalog.length"
                            class="input !mb-2 !rounded-xl"
                            @change="applyCatalogFee('publication', $event.target.value)"
                        >
                            <option value="">Apply from catalog…</option>
                            <option v-for="fee in publicationFeeCatalog" :key="fee.uuid" :value="fee.uuid">
                                {{ fee.name }} — {{ formatMoney(fee.amount, fee.currency) }}
                            </option>
                        </select>
                        <input v-model="form.publication_fee" type="number" min="0" step="0.01" class="input !rounded-xl" placeholder="0" />
                    </div>
                    <div>
                        <label class="label">Currency</label>
                        <input v-model="form.currency" maxlength="3" class="input !rounded-xl uppercase" />
                    </div>
                    <div>
                        <label class="label">Status</label>
                        <select v-model="form.status" class="input !rounded-xl">
                            <option value="draft">Draft</option>
                            <option value="open">Open</option>
                            <option value="closed">Closed</option>
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="label">Topics (comma-separated)</label>
                        <input v-model="form.topics" class="input !rounded-xl" placeholder="education, policy, research" />
                    </div>
                </div>

                <div>
                    <label class="label">Guidelines & description</label>
                    <RichTextEditor v-model="form.body" placeholder="Submission guidelines, scope, formatting requirements…" min-height="10rem" />
                </div>

                <div>
                    <label class="label">Editorial board</label>
                    <p v-if="!boardMembers.length" class="mb-2 text-sm text-slate-500">
                        No board members yet.
                        <RouterLink to="/admin/journal/editorial-board" class="font-medium text-brand-600 hover:underline">Add members</RouterLink>
                        first.
                    </p>
                    <div v-else class="grid gap-2 sm:grid-cols-2">
                        <label
                            v-for="member in boardMembers"
                            :key="member.uuid"
                            class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 px-3 py-2.5 transition hover:border-brand-200 hover:bg-brand-50/40"
                        >
                            <input
                                type="checkbox"
                                class="mt-1 rounded border-slate-300 text-brand-600"
                                :checked="form.editorial_board_member_ids.includes(member.uuid)"
                                @change="toggleBoardMember(member.uuid)"
                            />
                            <span>
                                <span class="block font-medium text-slate-900">{{ member.name }}</span>
                                <span class="block text-xs text-slate-500">{{ member.role_title }}</span>
                            </span>
                        </label>
                    </div>
                    <p v-if="selectedBoardLabels.length" class="mt-2 text-xs text-slate-500">
                        Selected: {{ selectedBoardLabels.join(', ') }}
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <button class="btn-primary !rounded-xl" type="button" :disabled="saving || !form.title || !form.journal_issue_uuid" @click="save">
                        {{ saving ? 'Saving…' : editingUuid ? 'Update call' : 'Create call' }}
                    </button>
                    <button v-if="editingUuid" class="btn-secondary !rounded-xl" type="button" @click="resetForm">Cancel edit</button>
                </div>
            </div>
        </AdminPanel>

        <AdminPanel title="All calls for papers">
            <AdminEmptyState
                v-if="!loading && !calls.length"
                title="No calls yet"
                description="Create a call for papers to let members submit manuscripts against a specific announcement."
            >
                <template #icon><MegaphoneIcon class="size-6" /></template>
            </AdminEmptyState>

            <ul v-else class="-mx-4 divide-y divide-slate-100 sm:-mx-5">
                <li v-for="call in calls" :key="call.uuid" class="admin-list-item items-start">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="font-medium text-slate-900">{{ call.title }}</p>
                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset" :class="statusClass(call.status)">
                                {{ call.status }}
                            </span>
                            <span v-if="call.is_open" class="text-xs font-medium text-emerald-600">Accepting submissions</span>
                        </div>
                        <p v-if="call.excerpt" class="mt-1 text-sm text-slate-500">{{ call.excerpt }}</p>
                        <p v-if="call.issue?.label" class="mt-1 text-xs font-medium text-slate-600">{{ call.issue.label }}</p>
                        <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500">
                            <span>Submission: {{ formatMoney(call.submission_fee, call.currency) }}</span>
                            <span>Publication: {{ formatMoney(call.publication_fee, call.currency) }}</span>
                            <span>Opens {{ formatDate(call.opens_at) }}</span>
                            <span>Closes {{ formatDate(call.closes_at) }}</span>
                            <span v-if="call.submissions_count != null">{{ call.submissions_count }} submission(s)</span>
                        </div>
                    </div>
                    <div class="flex shrink-0 gap-2">
                        <button class="btn-secondary !rounded-xl text-sm" type="button" @click="startEdit(call)">
                            <PencilSquareIcon class="mr-1 inline size-4" />
                            Edit
                        </button>
                        <button class="btn-secondary !rounded-xl text-sm text-red-600" type="button" @click="removeCall(call)">
                            <TrashIcon class="inline size-4" />
                        </button>
                    </div>
                </li>
            </ul>
        </AdminPanel>
    </div>
</template>
