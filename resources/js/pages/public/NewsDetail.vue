<script setup>
import { computed, ref, onMounted, watch } from 'vue';
import { useRoute, RouterLink } from 'vue-router';
import { applyDynamicArticleSeo } from '../../composables/useSeo';
import { useSiteBranding } from '../../composables/useSiteBranding';
import {
    ArrowLeftIcon,
    CalendarDaysIcon,
    NewspaperIcon,
} from '@heroicons/vue/24/outline';
import { publicApi } from '../../api/client';
import { renderRichTextHtml, RICH_TEXT_PROSE_CLASSES, stripHtml } from '../../utils/html';

const route = useRoute();
const { branding, loadBranding } = useSiteBranding();
const article = ref(null);
const relatedArticles = ref([]);
const loading = ref(true);

const renderedBody = computed(() => renderRichTextHtml(article.value?.body));

function formatPublishedDate(dateString) {
    if (!dateString) {
        return '';
    }

    return new Date(dateString).toLocaleDateString('en-US', {
        weekday: 'long',
        month: 'long',
        day: 'numeric',
        year: 'numeric',
    });
}

onMounted(async () => {
    await loadBranding();

    try {
        const [articleResponse, listResponse] = await Promise.all([
            publicApi().get(`/news/${route.params.uuid}`),
            publicApi().get('/news'),
        ]);

        article.value = articleResponse.data.data;
        relatedArticles.value = (listResponse.data.data || [])
            .filter((item) => item.uuid !== route.params.uuid)
            .slice(0, 3);
    } finally {
        loading.value = false;
    }
});

watch(article, (value) => {
    if (!value) {
        return;
    }

    applyDynamicArticleSeo(route, branding.value || window.__APP_SEO__?.branding || {}, {
        ...value,
        excerpt: value.excerpt ? stripHtml(value.excerpt) : undefined,
    });
});
</script>

<template>
    <div>
        <section class="border-b border-slate-200/80 bg-gradient-to-br from-surface-muted via-white to-institutional/5">
            <div class="mx-auto max-w-4xl px-4 py-10 sm:px-6 lg:px-8">
                <RouterLink to="/news" class="inline-flex items-center gap-2 text-sm font-semibold text-institutional transition hover:text-institutional-dark">
                    <ArrowLeftIcon class="size-4" aria-hidden="true" />
                    Back to news
                </RouterLink>

                <div v-if="loading" class="mt-10 flex justify-center py-16">
                    <div class="size-10 animate-spin rounded-full border-2 border-institutional/20 border-t-institutional" />
                </div>

                <header v-else-if="article" class="mt-8">
                    <span class="label-caps inline-flex rounded-full bg-accent-gold/15 px-3 py-1 text-institutional-dark">
                        {{ article.category?.name || 'News' }}
                    </span>
                    <h1 class="mt-4 font-display text-3xl font-bold tracking-tight text-institutional-dark sm:text-4xl lg:text-5xl">
                        {{ article.title }}
                    </h1>
                    <p class="mt-4 inline-flex items-center gap-2 text-sm font-medium text-text-secondary">
                        <CalendarDaysIcon class="size-4 shrink-0 text-institutional" aria-hidden="true" />
                        {{ formatPublishedDate(article.published_at) }}
                    </p>
                    <p v-if="article.excerpt" class="mt-5 max-w-3xl text-lg leading-relaxed text-text-secondary">
                        {{ stripHtml(article.excerpt) }}
                    </p>
                </header>
            </div>
        </section>

        <section v-if="article && !loading" class="section-padding bg-white">
            <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                <article class="card-modern overflow-hidden">
                    <div class="border-b border-slate-100 bg-slate-50/60 px-6 py-4 sm:px-8">
                        <div class="flex items-center gap-3">
                            <span class="flex size-10 items-center justify-center rounded-xl bg-institutional/10 text-institutional">
                                <NewspaperIcon class="size-5" aria-hidden="true" />
                            </span>
                            <div>
                                <p class="text-sm font-semibold text-institutional-dark">Article</p>
                                <p class="text-xs text-text-secondary">Official association update</p>
                            </div>
                        </div>
                    </div>
                    <div class="px-6 py-8 sm:px-8 sm:py-10">
                        <div :class="RICH_TEXT_PROSE_CLASSES" v-html="renderedBody" />
                    </div>
                </article>

                <div v-if="relatedArticles.length" class="mt-12">
                    <h2 class="font-display text-2xl font-bold text-institutional-dark">More news</h2>
                    <div class="mt-6 grid gap-4 sm:grid-cols-3">
                        <RouterLink
                            v-for="item in relatedArticles"
                            :key="item.uuid"
                            :to="`/news/${item.uuid}`"
                            class="group hover-lift card-modern p-5"
                        >
                            <p class="text-xs font-medium uppercase tracking-wide text-institutional">
                                {{ item.category?.name || 'News' }}
                            </p>
                            <h3 class="mt-2 line-clamp-2 font-semibold text-institutional-dark transition group-hover:text-institutional">
                                {{ item.title }}
                            </h3>
                            <p class="mt-2 line-clamp-2 text-sm text-text-secondary">
                                {{ stripHtml(item.excerpt) }}
                            </p>
                        </RouterLink>
                    </div>
                </div>

                <div class="mt-12 flex flex-col items-start justify-between gap-4 rounded-2xl border border-slate-200/80 bg-surface-muted p-6 sm:flex-row sm:items-center sm:p-8">
                    <div>
                        <h2 class="font-display text-xl font-bold text-institutional-dark">Stay in the loop</h2>
                        <p class="mt-1 text-sm text-text-secondary">Browse all announcements or explore upcoming events.</p>
                    </div>
                    <div class="flex flex-wrap gap-3">
                        <RouterLink to="/news" class="btn-secondary !rounded-xl">All news</RouterLink>
                        <RouterLink to="/events" class="btn-institutional !rounded-xl">View events</RouterLink>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>
