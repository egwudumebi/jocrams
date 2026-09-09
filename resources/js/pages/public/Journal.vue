<script setup>
import { onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { ArrowRightIcon, BookOpenIcon, LockClosedIcon, UserGroupIcon } from '@heroicons/vue/24/outline';
import { journalApi } from '../../api/client';
import JournalStatusBadge from '../../components/journal/JournalStatusBadge.vue';
import { useSiteBranding } from '../../composables/useSiteBranding';
import { formatJournalDate, downloadJournalDocument } from '../../utils/journal';
import { useAuth } from '../../composables/useAuth';

const articles = ref([]);
const loading = ref(true);
const { memberToken } = useAuth();
const { branding, loadBranding } = useSiteBranding();

onMounted(async () => {
    await loadBranding();

    try {
        const { data } = await journalApi(memberToken.value).get('/submissions/public');
        articles.value = data.data || [];
    } finally {
        loading.value = false;
    }
});

async function handleDownload(article) {
    if (!article.can_access || !article.document_url) {
        return;
    }

    await downloadJournalDocument(article.document_url, memberToken.value || null);
}
</script>

<template>
    <div>
        <section class="border-b border-slate-200/80 bg-gradient-to-br from-surface-muted via-white to-institutional/5">
            <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
                <div class="grid items-center gap-10 lg:grid-cols-[1.2fr_0.8fr]">
                    <div>
                        <p class="label-caps text-institutional">Research Journal</p>
                        <h1 class="mt-2 font-display text-4xl font-bold tracking-tight text-institutional-dark sm:text-5xl">
                            {{ branding?.site_name || 'JOCRAMS' }}
                        </h1>
                        <p class="mt-3 text-lg font-medium text-institutional">
                            {{ branding?.journal_full_name || branding?.site_tagline }}
                        </p>
                        <p class="mt-4 max-w-2xl text-base leading-relaxed text-text-secondary">
                            The official peer-reviewed journal of
                            <RouterLink to="/sicama" class="font-medium text-institutional hover:underline">
                                {{ branding?.parent_org?.short_name || 'SICAMA' }}
                            </RouterLink>.
                            Browse published manuscripts and explore the editorial team.
                        </p>
                        <blockquote class="mt-5 max-w-2xl border-l-4 border-accent-gold pl-4 text-sm italic text-text-secondary">
                            “{{ branding?.parent_org?.motto }}”
                        </blockquote>
                        <div class="mt-8 flex flex-wrap gap-3">
                            <RouterLink to="/journal/editorial-board" class="btn-institutional inline-flex items-center gap-2">
                                <UserGroupIcon class="size-4" aria-hidden="true" />
                                Editorial team
                            </RouterLink>
                            <RouterLink to="/member/journal/submit" class="btn-secondary inline-flex items-center gap-2 !rounded-xl">
                                Submit manuscript
                                <ArrowRightIcon class="size-4" aria-hidden="true" />
                            </RouterLink>
                        </div>
                    </div>

                    <div class="flex justify-center lg:justify-end">
                        <div class="card-modern max-w-sm p-6 text-center sm:p-8">
                            <img
                                :src="branding?.parent_org?.logo_url || '/images/sicama-logo.png'"
                                alt="SICAMA logo"
                                class="mx-auto max-h-40 object-contain"
                            />
                            <p class="mt-4 text-xs font-semibold uppercase tracking-wide text-institutional">Published by</p>
                            <p class="mt-1 font-display text-lg font-bold text-institutional-dark">
                                {{ branding?.parent_org?.short_name || 'SICAMA' }}
                            </p>
                            <p class="mt-2 text-sm text-text-secondary">{{ branding?.parent_org?.full_name }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="bg-slate-50 py-12 sm:py-16">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex items-end justify-between gap-4">
                    <div>
                        <h2 class="font-display text-2xl font-bold text-institutional-dark">Published Articles</h2>
                        <p class="mt-1 text-text-secondary">Peer-reviewed manuscripts approved for publication.</p>
                    </div>
                </div>

                <div v-if="loading" class="mt-10 text-slate-500">Loading articles…</div>
                <div v-else-if="articles.length === 0" class="mt-10 card p-10 text-center text-slate-500">
                    <BookOpenIcon class="mx-auto size-10 text-slate-300" />
                    <p class="mt-3">No published articles yet.</p>
                </div>
                <div v-else class="mt-10 grid gap-6 lg:grid-cols-2">
                    <article v-for="article in articles" :key="article.id" class="card p-6">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <RouterLink :to="`/journal/${article.slug || article.id}`" class="text-lg font-semibold text-slate-900 hover:text-brand-700">
                                    {{ article.title }}
                                </RouterLink>
                                <p class="mt-1 text-sm text-slate-500">{{ article.author_name }} · {{ article.category }}</p>
                            </div>
                            <JournalStatusBadge :status="article.status" />
                        </div>
                        <p class="mt-4 line-clamp-3 text-sm text-slate-600">{{ article.abstract }}</p>
                        <div class="mt-4 flex flex-wrap items-center gap-3 text-xs text-slate-500">
                            <span>Submitted {{ formatJournalDate(article.date_submitted) }}</span>
                            <span v-if="article.mins_read">{{ article.mins_read }} min read</span>
                            <span v-if="article.visibility === 'members_only'" class="inline-flex items-center gap-1 text-amber-700">
                                <LockClosedIcon class="size-3.5" /> Members only
                            </span>
                        </div>
                        <div class="mt-5 flex gap-3">
                            <RouterLink :to="`/journal/${article.slug || article.id}`" class="btn-secondary !py-2 text-sm">Read more</RouterLink>
                            <button
                                v-if="article.can_access"
                                class="btn-primary inline-flex items-center gap-1.5 !py-2 text-sm"
                                @click="handleDownload(article)"
                            >
                                Download PDF
                            </button>
                            <RouterLink v-else to="/member/login" class="self-center text-sm text-brand-600">Sign in to access</RouterLink>
                        </div>
                    </article>
                </div>
            </div>
        </section>
    </div>
</template>
