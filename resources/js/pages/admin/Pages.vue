<script setup>
import { ref, onMounted } from 'vue';
import { PencilSquareIcon, PlusIcon, TrashIcon } from '@heroicons/vue/24/outline';
import AdminPageIntro from '../../components/admin/AdminPageIntro.vue';
import { useAuth } from '../../composables/useAuth';

const { getAdminClient } = useAuth();
const pages = ref([]);
const loading = ref(true);
const saving = ref(false);
const message = ref('');
const error = ref('');
const editingUuid = ref(null);
const search = ref('');

const emptyForm = () => ({
    title: '',
    body: '',
    status: 'draft',
    visibility: 'public',
    sort_order: 0,
});

const form = ref(emptyForm());

onMounted(load);

async function load() {
    loading.value = true;
    try {
        const { data } = await getAdminClient().get('/pages', {
            params: { search: search.value || undefined },
        });
        pages.value = data.data || [];
    } finally {
        loading.value = false;
    }
}

function resetForm() {
    form.value = emptyForm();
    editingUuid.value = null;
    error.value = '';
}

function startEdit(page) {
    editingUuid.value = page.uuid;
    form.value = {
        title: page.title || '',
        body: page.body || '',
        status: page.status || 'draft',
        visibility: page.visibility || 'public',
        sort_order: page.sort_order ?? 0,
    };
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

async function save() {
    message.value = '';
    error.value = '';
    saving.value = true;

    try {
        const client = getAdminClient();
        if (editingUuid.value) {
            await client.put(`/pages/${editingUuid.value}`, form.value);
            message.value = 'Page updated.';
        } else {
            await client.post('/pages', form.value);
            message.value = 'Page created.';
        }
        resetForm();
        await load();
    } catch (err) {
        error.value = err.response?.data?.message || 'Unable to save page.';
    } finally {
        saving.value = false;
    }
}

async function remove(page) {
    if (!window.confirm(`Delete page "${page.title}"?`)) {
        return;
    }

    await getAdminClient().delete(`/pages/${page.uuid}`);
    message.value = 'Page deleted.';
    if (editingUuid.value === page.uuid) {
        resetForm();
    }
    await load();
}
</script>

<template>
    <div>
        <AdminPageIntro description="Manage static CMS pages such as About, Policies, and other site content served via the public pages API." />

        <p v-if="message" class="mb-4 text-sm text-green-600">{{ message }}</p>
        <p v-if="error" class="mb-4 text-sm text-red-600">{{ error }}</p>

        <div class="mb-6 overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                <h2 class="font-display text-lg font-bold text-slate-900">
                    {{ editingUuid ? 'Edit Page' : 'New Page' }}
                </h2>
                <button v-if="editingUuid" type="button" class="btn-secondary !rounded-xl text-sm" @click="resetForm">
                    Cancel edit
                </button>
            </div>

            <form class="space-y-4 px-5 py-5" @submit.prevent="save">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Title</label>
                    <input v-model="form.title" type="text" required class="input !rounded-xl" />
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Body</label>
                    <textarea v-model="form.body" rows="8" required class="input !rounded-xl" placeholder="HTML supported" />
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Status</label>
                        <select v-model="form.status" class="input !rounded-xl">
                            <option value="draft">Draft</option>
                            <option value="published">Published</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Visibility</label>
                        <select v-model="form.visibility" class="input !rounded-xl">
                            <option value="public">Public</option>
                            <option value="members_only">Members only</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Sort order</label>
                        <input v-model.number="form.sort_order" type="number" min="0" class="input !rounded-xl" />
                    </div>
                </div>

                <button type="submit" class="btn-primary !rounded-xl" :disabled="saving">
                    <PlusIcon v-if="!editingUuid" class="mr-1.5 inline size-4" />
                    {{ saving ? 'Saving...' : editingUuid ? 'Save changes' : 'Create page' }}
                </button>
            </form>
        </div>

        <div class="mb-4">
            <input
                v-model="search"
                type="search"
                placeholder="Search pages..."
                class="input max-w-md !rounded-xl !py-2.5"
                @keyup.enter="load"
            />
        </div>

        <div v-if="loading" class="rounded-2xl border border-dashed border-slate-200 bg-white px-8 py-14 text-center text-slate-500">
            Loading pages...
        </div>

        <div v-else class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm">
            <div v-if="!pages.length" class="admin-panel-body">
                <div class="empty-state !border-none !bg-transparent">No pages yet.</div>
            </div>
            <ul v-else class="divide-y divide-slate-100">
                <li v-for="page in pages" :key="page.uuid" class="flex items-center gap-3 px-5 py-4">
                    <div class="min-w-0 flex-1">
                        <p class="font-medium text-slate-900">{{ page.title }}</p>
                        <p class="mt-1 text-xs text-slate-500">
                            /{{ page.slug }} · {{ page.status }} · order {{ page.sort_order }}
                        </p>
                    </div>
                    <button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100" @click="startEdit(page)">
                        <PencilSquareIcon class="size-4" />
                    </button>
                    <button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-red-50 hover:text-red-600" @click="remove(page)">
                        <TrashIcon class="size-4" />
                    </button>
                </li>
            </ul>
        </div>
    </div>
</template>
