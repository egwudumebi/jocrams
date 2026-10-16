<script setup>
import { onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { BookOpenIcon, MagnifyingGlassIcon } from '@heroicons/vue/24/outline';
import { publicApi } from '../../api/client';
import { useSiteBranding } from '../../composables/useSiteBranding';
import JournalTitle from '../../components/JournalTitle.vue';

const { branding, loadBranding } = useSiteBranding();
const articles = ref([]);
const meta = ref({ current_page: 1, last_page: 1, total: 0 });
const loading = ref(true);
const search = ref('');
const page = ref(1);

onMounted(async () => {
    await loadBranding();
    await load();
});

async function load() {
    loading.value = true;
    try {
        const { data } = await publicApi().get('/articles', {
            params: {
                search: search.value || undefined,
                page: page.value,
                per_page: 12,
            },
        });
        articles.value = data.data || [];
        meta.value = data.meta || meta.value;
    } finally {
        loading.value = false;
    }
}

async function submitSearch() {
    page.value = 1;
    await load();
}

async function goPage(next) {
    page.value = next;
    await load();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
</script>

<template>
    <div>
        <section class="border-b border-slate-200 bg-gradient-to-br from-surface-muted via-white to-institutional/5">
            <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
                <p class="label-caps text-institutional">Published research</p>
                <h1 class="mt-2 font-display text-4xl font-bold tracking-tight text-institutional-dark">
                    Articles
                </h1>
                <p class="mt-3 max-w-2xl text-base text-text-secondary">
                    Browse peer-reviewed articles in
                    <JournalTitle :text="branding?.journal_full_name || 'Journal of Communication Research and Media Studies'" />.
                    Each article has a permanent public landing page suitable for Crossref DOI resolution.
                </p>
                <div class="mt-6 flex flex-wrap gap-3">
                    <RouterLink to="/articles/current-issue" class="btn-institutional">Current issue</RouterLink>
                    <RouterLink to="/journal" class="btn-secondary !rounded-xl">Journal home</RouterLink>
                </div>
            </div>
        </section>

        <section class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
            <form class="mb-8 flex flex-col gap-3 sm:flex-row" @submit.prevent="submitSearch">
                <div class="relative flex-1">
                    <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 size-5 -translate-y-1/2 text-slate-400" />
                    <input
                        v-model="search"
                        type="search"
                        class="input !rounded-xl !pl-10"
                        placeholder="Search title, keywords, or abstract"
                    />
                </div>
                <button type="submit" class="btn-primary !rounded-xl">Search</button>
            </form>

            <div v-if="loading" class="text-slate-500">Loading articles…</div>

            <div v-else-if="!articles.length" class="rounded-2xl border border-dashed border-slate-200 p-10 text-center">
                <BookOpenIcon class="mx-auto size-8 text-slate-300" />
                <p class="mt-3 font-medium text-slate-700">No published articles found.</p>
            </div>

            <ul v-else class="space-y-4">
                <li
                    v-for="article in articles"
                    :key="article.uuid"
                    class="rounded-2xl border border-slate-200 bg-white p-5 transition hover:border-institutional/30 hover:shadow-sm sm:p-6"
                >
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <p class="text-xs font-semibold uppercase tracking-wide text-institutional">
                                {{ article.article_type }}
                                <span v-if="article.volume_issue_label"> · {{ article.volume_issue_label }}</span>
                                <span v-if="article.pages"> · pp. {{ article.pages }}</span>
                            </p>
                            <RouterLink
                                :to="article.landing_url"
                                class="mt-1 block font-display text-xl font-bold text-slate-900 hover:text-institutional"
                            >
                                {{ article.title }}
                            </RouterLink>
                            <p class="mt-2 text-sm text-slate-600">{{ article.author_names }}</p>
                            <p class="mt-3 line-clamp-3 text-sm leading-relaxed text-slate-600">{{ article.abstract }}</p>
                        </div>
                        <RouterLink :to="article.landing_url" class="btn-secondary shrink-0 !rounded-xl text-sm">
                            View article
                        </RouterLink>
                    </div>
                </li>
            </ul>

            <div v-if="meta.last_page > 1" class="mt-8 flex items-center justify-between gap-3">
                <button
                    type="button"
                    class="btn-secondary !rounded-xl"
                    :disabled="meta.current_page <= 1"
                    @click="goPage(meta.current_page - 1)"
                >
                    Previous
                </button>
                <p class="text-sm text-slate-500">
                    Page {{ meta.current_page }} of {{ meta.last_page }} · {{ meta.total }} articles
                </p>
                <button
                    type="button"
                    class="btn-secondary !rounded-xl"
                    :disabled="meta.current_page >= meta.last_page"
                    @click="goPage(meta.current_page + 1)"
                >
                    Next
                </button>
            </div>
        </section>
    </div>
</template>
