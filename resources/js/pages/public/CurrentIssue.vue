<script setup>
import { onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { BookOpenIcon } from '@heroicons/vue/24/outline';
import { publicApi } from '../../api/client';
import { useSiteBranding } from '../../composables/useSiteBranding';
import JournalTitle from '../../components/JournalTitle.vue';

const { branding, loadBranding } = useSiteBranding();
const issue = ref(null);
const articles = ref([]);
const loading = ref(true);

onMounted(async () => {
    await loadBranding();
    try {
        const { data } = await publicApi().get('/articles/current-issue');
        issue.value = data.data?.issue || null;
        articles.value = data.data?.articles || [];
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <div>
        <section class="border-b border-slate-200 bg-gradient-to-br from-surface-muted via-white to-institutional/5">
            <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
                <p class="label-caps text-institutional">JOCRAMS</p>
                <h1 class="mt-2 font-display text-4xl font-bold tracking-tight text-institutional-dark">
                    Current Issue
                </h1>
                <p class="mt-3 max-w-2xl text-base text-text-secondary">
                    Latest published issue of
                    <JournalTitle :text="branding?.journal_full_name || 'Journal of Communication Research and Media Studies'" />.
                </p>
                <p v-if="issue?.label" class="mt-4 text-lg font-semibold text-institutional">
                    {{ issue.label }}
                </p>
                <div class="mt-6">
                    <RouterLink to="/articles" class="btn-secondary !rounded-xl">Browse all articles</RouterLink>
                </div>
            </div>
        </section>

        <section class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
            <div v-if="loading" class="text-slate-500">Loading current issue…</div>

            <div v-else-if="!articles.length" class="rounded-2xl border border-dashed border-slate-200 p-10 text-center">
                <BookOpenIcon class="mx-auto size-8 text-slate-300" />
                <p class="mt-3 font-medium text-slate-700">No articles in the current issue yet.</p>
            </div>

            <ol v-else class="space-y-4">
                <li
                    v-for="article in articles"
                    :key="article.uuid"
                    class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6"
                >
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                        {{ article.article_type }}
                        <span v-if="article.pages"> · pp. {{ article.pages }}</span>
                    </p>
                    <RouterLink
                        :to="article.landing_url"
                        class="mt-1 block font-display text-xl font-bold text-slate-900 hover:text-institutional"
                    >
                        {{ article.title }}
                    </RouterLink>
                    <p class="mt-2 text-sm text-slate-600">{{ article.author_names }}</p>
                    <p class="mt-3 line-clamp-2 text-sm text-slate-600">{{ article.abstract }}</p>
                    <RouterLink :to="article.landing_url" class="mt-4 inline-flex text-sm font-semibold text-institutional hover:underline">
                        Open landing page →
                    </RouterLink>
                </li>
            </ol>
        </section>
    </div>
</template>
