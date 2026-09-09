<script setup>
import { ref, onMounted } from 'vue';
import { TrashIcon, ArrowUpTrayIcon } from '@heroicons/vue/24/outline';
import AdminPageIntro from '../../components/admin/AdminPageIntro.vue';
import { useAuth } from '../../composables/useAuth';

const { getAdminClient } = useAuth();
const items = ref([]);
const loading = ref(true);
const saving = ref(false);
const message = ref('');
const error = ref('');
const search = ref('');
const fileInput = ref(null);
const batchId = ref('');

const form = ref({
    title: '',
    description: '',
    file_type: '',
    event_date: '',
    location: '',
});

onMounted(load);

async function load() {
    loading.value = true;
    try {
        const { data } = await getAdminClient().get('/media-library', {
            params: { search: search.value || undefined },
        });
        items.value = data.data || [];
        if (!batchId.value) {
            batchId.value = crypto.randomUUID();
        }
    } finally {
        loading.value = false;
    }
}

function resetForm() {
    form.value = { title: '', description: '', file_type: '', event_date: '', location: '' };
    if (fileInput.value) {
        fileInput.value.value = '';
    }
}

function onFileSelected(event) {
    const file = event.target.files?.[0];
    if (file && !form.value.title) {
        form.value.title = file.name.replace(/\.[^.]+$/, '');
    }
}

async function upload() {
    message.value = '';
    error.value = '';
    const file = fileInput.value?.files?.[0];
    if (!file) {
        error.value = 'Choose a file to upload.';
        return;
    }

    saving.value = true;
    try {
        const formData = new FormData();
        formData.append('file', file);
        if (form.value.title) formData.append('title', form.value.title);
        if (form.value.description) formData.append('description', form.value.description);
        if (form.value.file_type) formData.append('file_type', form.value.file_type);
        if (form.value.event_date) formData.append('event_date', form.value.event_date);
        if (form.value.location) formData.append('location', form.value.location);
        if (batchId.value) formData.append('batch_id', batchId.value);

        await getAdminClient().post('/media-library', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });

        message.value = 'Media uploaded to catalog.';
        resetForm();
        await load();
    } catch (err) {
        error.value = err.response?.data?.message || 'Upload failed.';
    } finally {
        saving.value = false;
    }
}

async function remove(item) {
    if (!window.confirm(`Remove "${item.title}" from the media library?`)) {
        return;
    }

    await getAdminClient().delete(`/media-library/${item.uuid}`);
    message.value = 'Media item removed.';
    await load();
}

function formatSize(mb) {
    if (!mb) return '—';
    return mb < 1 ? `${Math.round(mb * 1024)} KB` : `${mb} MB`;
}
</script>

<template>
    <div>
        <AdminPageIntro description="Upload images, documents, and videos for the public media catalog. Group related files with the same batch ID." />

        <p v-if="message" class="mb-4 text-sm text-green-600">{{ message }}</p>
        <p v-if="error" class="mb-4 text-sm text-red-600">{{ error }}</p>

        <div class="mb-6 overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-5 py-4">
                <h2 class="font-display text-lg font-bold text-slate-900">Upload Media</h2>
            </div>
            <form class="space-y-4 px-5 py-5" @submit.prevent="upload">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Title</label>
                        <input v-model="form.title" type="text" class="input !rounded-xl" placeholder="Gallery title or file name" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">File type</label>
                        <select v-model="form.file_type" class="input !rounded-xl">
                            <option value="">Auto-detect</option>
                            <option value="image">Image</option>
                            <option value="document">Document</option>
                            <option value="video">Video</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Description</label>
                    <textarea v-model="form.description" rows="2" class="input !rounded-xl" placeholder="Optional caption or summary" />
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Event date</label>
                        <input v-model="form.event_date" type="date" class="input !rounded-xl" />
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-slate-700">Location</label>
                        <input v-model="form.location" type="text" class="input !rounded-xl" placeholder="Optional location" />
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Batch ID</label>
                    <input v-model="batchId" type="text" class="input !rounded-xl font-mono text-sm" />
                    <p class="mt-1 text-xs text-slate-500">Use the same batch ID to group multiple uploads into one catalog entry.</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">File</label>
                    <input ref="fileInput" type="file" class="input !rounded-xl !py-2" @change="onFileSelected" />
                </div>

                <button type="submit" class="btn-primary !rounded-xl" :disabled="saving">
                    <ArrowUpTrayIcon class="mr-1.5 inline size-4" />
                    {{ saving ? 'Uploading...' : 'Upload to catalog' }}
                </button>
            </form>
        </div>

        <div class="mb-4">
            <input
                v-model="search"
                type="search"
                placeholder="Search media..."
                class="input max-w-md !rounded-xl !py-2.5"
                @keyup.enter="load"
            />
        </div>

        <div v-if="loading" class="rounded-2xl border border-dashed border-slate-200 bg-white px-8 py-14 text-center text-slate-500">
            Loading media library...
        </div>

        <div v-else-if="!items.length" class="rounded-2xl border border-dashed border-slate-200 bg-white px-8 py-14 text-center text-slate-500">
            No media catalog items yet.
        </div>

        <div v-else class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <article
                v-for="item in items"
                :key="item.uuid"
                class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm"
            >
                <div class="border-b border-slate-100 px-4 py-3">
                    <p class="font-medium text-slate-900">{{ item.title }}</p>
                    <p class="mt-1 text-xs capitalize text-slate-500">
                        {{ item.file_type }} · {{ formatSize(item.file_size_mb) }}
                    </p>
                </div>
                <div class="space-y-2 px-4 py-3 text-sm text-slate-600">
                    <p v-if="item.description" class="line-clamp-2">{{ item.description }}</p>
                    <p v-if="item.location" class="text-xs text-slate-500">{{ item.location }}</p>
                    <p class="truncate font-mono text-xs text-slate-400">Batch: {{ item.batch_id }}</p>
                </div>
                <div class="flex items-center justify-between border-t border-slate-100 px-4 py-3">
                    <a :href="item.file_url" target="_blank" rel="noopener" class="text-sm text-institutional hover:underline">Preview</a>
                    <button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-red-50 hover:text-red-600" @click="remove(item)">
                        <TrashIcon class="size-4" />
                    </button>
                </div>
            </article>
        </div>
    </div>
</template>
