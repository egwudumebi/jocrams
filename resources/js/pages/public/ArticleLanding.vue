<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import {
    ArrowDownTrayIcon,
    ArrowLeftIcon,
    BookOpenIcon,
    CalendarDaysIcon,
} from '@heroicons/vue/24/outline';
import { publicApi } from '../../api/client';
import { useSiteBranding } from '../../composables/useSiteBranding';
import { applySeo } from '../../utils/seo';
import JournalTitle from '../../components/JournalTitle.vue';

const route = useRoute();
const { branding, loadBranding } = useSiteBranding();
const article = ref(null);
const loading = ref(true);
const error = ref('');

const journalFullName = computed(
    () => branding.value?.journal_full_name
        || branding.value?.site_tagline
        || 'Journal of Communication Research and Media Studies',
);

const keywordList = computed(() => {
    if (!article.value?.keywords) {
        return [];
    }

    return String(article.value.keywords)
        .split(',')
        .map((item) => item.trim())
        .filter(Boolean);
});

onMounted(async () => {
    await loadBranding();
    await load();
});

watch(article, (value) => {
    if (!value) {
        return;
    }

    const siteName = branding.value?.site_name || 'JOCRAMS';
    applySeo({
        title: `${value.title} | ${siteName}`,
        description: value.abstract?.slice(0, 160) || value.title,
        type: 'article',
        keywords: value.keywords || undefined,
        url: window.location.origin + value.landing_url,
    });
});

async function load() {
    loading.value = true;
    error.value = '';
    try {
        const { data } = await publicApi().get(`/articles/${route.params.slug}`);
        article.value = data.data;
    } catch {
        article.value = null;
        error.value = 'Article not found.';
    } finally {
        loading.value = false;
    }
}

