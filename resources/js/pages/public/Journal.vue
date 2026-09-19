<script setup>
import { computed, onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import {
    ArrowRightIcon,
    BookOpenIcon,
    LightBulbIcon,
    LockClosedIcon,
    SparklesIcon,
    UserGroupIcon,
} from '@heroicons/vue/24/outline';
import { journalApi } from '../../api/client';
import JournalStatusBadge from '../../components/journal/JournalStatusBadge.vue';
import { useSiteBranding } from '../../composables/useSiteBranding';
import { useOrgInfo } from '../../composables/useOrgInfo';
import { formatJournalDate, downloadJournalDocument } from '../../utils/journal';
import { useAuth } from '../../composables/useAuth';

const articles = ref([]);
const loading = ref(true);
const { memberToken } = useAuth();
const { branding, loadBranding } = useSiteBranding();
const { journal, loadOrgInfo } = useOrgInfo();

const areas = computed(() => (journal.value?.areas_of_interest || []).slice(0, 12));
const publishTypes = computed(() => journal.value?.what_we_publish || []);
const whyPublish = computed(() => journal.value?.why_publish || []);
const eicMessage = computed(() => journal.value?.editor_in_chief_message || null);
const eicParagraphs = computed(() => paragraphs(eicMessage.value?.body));

onMounted(async () => {
    await Promise.all([loadBranding(), loadOrgInfo()]);

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

function paragraphs(text) {
    if (!text) {
        return [];
    }

    return String(text).split(/\n\s*\n/).map((part) => part.trim()).filter(Boolean);
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
                        <p v-if="journal?.issn" class="mt-2 font-mono text-sm text-text-secondary">
                            ISSN: {{ journal.issn }}
                        </p>
                        <p class="mt-4 max-w-2xl text-base leading-relaxed text-text-secondary">
                            The official peer-reviewed journal of
                            <RouterLink to="/sicama" class="font-medium text-institutional hover:underline">
                                {{ branding?.parent_org?.short_name || 'SICAMA' }}
                            </RouterLink>.
                            Browse published manuscripts, review author guidelines, and meet the editorial team.
                        </p>
                        <blockquote class="mt-5 max-w-2xl border-l-4 border-accent-gold pl-4 text-sm italic text-text-secondary">
                            “{{ journal?.motto || branding?.parent_org?.motto }}”
                        </blockquote>
                        <div class="mt-8 flex flex-wrap gap-3">
                            <RouterLink to="/journal/author-guidelines" class="btn-gold inline-flex items-center gap-2">
                                Author guidelines
                                <ArrowRightIcon class="size-4" aria-hidden="true" />
                            </RouterLink>
                            <RouterLink to="/journal/editorial-board" class="btn-institutional inline-flex items-center gap-2">
                                <UserGroupIcon class="size-4" aria-hidden="true" />
                                Editorial team
                            </RouterLink>
                            <RouterLink to="/member/journal/submit" class="btn-secondary inline-flex items-center gap-2 !rounded-xl">
                                Submit manuscript
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

        <section v-if="eicMessage" class="section-padding bg-white">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <article class="overflow-hidden rounded-3xl border border-institutional/10 bg-gradient-to-br from-surface-muted via-white to-institutional/5 shadow-sm">
                    <div class="border-b border-institutional/10 bg-institutional-dark px-6 py-5 text-white sm:px-10 sm:py-6">
                        <p class="label-caps text-accent-gold/90">From the Editor-in-Chief</p>
                        <h2 class="mt-2 font-display text-2xl font-bold sm:text-3xl">{{ eicMessage.title }}</h2>
                    </div>
                    <div class="px-6 py-8 sm:px-10 sm:py-10">
                        <div class="mx-auto max-w-3xl space-y-4">
                            <p
                                v-for="(paragraph, index) in eicParagraphs"
                                :key="`eic-${index}`"
                                class="text-base leading-relaxed text-text-secondary"
                            >
                                {{ paragraph }}
                            </p>
                            <div class="mt-8 border-t border-slate-200 pt-6">
                                <p class="text-sm text-text-secondary">Sincerely,</p>
                                <p class="mt-3 font-display text-lg font-bold text-institutional-dark">
                                    {{ eicMessage.signatory_name }}
                                </p>
                                <p class="text-sm font-medium text-institutional">{{ eicMessage.signatory_title }}</p>
                            </div>
                        </div>
                    </div>
                </article>
            </div>
        </section>

        <section class="section-padding bg-surface-muted">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="max-w-3xl">
                    <p class="label-caps text-institutional">About JOCRAMS</p>
                    <h2 class="mt-2 font-display text-3xl font-bold text-institutional-dark">A scholarly home for communication research</h2>
                </div>
                <div class="mt-8 grid gap-6 lg:grid-cols-2">
                    <div class="space-y-4 rounded-3xl border border-slate-100 bg-surface-muted/40 p-6 sm:p-8">
                        <p
                            v-for="(paragraph, index) in paragraphs(journal?.opening_statement)"
                            :key="`open-${index}`"
                            class="text-sm leading-relaxed text-text-secondary"
                        >
                            {{ paragraph }}
                        </p>
                    </div>
                    <div class="space-y-4 rounded-3xl border border-slate-100 bg-white p-6 shadow-sm sm:p-8">
                        <p
                            v-for="(paragraph, index) in paragraphs(journal?.about)"
                            :key="`about-${index}`"
                            class="text-sm leading-relaxed text-text-secondary"
                        >
                            {{ paragraph }}
                        </p>
                    </div>
                </div>

                <div class="mt-10 grid gap-6 md:grid-cols-2">
                    <div class="rounded-3xl bg-gradient-to-br from-institutional-dark to-institutional p-6 text-white sm:p-8">
                        <LightBulbIcon class="size-8 text-accent-gold" />
                        <h3 class="mt-4 font-display text-xl font-bold">Our Vision</h3>
                        <p class="mt-3 text-sm leading-relaxed text-slate-200">{{ journal?.vision }}</p>
                    </div>
                    <div class="rounded-3xl border border-institutional/15 bg-white p-6 shadow-sm sm:p-8">
                        <SparklesIcon class="size-8 text-institutional" />
                        <h3 class="mt-4 font-display text-xl font-bold text-institutional-dark">Our Mission</h3>
                        <div class="mt-3 space-y-3">
                            <p
                                v-for="(paragraph, index) in paragraphs(journal?.mission)"
                                :key="`mission-${index}`"
                                class="text-sm leading-relaxed text-text-secondary"
                            >
                                {{ paragraph }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="section-padding bg-white">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="label-caps text-institutional">Scope</p>
                        <h2 class="mt-2 font-display text-3xl font-bold text-institutional-dark">Areas of scholarly interest</h2>
                    </div>
                    <RouterLink to="/journal/author-guidelines" class="link-arrow text-sm">
                        Full guidelines
                        <ArrowRightIcon class="size-4" />
                    </RouterLink>
                </div>
                <div class="mt-8 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="area in areas"
                        :key="area"
                        class="rounded-2xl border border-white bg-white px-4 py-3 text-sm font-medium text-institutional-dark shadow-sm"
                    >
                        {{ area }}
                    </div>
                </div>

                <div class="mt-12 grid gap-8 lg:grid-cols-2">
                    <div>
                        <h3 class="font-display text-xl font-bold text-institutional-dark">What we publish</h3>
                        <ul class="mt-4 space-y-2">
                            <li
                                v-for="item in publishTypes"
                                :key="item"
                                class="rounded-xl bg-white px-4 py-3 text-sm text-text-secondary shadow-sm"
                            >
                                {{ item }}
                            </li>
                        </ul>
                    </div>
                    <div>
                        <h3 class="font-display text-xl font-bold text-institutional-dark">Why publish with JOCRAMS?</h3>
                        <div class="mt-4 space-y-3">
                            <div
                                v-for="item in whyPublish"
                                :key="item.title"
                                class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm"
                            >
                                <p class="font-semibold text-institutional-dark">{{ item.title }}</p>
                                <p class="mt-1 text-sm leading-relaxed text-text-secondary">{{ item.body }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="bg-white py-12 sm:py-16">
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
                    <RouterLink to="/journal/author-guidelines" class="btn-institutional mt-6 inline-flex">
                        Read author guidelines
                    </RouterLink>
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
