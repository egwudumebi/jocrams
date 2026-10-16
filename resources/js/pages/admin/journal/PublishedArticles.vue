<script setup>
import { onMounted, ref } from 'vue';
import { DocumentTextIcon, PlusIcon, TrashIcon } from '@heroicons/vue/24/outline';
import AdminAlert from '../../../components/admin/AdminAlert.vue';
import AdminEmptyState from '../../../components/admin/AdminEmptyState.vue';
import AdminPageIntro from '../../../components/admin/AdminPageIntro.vue';
import AdminPanel from '../../../components/admin/AdminPanel.vue';
import { useAuth } from '../../../composables/useAuth';

const { getAdminClient } = useAuth();

const articles = ref([]);
const message = ref('');
const error = ref('');
const saving = ref(false);
const editingUuid = ref(null);
const pdfFile = ref(null);

const emptyAuthor = () => ({ name: '', affiliation: '', orcid: '' });

const form = ref(blankForm());

function blankForm() {
    return {
        title: '',
        slug: '',
        authors: [emptyAuthor()],
        abstract: '',
        keywords: '',
        article_type: 'Research Article',
        received_at: '',
        revised_at: '',
        accepted_at: '',
        published_at: '',
        volume_number: 1,
        issue_number: 1,
        page_start: '',
        page_end: '',
        citation: '',
        copyright: '© JOCRAMS / Scholars in Communication and Media Advancement Initiative (SICAMA).',
        license: 'Creative Commons Attribution 4.0 International (CC BY 4.0)',
        doi: '',
        status: 'published',
        sort_order: 0,
    };
}

onMounted(load);

async function load() {
    const { data } = await getAdminClient().get('/published-articles', { params: { per_page: 50 } });
    articles.value = data.data || [];
}

function addAuthor() {
    form.value.authors.push(emptyAuthor());
}

function removeAuthor(index) {
    if (form.value.authors.length === 1) {
        return;
    }
    form.value.authors.splice(index, 1);
}

function onPdfChange(event) {
    pdfFile.value = event.target.files?.[0] || null;
}

function resetForm() {
    editingUuid.value = null;
    form.value = blankForm();
    pdfFile.value = null;
}

function editArticle(article) {
    editingUuid.value = article.uuid;
    form.value = {
        title: article.title || '',
        slug: article.slug || '',
        authors: (article.authors?.length ? article.authors : [emptyAuthor()]).map((a) => ({
            name: a.name || '',
            affiliation: a.affiliation || '',
            orcid: a.orcid || '',
        })),
        abstract: article.abstract || '',
        keywords: article.keywords || '',
        article_type: article.article_type || 'Research Article',
        received_at: article.received_at || '',
        revised_at: article.revised_at || '',
        accepted_at: article.accepted_at || '',
        published_at: article.published_at || '',
        volume_number: article.volume_number || '',
        issue_number: article.issue_number || '',
        page_start: article.page_start || '',
        page_end: article.page_end || '',
        citation: article.citation || '',
        copyright: article.copyright || '',
        license: article.license || '',
        doi: article.doi || '',
        status: article.status || 'draft',
        sort_order: article.sort_order || 0,
    };
    pdfFile.value = null;
    message.value = '';
    error.value = '';
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function buildPayload() {
    const payload = new FormData();
    const values = { ...form.value };

    Object.entries(values).forEach(([key, value]) => {
        if (key === 'authors') {
            payload.append('authors', JSON.stringify(value));
            return;
        }
        if (value === null || value === undefined) {
            return;
        }
        payload.append(key, String(value));
    });

    if (pdfFile.value) {
        payload.append('pdf', pdfFile.value);
    }

    return payload;
}

async function save() {
    saving.value = true;
    message.value = '';
    error.value = '';

    try {
        const payload = buildPayload();
        if (editingUuid.value) {
            await getAdminClient().post(`/published-articles/${editingUuid.value}`, payload, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });
            message.value = 'Article updated.';
        } else {
            await getAdminClient().post('/published-articles', payload, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });
            message.value = 'Article published.';
        }
        resetForm();
        await load();
    } catch (err) {
        error.value = err.response?.data?.message
            || Object.values(err.response?.data?.errors || {})?.[0]?.[0]
            || 'Unable to save article.';
    } finally {
        saving.value = false;
    }
}

async function remove(article) {
    if (article.is_crossref_sample) {
        error.value = 'Crossref sample articles are permanent and cannot be deleted.';
        return;
    }
    if (!confirm(`Delete “${article.title}”?`)) {
        return;
    }
    try {
        await getAdminClient().delete(`/published-articles/${article.uuid}`);
        message.value = 'Article deleted.';
        await load();
    } catch (err) {
        error.value = err.response?.data?.message || 'Unable to delete article.';
    }
}
</script>

