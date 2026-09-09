<script setup>
import { computed, ref, onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import {
    BookOpenIcon,
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

const volumes = ref([]);
const issues = ref([]);
const loadingVolumes = ref(true);
const loadingIssues = ref(true);
const savingVolume = ref(false);
const savingIssue = ref(false);
const message = ref('');
const error = ref('');
const editingVolumeUuid = ref(null);
const editingIssueUuid = ref(null);

const emptyVolumeForm = () => ({
    title: '',
    volume_number: '',
    year: new Date().getFullYear(),
    description: '',
    status: 'draft',
    sort_order: 0,
});

const emptyIssueForm = () => ({
    journal_volume_uuid: '',
    title: '',
    issue_number: '',
    description: '',
    status: 'draft',
    sort_order: 0,
});

const volumeForm = ref(emptyVolumeForm());
const issueForm = ref(emptyIssueForm());

const volumeOptions = computed(() =>
    volumes.value.map((volume) => ({
        uuid: volume.uuid,
        label: volume.label || `Vol. ${volume.volume_number} (${volume.year})`,
    })),
);

onMounted(async () => {
    await fetchAdminProfile();
    await Promise.all([loadVolumes(), loadIssues()]);
});

async function loadVolumes() {
    loadingVolumes.value = true;
    error.value = '';

    try {
        const { data } = await getJournalClient().get('/admin/volumes');
        volumes.value = data.data || [];
    } catch (e) {
        error.value = extractApiError(e, 'Unable to load volumes.');
    } finally {
        loadingVolumes.value = false;
    }
}

async function loadIssues() {
    loadingIssues.value = true;

    try {
        const { data } = await getJournalClient().get('/admin/issues');
        issues.value = data.data || [];
    } catch (e) {
        error.value = extractApiError(e, 'Unable to load issues.');
    } finally {
        loadingIssues.value = false;
    }
}

function statusClass(status) {
    if (status === 'open' || status === 'published') {
        return 'bg-emerald-50 text-emerald-700 ring-emerald-600/20';
    }

    if (status === 'closed' || status === 'archived') {
        return 'bg-slate-100 text-slate-600 ring-slate-500/10';
    }

    return 'bg-amber-50 text-amber-700 ring-amber-600/20';
}

function resetVolumeForm() {
    volumeForm.value = emptyVolumeForm();
    editingVolumeUuid.value = null;
}

function resetIssueForm() {
    issueForm.value = emptyIssueForm();
    editingIssueUuid.value = null;
}

function startEditVolume(volume) {
    editingVolumeUuid.value = volume.uuid;
    volumeForm.value = {
        title: volume.title || '',
        volume_number: volume.volume_number ?? '',
        year: volume.year ?? new Date().getFullYear(),
        description: volume.description || '',
        status: volume.status || 'draft',
        sort_order: volume.sort_order ?? 0,
    };
}

function startEditIssue(issue) {
    editingIssueUuid.value = issue.uuid;
    issueForm.value = {
        journal_volume_uuid: issue.journal_volume_uuid || issue.volume?.uuid || '',
        title: issue.title || '',
        issue_number: issue.issue_number ?? '',
        description: issue.description || '',
        status: issue.status || 'draft',
        sort_order: issue.sort_order ?? 0,
    };
}

async function saveVolume() {
    savingVolume.value = true;
    message.value = '';
    error.value = '';

    const payload = {
        ...volumeForm.value,
        volume_number: Number(volumeForm.value.volume_number),
        year: Number(volumeForm.value.year),
        sort_order: Number(volumeForm.value.sort_order) || 0,
    };

    try {
        if (editingVolumeUuid.value) {
            await getJournalClient().put(`/admin/volumes/${editingVolumeUuid.value}`, payload);
            message.value = 'Volume updated.';
        } else {
            await getJournalClient().post('/admin/volumes', payload);
            message.value = 'Volume created.';
        }

        resetVolumeForm();
        await loadVolumes();
    } catch (e) {
        error.value = extractApiError(e, 'Unable to save volume.');
    } finally {
        savingVolume.value = false;
    }
}

async function saveIssue() {
    savingIssue.value = true;
    message.value = '';
    error.value = '';

    const payload = {
        ...issueForm.value,
        issue_number: Number(issueForm.value.issue_number),
        sort_order: Number(issueForm.value.sort_order) || 0,
    };

    try {
        if (editingIssueUuid.value) {
            await getJournalClient().put(`/admin/issues/${editingIssueUuid.value}`, payload);
            message.value = 'Issue updated.';
        } else {
            await getJournalClient().post('/admin/issues', payload);
            message.value = 'Issue created.';
        }

        resetIssueForm();
        await Promise.all([loadVolumes(), loadIssues()]);
    } catch (e) {
        error.value = extractApiError(e, 'Unable to save issue.');
    } finally {
        savingIssue.value = false;
    }
}

async function removeVolume(volume) {
    if (!window.confirm(`Delete "${volume.label || volume.title}"? Issues in this volume will also be removed.`)) {
        return;
    }

    try {
        await getJournalClient().delete(`/admin/volumes/${volume.uuid}`);
        message.value = 'Volume deleted.';
        if (editingVolumeUuid.value === volume.uuid) {
            resetVolumeForm();
        }
        await Promise.all([loadVolumes(), loadIssues()]);
    } catch (e) {
        error.value = extractApiError(e, 'Unable to delete volume.');
    }
}

async function removeIssue(issue) {
    if (!window.confirm(`Delete "${issue.label || issue.title}"?`)) {
        return;
    }

    try {
        await getJournalClient().delete(`/admin/issues/${issue.uuid}`);
        message.value = 'Issue deleted.';
        if (editingIssueUuid.value === issue.uuid) {
            resetIssueForm();
        }
        await loadIssues();
    } catch (e) {
        error.value = extractApiError(e, 'Unable to delete issue.');
    }
}
</script>

<template>
    <div>
        <AdminPageIntro
            description="Organise the journal as volumes and issues. Each call for papers and submission is tied to an issue within a volume."
        />

        <div class="mb-4 flex flex-wrap gap-2">
            <RouterLink to="/admin/journal" class="btn-secondary !rounded-xl text-sm">← Submissions</RouterLink>
            <RouterLink to="/admin/journal/calls-for-papers" class="btn-secondary !rounded-xl text-sm">Calls for papers</RouterLink>
        </div>

        <AdminAlert v-if="message" type="success">{{ message }}</AdminAlert>
        <AdminAlert v-if="error" type="error">{{ error }}</AdminAlert>

        <div class="grid gap-6 xl:grid-cols-2">
            <AdminPanel
                :title="editingVolumeUuid ? 'Edit volume' : 'New volume'"
                description="Volumes group issues by year and volume number."
                class="mb-0"
            >
                <div class="space-y-4">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label class="label">Title</label>
                            <input v-model="volumeForm.title" class="input !rounded-xl" placeholder="Annual research collection" required />
                        </div>
                        <div>
                            <label class="label">Volume number</label>
                            <input v-model="volumeForm.volume_number" type="number" min="1" class="input !rounded-xl" required />
                        </div>
                        <div>
                            <label class="label">Year</label>
                            <input v-model="volumeForm.year" type="number" min="1900" max="2100" class="input !rounded-xl" required />
                        </div>
                        <div>
                            <label class="label">Status</label>
                            <select v-model="volumeForm.status" class="input !rounded-xl">
                                <option value="draft">Draft</option>
                                <option value="published">Published</option>
                                <option value="archived">Archived</option>
                            </select>
                        </div>
                        <div>
                            <label class="label">Sort order</label>
                            <input v-model="volumeForm.sort_order" type="number" min="0" class="input !rounded-xl" />
                        </div>
                        <div class="sm:col-span-2">
                            <label class="label">Description (optional)</label>
                            <textarea v-model="volumeForm.description" rows="2" class="input !rounded-xl" />
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button
                            class="btn-primary !rounded-xl"
                            type="button"
                            :disabled="savingVolume || !volumeForm.title || !volumeForm.volume_number"
                            @click="saveVolume"
                        >
                            <PlusIcon v-if="!editingVolumeUuid" class="mr-1 inline size-4" />
                            {{ savingVolume ? 'Saving…' : editingVolumeUuid ? 'Update volume' : 'Create volume' }}
                        </button>
                        <button v-if="editingVolumeUuid" class="btn-secondary !rounded-xl" type="button" @click="resetVolumeForm">
                            Cancel edit
                        </button>
                    </div>
                </div>
            </AdminPanel>

            <AdminPanel
                :title="editingIssueUuid ? 'Edit issue' : 'New issue'"
                description="Issues belong to a volume. Calls for papers are created against an issue."
                class="mb-0"
            >
                <div class="space-y-4">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label class="label">Volume</label>
                            <select v-model="issueForm.journal_volume_uuid" class="input !rounded-xl" required>
                                <option value="" disabled>Select a volume</option>
                                <option v-for="volume in volumeOptions" :key="volume.uuid" :value="volume.uuid">
                                    {{ volume.label }}
                                </option>
                            </select>
                            <p v-if="!volumeOptions.length" class="mt-1 text-xs text-slate-500">Create a volume first.</p>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="label">Title</label>
                            <input v-model="issueForm.title" class="input !rounded-xl" placeholder="Spring issue" required />
                        </div>
                        <div>
                            <label class="label">Issue number</label>
                            <input v-model="issueForm.issue_number" type="number" min="1" class="input !rounded-xl" required />
                        </div>
                        <div>
                            <label class="label">Status</label>
                            <select v-model="issueForm.status" class="input !rounded-xl">
                                <option value="draft">Draft</option>
                                <option value="open">Open</option>
                                <option value="closed">Closed</option>
                                <option value="published">Published</option>
                            </select>
                        </div>
                        <div>
                            <label class="label">Sort order</label>
                            <input v-model="issueForm.sort_order" type="number" min="0" class="input !rounded-xl" />
                        </div>
                        <div class="sm:col-span-2">
                            <label class="label">Description (optional)</label>
                            <textarea v-model="issueForm.description" rows="2" class="input !rounded-xl" />
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button
                            class="btn-primary !rounded-xl"
                            type="button"
                            :disabled="savingIssue || !issueForm.title || !issueForm.issue_number || !issueForm.journal_volume_uuid"
                            @click="saveIssue"
                        >
                            <PlusIcon v-if="!editingIssueUuid" class="mr-1 inline size-4" />
                            {{ savingIssue ? 'Saving…' : editingIssueUuid ? 'Update issue' : 'Create issue' }}
                        </button>
                        <button v-if="editingIssueUuid" class="btn-secondary !rounded-xl" type="button" @click="resetIssueForm">
                            Cancel edit
                        </button>
                    </div>
                </div>
            </AdminPanel>
        </div>

        <div class="mt-6 grid gap-6 xl:grid-cols-2">
            <AdminPanel title="Volumes">
                <AdminEmptyState
                    v-if="!loadingVolumes && !volumes.length"
                    title="No volumes yet"
                    description="Create a volume before adding issues and calls for papers."
                >
                    <template #icon><BookOpenIcon class="size-6" /></template>
                </AdminEmptyState>

                <ul v-else class="-mx-4 divide-y divide-slate-100 sm:-mx-5">
                    <li v-for="volume in volumes" :key="volume.uuid" class="admin-list-item items-start">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-medium text-slate-900">{{ volume.label || volume.title }}</p>
                                <span
                                    class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset"
                                    :class="statusClass(volume.status)"
                                >
                                    {{ volume.status }}
                                </span>
                            </div>
                            <p v-if="volume.description" class="mt-1 text-sm text-slate-500">{{ volume.description }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ volume.issues_count ?? 0 }} issue(s)</p>
                        </div>
                        <div class="flex shrink-0 gap-2">
                            <button class="btn-secondary !rounded-xl text-sm" type="button" @click="startEditVolume(volume)">
                                <PencilSquareIcon class="mr-1 inline size-4" />
                                Edit
                            </button>
                            <button class="btn-secondary !rounded-xl text-sm text-red-600" type="button" @click="removeVolume(volume)">
                                <TrashIcon class="inline size-4" />
                            </button>
                        </div>
                    </li>
                </ul>
            </AdminPanel>

            <AdminPanel title="Issues">
                <AdminEmptyState
                    v-if="!loadingIssues && !issues.length"
                    title="No issues yet"
                    description="Add issues to volumes, then create calls for papers against each issue."
                >
                    <template #icon><BookOpenIcon class="size-6" /></template>
                </AdminEmptyState>

                <ul v-else class="-mx-4 divide-y divide-slate-100 sm:-mx-5">
                    <li v-for="issue in issues" :key="issue.uuid" class="admin-list-item items-start">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-medium text-slate-900">{{ issue.label || issue.title }}</p>
                                <span
                                    class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset"
                                    :class="statusClass(issue.status)"
                                >
                                    {{ issue.status }}
                                </span>
                            </div>
                            <p v-if="issue.volume?.label" class="mt-1 text-xs text-slate-500">{{ issue.volume.label }}</p>
                            <p class="mt-1 text-xs text-slate-500">
                                {{ issue.calls_count ?? 0 }} call(s) · {{ issue.submissions_count ?? 0 }} submission(s)
                            </p>
                        </div>
                        <div class="flex shrink-0 gap-2">
                            <button class="btn-secondary !rounded-xl text-sm" type="button" @click="startEditIssue(issue)">
                                <PencilSquareIcon class="mr-1 inline size-4" />
                                Edit
                            </button>
                            <button class="btn-secondary !rounded-xl text-sm text-red-600" type="button" @click="removeIssue(issue)">
                                <TrashIcon class="inline size-4" />
                            </button>
                        </div>
                    </li>
                </ul>
            </AdminPanel>
        </div>
    </div>
</template>
