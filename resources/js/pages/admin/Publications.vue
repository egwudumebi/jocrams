<script setup>
import { ref, onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import {
    DocumentTextIcon,
    PencilSquareIcon,
    PlusIcon,
    TrashIcon,
} from '@heroicons/vue/24/outline';
import AdminPageIntro from '../../components/admin/AdminPageIntro.vue';
import { useAuth } from '../../composables/useAuth';
import { publicApi } from '../../api/client';

const { getAdminClient } = useAuth();
const publications = ref([]);
const articles = ref([]);
const membershipTiers = ref([]);
const search = ref('');
const loading = ref(true);
const saving = ref(false);
const message = ref('');
const error = ref('');
const editingUuid = ref(null);
const fileInput = ref(null);

const emptyForm = () => ({
    title: '',
    description: '',
    visibility: 'public',
    is_active: true,
    allowed_tier_ids: [],
});

const form = ref(emptyForm());
const uploadFile = ref(null);

onMounted(async () => {
    await Promise.all([load(), loadMembershipTiers()]);
});

async function loadMembershipTiers() {
    try {
        const { data } = await publicApi().get('/membership-tiers');
        membershipTiers.value = data.data || [];
    } catch {
        membershipTiers.value = [];
    }
}

async function load() {
    loading.value = true;
    try {
        const [downloadsResponse, newsResponse] = await Promise.all([
            getAdminClient().get('/downloads', { params: { search: search.value || undefined } }),
            getAdminClient().get('/news-articles'),
        ]);

        publications.value = downloadsResponse.data.data || [];
        articles.value = newsResponse.data.data || [];
    } finally {
        loading.value = false;
    }
}

function resetForm() {
    form.value = emptyForm();
    editingUuid.value = null;
    uploadFile.value = null;
    error.value = '';
    if (fileInput.value) {
        fileInput.value.value = '';
    }
}

function startEdit(item) {
    editingUuid.value = item.uuid;
    form.value = {
        title: item.title || '',
        description: item.description || '',
        visibility: item.visibility?.value || item.visibility || 'public',
        is_active: item.is_active ?? true,
        allowed_tier_ids: [...(item.allowed_tier_ids || [])],
    };
    uploadFile.value = null;
    if (fileInput.value) {
        fileInput.value.value = '';
    }
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function onFileSelected(event) {
    uploadFile.value = event.target.files?.[0] || null;
}

function toggleTier(tierId) {
    const ids = form.value.allowed_tier_ids;
    const index = ids.indexOf(tierId);
    if (index === -1) {
        ids.push(tierId);
    } else {
        ids.splice(index, 1);
    }
}

function buildPayload() {
    return {
        title: form.value.title,
        description: form.value.description || null,
        visibility: form.value.visibility,
        is_active: form.value.is_active,
        allowed_tier_ids: form.value.visibility === 'tier_specific' ? form.value.allowed_tier_ids : [],
    };
}

async function save() {
    message.value = '';
    error.value = '';
    saving.value = true;

    try {
        const payload = buildPayload();
        let response;
        const client = getAdminClient();

        if (uploadFile.value || !editingUuid.value) {
            const formData = new FormData();
            Object.entries(payload).forEach(([key, value]) => {
                if (value === null || value === undefined) return;
                if (Array.isArray(value)) {
                    formData.append(key, JSON.stringify(value));
                } else {
                    formData.append(key, String(value));
                }
            });

            if (uploadFile.value) {
                formData.append('file', uploadFile.value);
            }

            if (editingUuid.value) {
                formData.append('_method', 'PUT');
                response = await client.post(`/downloads/${editingUuid.value}`, formData, {
                    headers: { 'Content-Type': 'multipart/form-data' },
                });
            } else {
                if (!uploadFile.value) {
                    error.value = 'Please choose a PDF or Word file to upload.';
                    return;
                }
                response = await client.post('/downloads', formData, {
                    headers: { 'Content-Type': 'multipart/form-data' },
                });
            }
        } else {
            response = await client.put(`/downloads/${editingUuid.value}`, payload);
        }

        message.value = editingUuid.value ? 'Library file updated.' : 'Library file uploaded.';
        resetForm();
        await load();
        return response;
    } catch (err) {
        error.value = err.response?.data?.message || 'Unable to save library file.';
    } finally {
        saving.value = false;
    }
}

async function toggleActive(item) {
    await getAdminClient().put(`/downloads/${item.uuid}`, {
        is_active: !item.is_active,
    });
    message.value = item.is_active ? 'Library file deactivated.' : 'Library file reactivated.';
    await load();
}

async function remove(item) {
    if (!window.confirm(`Remove "${item.title}" from the library?`)) {
        return;
    }

    await getAdminClient().delete(`/downloads/${item.uuid}`);
    message.value = 'Library file removed.';
    if (editingUuid.value === item.uuid) {
        resetForm();
    }
    await load();
}

function formatDate(value) {
    return value ? new Date(value).toLocaleDateString() : '—';
}

function fileType(mimeType) {
    if (!mimeType) {
        return 'File';
    }

    if (mimeType.includes('pdf')) {
        return 'PDF';
    }

    if (mimeType.includes('word') || mimeType.includes('document')) {
        return 'Word';
    }

    return 'File';
}

function visibilityLabel(value) {
    const normalized = value?.value || value;
    if (normalized === 'members_only') return 'Members only';
    if (normalized === 'tier_specific') return 'Tier specific';
    return 'Public';
}
</script>

<template>
    <div>
        <AdminPageIntro description="Upload downloadable library files (PDFs, documents) and review published news articles. Library files are served via signed URLs; news articles are managed separately." />

        <p v-if="message" class="mb-4 text-sm text-green-600">{{ message }}</p>
        <p v-if="error" class="mb-4 text-sm text-red-600">{{ error }}</p>

        <div class="mb-6 overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                <h2 class="font-display text-lg font-bold text-slate-900">
                    {{ editingUuid ? 'Edit Library File' : 'Upload Library File' }}
                </h2>
                <button v-if="editingUuid" type="button" class="btn-secondary !rounded-xl text-sm" @click="resetForm">
                    Cancel edit
                </button>
            </div>

            <form class="space-y-4 px-5 py-5" @submit.prevent="save">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Title</label>
                    <input v-model="form.title" type="text" required class="input !rounded-xl" placeholder="Annual report, handbook, policy..." />
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Description</label>
                    <textarea v-model="form.description" rows="3" class="input !rounded-xl" placeholder="Optional summary for members and the public." />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Visibility</label>
                        <select v-model="form.visibility" class="input !rounded-xl">
                            <option value="public">Public</option>
                            <option value="members_only">Members only</option>
                            <option value="tier_specific">Specific membership tiers</option>
                        </select>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Status</label>
                        <select v-model="form.is_active" class="input !rounded-xl">
                            <option :value="true">Active</option>
                            <option :value="false">Inactive</option>
                        </select>
                    </div>
                </div>

                <div v-if="form.visibility === 'tier_specific'" class="rounded-xl border border-slate-200 p-4">
                    <p class="mb-3 text-sm font-medium text-slate-700">Allowed membership tiers</p>
                    <div v-if="!membershipTiers.length" class="text-sm text-slate-500">No membership tiers available.</div>
                    <div v-else class="flex flex-wrap gap-3">
                        <label
                            v-for="tier in membershipTiers"
                            :key="tier.id"
                            class="flex cursor-pointer items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm"
                        >
                            <input
                                type="checkbox"
                                :checked="form.allowed_tier_ids.includes(tier.id)"
                                @change="toggleTier(tier.id)"
                            />
                            <span>{{ tier.name }}</span>
                        </label>
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">
                        File {{ editingUuid ? '(optional — leave blank to keep current)' : '' }}
                    </label>
                    <input
                        ref="fileInput"
                        type="file"
                        accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                        class="input !rounded-xl !py-2"
                        @change="onFileSelected"
                    />
                    <p class="mt-1 text-xs text-slate-500">PDF or Word documents up to 25 MB.</p>
                </div>

                <div class="flex flex-wrap gap-3 pt-2">
                    <button type="submit" class="btn-primary !rounded-xl" :disabled="saving">
                        <PlusIcon v-if="!editingUuid" class="mr-1.5 inline size-4" />
                        {{ saving ? 'Saving...' : editingUuid ? 'Save changes' : 'Upload file' }}
                    </button>
                </div>
            </form>
        </div>

        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <input
                v-model="search"
                type="search"
                placeholder="Search library files..."
                class="input max-w-md !rounded-xl !py-2.5"
                @keyup.enter="load"
            />
            <RouterLink to="/admin/news" class="btn-secondary !rounded-xl">Manage News Articles</RouterLink>
        </div>

        <div v-if="loading" class="rounded-2xl border border-dashed border-slate-200 bg-white px-8 py-14 text-center text-slate-500">
            Loading publications...
        </div>

        <div v-else class="grid gap-6 xl:grid-cols-2">
            <section class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-5 py-4">
                    <h2 class="font-display text-lg font-bold text-slate-900">Library Files</h2>
                    <p class="mt-1 text-sm text-slate-500">Downloadable documents for members and the public.</p>
                </div>
                <div v-if="!publications.length" class="px-5 py-10 text-center text-sm text-slate-500">No library files uploaded yet.</div>
                <ul v-else class="divide-y divide-slate-100">
                    <li v-for="item in publications" :key="item.uuid" class="flex items-start gap-3 px-5 py-4">
                        <span class="rounded-lg bg-brand-50 px-2 py-1 text-xs font-bold text-institutional">{{ fileType(item.media_file?.mime_type) }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="font-medium text-slate-900">{{ item.title }}</p>
                            <p class="mt-1 text-xs text-slate-500">
                                {{ visibilityLabel(item.visibility) }}
                                · {{ item.is_active ? 'Active' : 'Inactive' }}
                                · Updated {{ formatDate(item.updated_at) }}
                            </p>
                        </div>
                        <div class="flex shrink-0 gap-1">
                            <button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-institutional" title="Edit" @click="startEdit(item)">
                                <PencilSquareIcon class="size-4" />
                            </button>
                            <button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-amber-600" :title="item.is_active ? 'Deactivate' : 'Activate'" @click="toggleActive(item)">
                                {{ item.is_active ? 'Off' : 'On' }}
                            </button>
                            <button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-red-50 hover:text-red-600" title="Delete" @click="remove(item)">
                                <TrashIcon class="size-4" />
                            </button>
                        </div>
                    </li>
                </ul>
            </section>

            <section class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-5 py-4">
                    <h2 class="font-display text-lg font-bold text-slate-900">News & Articles</h2>
                    <p class="mt-1 text-sm text-slate-500">Editorial content — not library downloads.</p>
                </div>
                <div v-if="!articles.length" class="px-5 py-10 text-center text-sm text-slate-500">No articles created yet.</div>
                <ul v-else class="divide-y divide-slate-100">
                    <li v-for="article in articles" :key="article.uuid" class="flex items-start gap-3 px-5 py-4">
                        <DocumentTextIcon class="mt-0.5 size-5 shrink-0 text-institutional" />
                        <div class="min-w-0 flex-1">
                            <p class="font-medium text-slate-900">{{ article.title }}</p>
                            <p class="mt-1 text-xs capitalize text-slate-500">{{ article.status }} · {{ formatDate(article.published_at || article.updated_at) }}</p>
                        </div>
                    </li>
                </ul>
            </section>
        </div>
    </div>
</template>