<template>
    <div>
        <AdminPageIntro description="Publish Crossref-ready article landing pages. Enter metadata, upload the PDF, and the site generates a permanent public URL." />

        <AdminAlert v-if="message" type="success" class="mb-4">{{ message }}</AdminAlert>
        <AdminAlert v-if="error" type="error" class="mb-4">{{ error }}</AdminAlert>

        <AdminPanel
            :title="editingUuid ? 'Edit article' : 'Publish article'"
            description="One form creates the reusable landing page. Future DOIs should resolve to /articles/{slug}."
            class="mb-6"
        >
            <div class="space-y-5">
                <div>
                    <label class="label">Article title</label>
                    <input v-model="form.title" class="input !rounded-xl" placeholder="Full article title" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="label">Permanent slug (URL)</label>
                        <input v-model="form.slug" class="input !rounded-xl" placeholder="auto-from-title-if-blank" />
                        <p class="mt-1 text-xs text-slate-500">Public URL: /articles/{{ form.slug || 'your-slug' }}</p>
                    </div>
                    <div>
                        <label class="label">Article type</label>
                        <input v-model="form.article_type" class="input !rounded-xl" />
                    </div>
                </div>

                <div>
                    <div class="mb-2 flex items-center justify-between">
                        <label class="label !mb-0">Authors</label>
                        <button type="button" class="btn-secondary !rounded-xl !px-3 !py-1.5 text-xs" @click="addAuthor">
                            <PlusIcon class="mr-1 inline size-3.5" /> Add author
                        </button>
                    </div>
                    <div v-for="(author, index) in form.authors" :key="index" class="mb-3 grid gap-3 rounded-xl border border-slate-100 p-3 sm:grid-cols-[1fr_1fr_auto]">
                        <input v-model="author.name" class="input !rounded-xl" placeholder="Author name" />
                        <input v-model="author.affiliation" class="input !rounded-xl" placeholder="Affiliation" />
                        <div class="flex gap-2 sm:col-span-2 lg:col-span-1 lg:block">
                            <input v-model="author.orcid" class="input !rounded-xl" placeholder="ORCID (optional)" />
                            <button type="button" class="btn-secondary !rounded-xl text-xs lg:mt-2" @click="removeAuthor(index)">
                                Remove
                            </button>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="label">Abstract</label>
                    <textarea v-model="form.abstract" rows="5" class="input !rounded-xl" />
                </div>

                <div>
                    <label class="label">Keywords (comma-separated)</label>
                    <input v-model="form.keywords" class="input !rounded-xl" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <label class="label">Received</label>
                        <input v-model="form.received_at" type="date" class="input !rounded-xl" />
                    </div>
                    <div>
                        <label class="label">Revised</label>
                        <input v-model="form.revised_at" type="date" class="input !rounded-xl" />
                    </div>
                    <div>
                        <label class="label">Accepted</label>
                        <input v-model="form.accepted_at" type="date" class="input !rounded-xl" />
                    </div>
                    <div>
                        <label class="label">Published</label>
                        <input v-model="form.published_at" type="date" class="input !rounded-xl" />
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <label class="label">Volume</label>
                        <input v-model="form.volume_number" type="number" min="1" class="input !rounded-xl" />
                    </div>
                    <div>
                        <label class="label">Issue</label>
                        <input v-model="form.issue_number" type="number" min="1" class="input !rounded-xl" />
                    </div>
                    <div>
                        <label class="label">Page start</label>
                        <input v-model="form.page_start" class="input !rounded-xl" />
                    </div>
                    <div>
                        <label class="label">Page end</label>
                        <input v-model="form.page_end" class="input !rounded-xl" />
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="label">Copyright</label>
                        <input v-model="form.copyright" class="input !rounded-xl" />
                    </div>
                    <div>
                        <label class="label">License</label>
                        <input v-model="form.license" class="input !rounded-xl" />
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="label">DOI (leave blank until Crossref registration)</label>
                        <input v-model="form.doi" class="input !rounded-xl" placeholder="10.xxxx/xxxxx" />
                    </div>
                    <div>
                        <label class="label">Status</label>
                        <select v-model="form.status" class="input !rounded-xl">
                            <option value="draft">Draft</option>
                            <option value="published">Published</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="label">Citation (auto-built if left blank)</label>
                    <textarea v-model="form.citation" rows="2" class="input !rounded-xl" />
                </div>

                <div>
                    <label class="label">Full-text PDF</label>
                    <input type="file" accept="application/pdf,.pdf" class="input !rounded-xl" @change="onPdfChange" />
                </div>

                <div class="flex flex-wrap gap-2">
                    <button type="button" class="btn-primary !rounded-xl" :disabled="saving" @click="save">
                        {{ saving ? 'Saving…' : (editingUuid ? 'Update article' : 'Publish article') }}
                    </button>
                    <button v-if="editingUuid" type="button" class="btn-secondary !rounded-xl" @click="resetForm">Cancel edit</button>
                </div>
            </div>
        </AdminPanel>

        <AdminPanel title="Published & draft articles">
            <AdminEmptyState
                v-if="!articles.length"
                title="No articles yet"
                description="Use the form above to publish the first landing page."
            >
                <template #icon><DocumentTextIcon class="size-6" /></template>
            </AdminEmptyState>

            <ul v-else class="divide-y divide-slate-100 -mx-4 sm:-mx-5">
                <li v-for="article in articles" :key="article.uuid" class="admin-list-item">
                    <div class="min-w-0">
                        <p class="font-medium text-slate-900">{{ article.title }}</p>
                        <p class="mt-1 text-sm text-slate-500">
                            /articles/{{ article.slug }}
                            · {{ article.status }}
                            <span v-if="article.is_crossref_sample"> · Crossref sample</span>
                        </p>
                    </div>
                    <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row">
                        <a :href="article.landing_url" target="_blank" class="btn-secondary !rounded-xl text-sm">View</a>
                        <button type="button" class="btn-secondary !rounded-xl text-sm" @click="editArticle(article)">Edit</button>
                        <button
                            v-if="!article.is_crossref_sample"
                            type="button"
                            class="btn-secondary !rounded-xl text-sm text-red-600"
                            @click="remove(article)"
                        >
                            <TrashIcon class="mr-1 inline size-4" /> Delete
                        </button>
                    </div>
                </li>
            </ul>
        </AdminPanel>
    </div>
</template>