function formatDate(value) {
    if (!value) {
        return null;
    }

    return new Date(`${value}T00:00:00`).toLocaleDateString('en-GB', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}

function orcidUrl(orcid) {
    if (!orcid) {
        return null;
    }

    const id = String(orcid).replace(/^https?:\/\/orcid\.org\//, '');

    return `https://orcid.org/${id}`;
}
</script>

<template>
    <div class="bg-slate-50">
        <section class="border-b border-slate-200 bg-white">
            <div class="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
                <RouterLink to="/articles" class="inline-flex items-center gap-2 text-sm font-medium text-institutional hover:underline">
                    <ArrowLeftIcon class="size-4" />
                    All articles
                </RouterLink>

                <div v-if="loading" class="mt-10 text-slate-500">Loading article…</div>
                <div v-else-if="error" class="mt-10 rounded-2xl border border-slate-200 bg-white p-8 text-center text-slate-500">
                    {{ error }}
                </div>

                <article v-else-if="article" class="mt-6">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-institutional">
                        {{ branding?.site_name || 'JOCRAMS' }}
                    </p>
                    <p class="mt-2 text-sm font-medium text-slate-600">
                        <JournalTitle :text="article.journal_title || journalFullName" />
                    </p>

                    <h1 class="mt-4 font-display text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">
                        {{ article.title }}
                    </h1>

                    <div class="mt-5 space-y-3">
                        <div
                            v-for="(author, index) in article.authors"
                            :key="`${author.name}-${index}`"
                            class="border-l-2 border-accent-gold pl-3"
                        >
                            <p class="font-semibold text-slate-900">{{ author.name }}</p>
                            <p v-if="author.affiliation" class="text-sm text-slate-600">{{ author.affiliation }}</p>
                            <a
                                v-if="author.orcid"
                                :href="orcidUrl(author.orcid)"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="mt-1 inline-block text-sm text-institutional hover:underline"
                            >
                                ORCID: {{ author.orcid }}
                            </a>
                        </div>
                    </div>

                    <div class="mt-6 flex flex-wrap gap-2 text-sm">
                        <span class="rounded-full bg-institutional/10 px-3 py-1 font-medium text-institutional">
                            {{ article.article_type }}
                        </span>
                        <span v-if="article.volume_issue_label" class="rounded-full bg-slate-100 px-3 py-1 text-slate-700">
                            {{ article.volume_issue_label }}
                        </span>
                        <span v-if="article.pages" class="rounded-full bg-slate-100 px-3 py-1 text-slate-700">
                            pp. {{ article.pages }}
                        </span>
                        <span v-if="article.is_crossref_sample" class="rounded-full bg-amber-50 px-3 py-1 text-amber-800">
                            Crossref sample landing page
                        </span>
                    </div>
                </article>
            </div>
        </section>

        <section v-if="article && !loading && !error" class="mx-auto max-w-4xl px-4 py-10 sm:px-6 lg:px-8">
            <div class="grid gap-6 lg:grid-cols-[1fr_240px]">
                <div class="space-y-6">
                    <div class="rounded-2xl border border-slate-200 bg-white p-6 sm:p-8">
                        <h2 class="font-display text-xl font-bold text-slate-900">Abstract</h2>
                        <p class="mt-3 whitespace-pre-wrap text-base leading-relaxed text-slate-700">{{ article.abstract }}</p>

                        <div v-if="keywordList.length" class="mt-6">
                            <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Keywords</h3>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <span
                                    v-for="keyword in keywordList"
                                    :key="keyword"
                                    class="rounded-lg bg-slate-100 px-2.5 py-1 text-sm text-slate-700"
                                >
                                    {{ keyword }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-white p-6 sm:p-8">
                        <h2 class="font-display text-xl font-bold text-slate-900">How to cite</h2>
                        <p class="mt-3 text-sm leading-relaxed text-slate-700">{{ article.citation }}</p>
                        <p v-if="!article.doi" class="mt-3 text-xs text-slate-500">
                            DOI will be registered with Crossref after membership approval. This page is the permanent
                            landing URL for that future DOI.
                        </p>
                        <p v-else class="mt-3 text-sm">
                            <a :href="`https://doi.org/${article.doi}`" class="text-institutional hover:underline" target="_blank" rel="noopener noreferrer">
                                https://doi.org/{{ article.doi }}
                            </a>
                        </p>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-white p-6 sm:p-8">
                        <h2 class="font-display text-xl font-bold text-slate-900">Copyright &amp; licensing</h2>
                        <dl class="mt-4 space-y-3 text-sm">
                            <div>
                                <dt class="font-semibold text-slate-900">Copyright</dt>
                                <dd class="mt-1 text-slate-700">{{ article.copyright || '© JOCRAMS / SICAMA.' }}</dd>
                            </div>
                            <div>
                                <dt class="font-semibold text-slate-900">License</dt>
                                <dd class="mt-1 text-slate-700">{{ article.license || 'All rights reserved.' }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <aside class="space-y-4">
                    <div class="rounded-2xl border border-slate-200 bg-white p-5">
                        <a
                            v-if="article.pdf_url"
                            :href="article.pdf_url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="btn-primary inline-flex w-full items-center justify-center gap-2"
                        >
                            <ArrowDownTrayIcon class="size-4" />
                            Full-text PDF
                        </a>
                        <p v-else class="text-sm text-slate-500">PDF forthcoming.</p>
                        <p class="mt-3 text-xs leading-relaxed text-slate-500">
                            The DOI resolves to this landing page. Readers access the PDF from here.
                        </p>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-white p-5">
                        <h3 class="flex items-center gap-2 text-sm font-semibold text-slate-900">
                            <CalendarDaysIcon class="size-4 text-institutional" />
                            Publication timeline
                        </h3>
                        <dl class="mt-4 space-y-3 text-sm">
                            <div v-if="article.received_at">
                                <dt class="text-slate-500">Received</dt>
                                <dd class="font-medium text-slate-900">{{ formatDate(article.received_at) }}</dd>
                            </div>
                            <div v-if="article.revised_at">
                                <dt class="text-slate-500">Revised</dt>
                                <dd class="font-medium text-slate-900">{{ formatDate(article.revised_at) }}</dd>
                            </div>
                            <div v-if="article.accepted_at">
                                <dt class="text-slate-500">Accepted</dt>
                                <dd class="font-medium text-slate-900">{{ formatDate(article.accepted_at) }}</dd>
                            </div>
                            <div v-if="article.published_at">
                                <dt class="text-slate-500">Published</dt>
                                <dd class="font-medium text-slate-900">{{ formatDate(article.published_at) }}</dd>
                            </div>
                        </dl>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-white p-5">
                        <h3 class="flex items-center gap-2 text-sm font-semibold text-slate-900">
                            <BookOpenIcon class="size-4 text-institutional" />
                            Browse
                        </h3>
                        <div class="mt-3 flex flex-col gap-2 text-sm">
                            <RouterLink to="/articles/current-issue" class="text-institutional hover:underline">Current issue</RouterLink>
                            <RouterLink to="/articles" class="text-institutional hover:underline">All articles</RouterLink>
                            <RouterLink to="/journal" class="text-institutional hover:underline">Journal home</RouterLink>
                        </div>
                    </div>
                </aside>
            </div>
        </section>
    </div>
</template>
