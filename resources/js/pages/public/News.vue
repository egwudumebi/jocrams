<script setup>
import { ref, watch, onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import {
    ArrowRightIcon,
    CalendarDaysIcon,
    MagnifyingGlassIcon,
    NewspaperIcon,
} from '@heroicons/vue/24/outline';
import { publicApi } from '../../api/client';
import { stripHtml } from '../../utils/html';

const articles = ref([]);
const search = ref('');
const loading = ref(true);
const searched = ref(false);

let searchTimeout = null;

function formatPublishedDate(dateString) {
    if (! dateString) {
        return '';
    }

    return new Date(dateString).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

async function load() {
    loading.value = true;
    searched.value = search.value.trim().length > 0;

    try {
        const { data } = await publicApi().get('/news', {
            params: { search: search.value.trim() || undefined },
        });
        articles.value = data.data;
    } finally {
        loading.value = false;
    }
}

watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(load, 300);
});

onMounted(load);
</script>

<template>
    <div>
        <!-- Page header -->
        <section class="border-b border-slate-200/80 bg-gradient-to-br from-surface-muted via-white to-institutional/5">
            <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 sm:py-16 lg:px-8">
                <div class="flex flex-col gap-8 lg:flex-row lg:items-end lg:justify-between">
                    <div class="max-w-2xl">
                        <p class="label-caps text-institutional">Updates</p>
                        <h1 class="mt-2 font-display text-4xl font-bold tracking-tight text-institutional-dark sm:text-5xl">
                            News Center
                        </h1>
                        <p class="mt-4 text-lg leading-relaxed text-text-secondary">
                            Official announcements, press releases, and association updates for members and the public.
                        </p>
                    </div>

                    <div class="relative w-full max-w-md shrink-0">
                        <MagnifyingGlassIcon class="pointer-events-none absolute left-4 top-1/2 size-5 -translate-y-1/2 text-text-secondary" aria-hidden="true" />
                        <input
                            v-model="search"
                            type="search"
                            placeholder="Search news..."
                            class="input !rounded-xl !border-slate-200/80 !py-3 !pl-12 !shadow-sm focus:!border-institutional focus:!ring-institutional/20"
                        />
                    </div>
                </div>

                <div v-if="! loading && articles.length > 0" class="mt-10 flex flex-wrap gap-3">
                    <span class="inline-flex items-center gap-2 rounded-full bg-white px-4 py-2 text-sm font-medium text-institutional-dark shadow-sm ring-1 ring-slate-200/80">
                        <NewspaperIcon class="size-4 text-institutional" aria-hidden="true" />
                        {{ articles.length }} {{ articles.length === 1 ? 'article' : 'articles' }}
                    </span>
                    <span v-if="searched" class="inline-flex items-center rounded-full bg-institutional/8 px-4 py-2 text-sm font-medium text-institutional">
                        Filtered results
                    </span>
                </div>
            </div>
        </section>

        <!-- Content -->
        <section class="section-padding bg-white">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div v-if="loading" class="flex justify-center py-16">
                    <div class="size-10 animate-spin rounded-full border-2 border-institutional/20 border-t-institutional" />
                </div>

                <div v-else-if="articles.length === 0 && ! searched" class="empty-state mx-auto max-w-2xl">
                    <NewspaperIcon class="mx-auto size-12 text-institutional/25" aria-hidden="true" />
                    <h2 class="mt-5 font-display text-2xl font-bold text-institutional-dark">No news published yet</h2>
                    <p class="mx-auto mt-3 max-w-md text-base leading-relaxed text-text-secondary">
                        Check back soon for announcements and updates from the association.
                    </p>
                    <RouterLink to="/events" class="btn-institutional mt-8 inline-flex">
                        View upcoming events
                    </RouterLink>
                </div>

                <div v-else-if="articles.length === 0" class="empty-state mx-auto max-w-xl">
                    <MagnifyingGlassIcon class="mx-auto size-10 text-institutional/25" aria-hidden="true" />
                    <h2 class="mt-4 font-display text-xl font-bold text-institutional-dark">No articles match your search</h2>
                    <p class="mt-2 text-sm text-text-secondary">Try different keywords or browse all news.</p>
                    <button type="button" class="link-arrow mt-4" @click="search = ''">
                        Clear search
                    </button>
                </div>

                <div v-else class="grid auto-rows-fr gap-8 sm:grid-cols-2 xl:grid-cols-3">
                    <RouterLink
                        v-for="article in articles"
                        :key="article.uuid"
                        :to="`/news/${article.uuid}`"
                        class="group hover-lift card-modern flex h-full flex-col overflow-hidden"
                    >
                        <div class="relative aspect-[16/10] shrink-0 overflow-hidden bg-gradient-to-br from-institutional/15 via-slate-100 to-accent-gold/20">
                            <div class="absolute inset-0 opacity-[0.07]" style="background-image: radial-gradient(circle at 1px 1px, #0a3d91 1px, transparent 0); background-size: 20px 20px;" />
                            <NewspaperIcon class="absolute inset-0 m-auto size-12 text-institutional/25 transition duration-500 group-hover:scale-110 group-hover:text-institutional/40" aria-hidden="true" />
                        </div>

                        <div class="flex flex-1 flex-col p-6 sm:p-7">
                            <span class="label-caps w-fit rounded-full bg-accent-gold/15 px-3 py-1 text-institutional-dark">
                                {{ article.category?.name || 'News' }}
                            </span>
                            <h2 class="mt-4 line-clamp-2 font-display text-xl font-semibold text-institutional-dark transition group-hover:text-institutional">
                                {{ article.title }}
                            </h2>
                            <p class="mt-3 line-clamp-3 flex-1 text-sm leading-relaxed text-text-secondary">
                                {{ stripHtml(article.excerpt) }}
                            </p>
                            <div class="mt-5 flex items-center justify-between gap-4">
                                <p class="inline-flex items-center gap-1.5 text-xs font-medium text-text-secondary/80">
                                    <CalendarDaysIcon class="size-4 shrink-0" aria-hidden="true" />
                                    {{ formatPublishedDate(article.published_at) }}
                                </p>
                                <span class="link-arrow shrink-0 text-xs">
                                    Read
                                    <ArrowRightIcon class="size-3.5 transition group-hover:translate-x-0.5" aria-hidden="true" />
                                </span>
                            </div>
                        </div>
                    </RouterLink>
                </div>
            </div>
        </section>

        <!-- Events upsell -->
        <section class="border-t border-slate-200/80 bg-surface-muted py-14 sm:py-16">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="card-modern flex flex-col items-center gap-6 p-8 text-center sm:p-10 lg:flex-row lg:text-left">
                    <div class="flex size-16 shrink-0 items-center justify-center rounded-2xl bg-institutional/10 text-institutional">
                        <CalendarDaysIcon class="size-8" aria-hidden="true" />
                    </div>
                    <div class="flex-1">
                        <h2 class="font-display text-2xl font-bold text-institutional-dark">Stay connected at our events</h2>
                        <p class="mt-2 max-w-2xl text-text-secondary">
                            Conferences, workshops, and member gatherings — register online and never miss an update.
                        </p>
                    </div>
                    <RouterLink to="/events" class="btn-institutional shrink-0">
                        Browse events
                    </RouterLink>
                </div>
            </div>
        </section>
    </div>
</template>
