<script setup>
import { ref, onMounted } from 'vue';
import { NewspaperIcon } from '@heroicons/vue/24/outline';
import AdminAlert from '../../components/admin/AdminAlert.vue';
import AdminEmptyState from '../../components/admin/AdminEmptyState.vue';
import AdminPageIntro from '../../components/admin/AdminPageIntro.vue';
import AdminPanel from '../../components/admin/AdminPanel.vue';
import RichTextEditor from '../../components/forms/RichTextEditor.vue';
import { useAuth } from '../../composables/useAuth';

const { getAdminClient } = useAuth();
const articles = ref([]);
const form = ref({ title: '', excerpt: '', body: '', status: 'draft' });
const editing = ref(false);
const message = ref('');

onMounted(load);

async function load() {
    const { data } = await getAdminClient().get('/news-articles');
    articles.value = data.data || [];
}

async function save() {
    if (editing.value) return;
    await getAdminClient().post('/news-articles', form.value);
    message.value = 'Article created.';
    form.value = { title: '', excerpt: '', body: '', status: 'draft' };
    await load();
}

async function publish(article) {
    await getAdminClient().put(`/news-articles/${article.uuid}`, { status: 'published' });
    message.value = 'Article published.';
    await load();
}
</script>

<template>
    <div>
        <AdminPageIntro description="Manage news articles and announcements for the public site." />

        <AdminAlert v-if="message" type="success">{{ message }}</AdminAlert>

        <AdminPanel title="New article" description="Drafts can be published later from the list below." class="mb-6">
            <div class="space-y-4">
                <div>
                    <label class="label">Title</label>
                    <input v-model="form.title" placeholder="Article title" class="input !rounded-xl" />
                </div>
                <div>
                    <label class="label">Excerpt</label>
                    <input v-model="form.excerpt" placeholder="Short summary for listings" class="input !rounded-xl" />
                </div>
                <div>
                    <label class="label">Body</label>
                    <RichTextEditor v-model="form.body" placeholder="Write the full article…" min-height="12rem" />
                </div>
                <div class="flex flex-col gap-2 sm:flex-row">
                    <button class="btn-secondary w-full !rounded-xl sm:w-auto" @click="form.status = 'draft'; save()">Save draft</button>
                    <button class="btn-primary w-full !rounded-xl sm:w-auto" @click="form.status = 'published'; save()">Publish now</button>
                </div>
            </div>
        </AdminPanel>

        <AdminPanel title="Published & draft articles">
            <AdminEmptyState
                v-if="!articles.length"
                title="No articles yet"
                description="Create your first news article using the form above."
            >
                <template #icon><NewspaperIcon class="size-6" /></template>
            </AdminEmptyState>
            <ul v-else class="divide-y divide-slate-100 -mx-4 sm:-mx-5">
                <li v-for="a in articles" :key="a.uuid" class="admin-list-item">
                    <div class="min-w-0">
                        <p class="font-medium text-slate-900">{{ a.title }}</p>
                        <p class="mt-1 text-sm capitalize text-slate-500">{{ a.status }}</p>
                    </div>
                    <button v-if="a.status !== 'published'" class="btn-secondary w-full !rounded-xl text-sm sm:w-auto" @click="publish(a)">Publish</button>
                </li>
            </ul>
        </AdminPanel>
    </div>
</template>
