<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import { useRoute, RouterLink } from 'vue-router';
import { applyDynamicJournalArticleSeo } from '../../composables/useSeo';
import { useSiteBranding } from '../../composables/useSiteBranding';
import { ArrowDownTrayIcon, ArrowLeftIcon } from '@heroicons/vue/24/outline';
import { journalApi } from '../../api/client';
import JournalStatusBadge from '../../components/journal/JournalStatusBadge.vue';
import { formatJournalDate, downloadJournalDocument } from '../../utils/journal';
import { useAuth } from '../../composables/useAuth';

const route = useRoute();
const { memberToken } = useAuth();
const { branding, loadBranding } = useSiteBranding();
const article = ref(null);
const loading = ref(true);
const error = ref('');

const slugOrId = computed(() => route.params.slugOrId);

onMounted(async () => {
    await loadBranding();
    await load();
});

watch(article, (value) => {
    if (!value) {
        return;
    }

    applyDynamicJournalArticleSeo(route, branding.value || window.__APP_SEO__?.branding || {}, value);
});

async function load() {
    loading.value = true;
    error.value = '';
    try {
        const { data } = await journalApi(memberToken.value).get('/submissions/public', { params: { per_page: 100 } });
        article.value = (data.data || []).find((item) => item.slug === slugOrId.value || item.id === slugOrId.value) || null;
        if (! article.value) {
            const allRes = await journalApi(memberToken.value).get('/submissions', { params: { per_page: 100 } });
            article.value = (allRes.data.data || []).find((item) => item.slug === slugOrId.value || item.id === slugOrId.value) || null;
        }
        if (! article.value) {
            error.value = 'Article not found.';
        }
    } catch {
        error.value = 'Unable to load article.';
    } finally {
        loading.value = false;
    }
}

async function handleDownload() {
    if (! article.value?.document_url) {
        return;
    }
    await downloadJournalDocument(article.value.document_url, memberToken.value || null);
}
</script>

<template>
    <section class="bg-slate-50 py-12">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
            <RouterLink to="/journal" class="inline-flex items-center gap-2 text-sm text-brand-600">
                <ArrowLeftIcon class="size-4" /> Back to journal
            </RouterLink>

            <div v-if="loading" class="mt-8 text-slate-500">Loading…</div>
            <div v-else-if="error" class="mt-8 card p-8 text-center text-slate-500">{{ error }}</div>
            <article v-else class="mt-8 card p-8">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 class="text-3xl font-bold text-slate-900">{{ article.title }}</h1>
                        <p class="mt-2 text-slate-600">{{ article.author_name }} · {{ article.category }}</p>
                    </div>
                    <JournalStatusBadge :status="article.status" />
                </div>

                <dl class="mt-6 grid gap-4 border-y border-slate-100 py-4 text-sm sm:grid-cols-3">
                    <div><dt class="text-slate-500">Submitted</dt><dd class="font-medium">{{ formatJournalDate(article.date_submitted) }}</dd></div>
                    <div v-if="article.mins_read"><dt class="text-slate-500">Read time</dt><dd class="font-medium">{{ article.mins_read }} minutes</dd></div>
                    <div v-if="article.keywords"><dt class="text-slate-500">Keywords</dt><dd class="font-medium">{{ article.keywords }}</dd></div>
                </dl>

                <div class="mt-6">
                    <h2 class="font-semibold text-slate-900">Abstract</h2>
                    <p class="mt-2 whitespace-pre-wrap text-slate-700">{{ article.abstract }}</p>
                </div>

                <div v-if="article.references?.length" class="mt-8">
                    <h2 class="font-semibold text-slate-900">References</h2>
                    <ol class="mt-3 list-decimal space-y-2 pl-5 text-sm text-slate-700">
                        <li v-for="(ref, index) in article.references" :key="index">{{ ref }}</li>
                    </ol>
                </div>

                <div class="mt-8">
                    <button
                        v-if="article.can_access"
                        class="btn-primary inline-flex items-center gap-2"
                        @click="handleDownload"
                    >
                        <ArrowDownTrayIcon class="size-4" /> Download manuscript
                    </button>
                    <RouterLink v-else to="/member/login" class="btn-secondary">Sign in to download</RouterLink>
                </div>
            </article>
        </div>
    </section>
</template>
