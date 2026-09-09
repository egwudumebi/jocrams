<script setup>
import { ref, onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import {
    DocumentTextIcon,
    NewspaperIcon,
    PhotoIcon,
    BookOpenIcon,
} from '@heroicons/vue/24/outline';
import AdminPageIntro from '../../components/admin/AdminPageIntro.vue';
import { useAuth } from '../../composables/useAuth';

const { getAdminClient } = useAuth();
const counts = ref(null);
const loading = ref(true);

const quickLinks = [
    { to: '/admin/news', label: 'News & Blog', icon: NewspaperIcon, key: 'news' },
    { to: '/admin/pages', label: 'Static Pages', icon: DocumentTextIcon, key: 'pages' },
    { to: '/admin/publications', label: 'Publications Library', icon: BookOpenIcon, key: 'downloads' },
    { to: '/admin/assets', label: 'Media Library', icon: PhotoIcon, key: 'media_catalog' },
];

onMounted(load);

async function load() {
    loading.value = true;
    try {
        const { data } = await getAdminClient().get('/content/overview');
        counts.value = data.data.counts;
    } finally {
        loading.value = false;
    }
}

function statFor(key) {
    const group = counts.value?.[key];
    if (!group) return '—';

    if (key === 'downloads') {
        return `${group.active} active / ${group.total} total`;
    }

    if (key === 'media_catalog') {
        return `${group.total ?? group} items`;
    }

    return `${group.published} published / ${group.total} total`;
}
</script>

<template>
    <div>
        <AdminPageIntro description="Overview of news, static pages, library files, and media catalog assets." />

        <div v-if="loading" class="rounded-2xl border border-dashed border-slate-200 bg-white px-8 py-14 text-center text-slate-500">
            Loading content overview...
        </div>

        <div v-else class="admin-stat-grid">
            <RouterLink
                v-for="link in quickLinks"
                :key="link.to"
                :to="link.to"
                class="admin-stat-card group block"
            >
                <component :is="link.icon" class="size-8 text-institutional transition group-hover:scale-105" />
                <p class="mt-4 font-display text-base font-bold text-slate-900 sm:text-lg">{{ link.label }}</p>
                <p class="mt-1 text-sm text-slate-500">{{ statFor(link.key) }}</p>
            </RouterLink>
        </div>

        <section v-if="counts" class="mt-8 admin-panel">
            <div class="admin-panel-header !border-b">
                <h2 class="admin-panel-title">Content summary</h2>
            </div>
            <dl class="admin-panel-body grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-xl bg-slate-50 p-4">
                    <dt class="text-sm text-slate-500">News drafts</dt>
                    <dd class="mt-1 text-2xl font-bold text-slate-900">{{ counts.news.draft }}</dd>
                </div>
                <div class="rounded-xl bg-slate-50 p-4">
                    <dt class="text-sm text-slate-500">Page drafts</dt>
                    <dd class="mt-1 text-2xl font-bold text-slate-900">{{ counts.pages.draft }}</dd>
                </div>
                <div class="rounded-xl bg-slate-50 p-4">
                    <dt class="text-sm text-slate-500">Library files</dt>
                    <dd class="mt-1 text-2xl font-bold text-slate-900">{{ counts.downloads.active }}</dd>
                </div>
                <div class="rounded-xl bg-slate-50 p-4">
                    <dt class="text-sm text-slate-500">Media catalog</dt>
                    <dd class="mt-1 text-2xl font-bold text-slate-900">{{ counts.media_catalog }}</dd>
                </div>
            </dl>
        </section>
    </div>
</template>
