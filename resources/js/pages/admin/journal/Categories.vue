<script setup>
import { ref, onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import { PlusIcon, TagIcon, TrashIcon } from '@heroicons/vue/24/outline';
import AdminAlert from '../../../components/admin/AdminAlert.vue';
import AdminEmptyState from '../../../components/admin/AdminEmptyState.vue';
import AdminPageIntro from '../../../components/admin/AdminPageIntro.vue';
import AdminPanel from '../../../components/admin/AdminPanel.vue';
import { useAuth } from '../../../composables/useAuth';
import { extractApiError } from '../../../utils/apiError';

const { getJournalClient, fetchAdminProfile } = useAuth();

const categories = ref([]);
const loading = ref(true);
const saving = ref(false);
const message = ref('');
const error = ref('');

const form = ref({
    name: '',
    description: '',
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
        const { data } = await getJournalClient().get('/admin/categories');
        categories.value = data.data || [];
    } catch (e) {
        error.value = extractApiError(e, 'Unable to load categories.');
    } finally {
        loading.value = false;
    }
}

async function save() {
    saving.value = true;
    message.value = '';
    error.value = '';

    try {
        await getJournalClient().post('/admin/categories', {
            ...form.value,
            sort_order: Number(form.value.sort_order) || 0,
        });
        message.value = 'Category added.';
        form.value = { name: '', description: '', sort_order: 0, is_active: true };
        await load();
    } catch (e) {
        error.value = extractApiError(e, 'Unable to add category.');
    } finally {
        saving.value = false;
    }
}

async function toggleActive(category) {
    try {
        await getJournalClient().put(`/admin/categories/${category.uuid}`, {
            is_active: !category.is_active,
        });
        await load();
    } catch (e) {
        error.value = extractApiError(e, 'Unable to update category.');
    }
}

async function removeCategory(category) {
    if (!window.confirm(`Remove "${category.name}"?`)) {
        return;
    }

    try {
        await getJournalClient().delete(`/admin/categories/${category.uuid}`);
        message.value = 'Category removed.';
        await load();
    } catch (e) {
        error.value = extractApiError(e, 'Unable to remove category.');
    }
}
</script>

<template>
    <div>
        <AdminPageIntro description="Define manuscript categories members choose from when submitting to the journal." />

        <div class="mb-4 flex flex-wrap gap-2">
            <RouterLink to="/admin/journal" class="btn-secondary !rounded-xl text-sm">← Submissions</RouterLink>
            <RouterLink to="/admin/journal/calls-for-papers" class="btn-secondary !rounded-xl text-sm">Calls for papers</RouterLink>
            <RouterLink to="/admin/journal/fees" class="btn-secondary !rounded-xl text-sm">Fees catalog</RouterLink>
        </div>

        <AdminAlert v-if="message" type="success">{{ message }}</AdminAlert>
        <AdminAlert v-if="error" type="error">{{ error }}</AdminAlert>

        <AdminPanel title="Add category" class="mb-6">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label">Name</label>
                    <input v-model="form.name" class="input !rounded-xl" placeholder="e.g. Research, Education" required />
                </div>
                <div>
                    <label class="label">Sort order</label>
                    <input v-model="form.sort_order" type="number" min="0" class="input !rounded-xl" />
                </div>
                <div class="sm:col-span-2">
                    <label class="label">Description (optional)</label>
                    <input v-model="form.description" class="input !rounded-xl" placeholder="Short hint shown to admins" />
                </div>
            </div>
            <button class="btn-primary mt-4 !rounded-xl" type="button" :disabled="saving || !form.name" @click="save">
                <PlusIcon class="mr-1 inline size-4" />
                {{ saving ? 'Adding…' : 'Add category' }}
            </button>
        </AdminPanel>

        <AdminPanel title="Manuscript categories">
            <AdminEmptyState
                v-if="!loading && !categories.length"
                title="No categories yet"
                description="Add categories here to populate the dropdown on the member submission form."
            >
                <template #icon><TagIcon class="size-6" /></template>
            </AdminEmptyState>

            <ul v-else class="-mx-4 divide-y divide-slate-100 sm:-mx-5">
                <li v-for="category in categories" :key="category.uuid" class="admin-list-item items-start">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="font-medium text-slate-900">{{ category.name }}</p>
                            <span
                                class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset"
                                :class="category.is_active ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/20' : 'bg-slate-100 text-slate-500 ring-slate-500/10'"
                            >
                                {{ category.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>
                        <p v-if="category.description" class="mt-1 text-sm text-slate-500">{{ category.description }}</p>
                        <p class="mt-1 text-xs text-slate-400">Sort order: {{ category.sort_order }}</p>
                    </div>
                    <div class="flex shrink-0 gap-2">
                        <button class="btn-secondary !rounded-xl text-sm" type="button" @click="toggleActive(category)">
                            {{ category.is_active ? 'Deactivate' : 'Activate' }}
                        </button>
                        <button class="btn-secondary !rounded-xl text-sm text-red-600" type="button" @click="removeCategory(category)">
                            <TrashIcon class="inline size-4" />
                        </button>
                    </div>
                </li>
            </ul>
        </AdminPanel>
    </div>
</template>
