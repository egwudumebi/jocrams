<script setup>
import { computed, onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import {
    ArrowRightIcon,
    BookOpenIcon,
    BuildingLibraryIcon,
    UserGroupIcon,
} from '@heroicons/vue/24/outline';
import { useSiteBranding } from '../../composables/useSiteBranding';

const { branding, sicama, loadBranding, loadSicama } = useSiteBranding();
const loading = computed(() => !sicama.value);

const profile = computed(() => sicama.value || null);
const parentOrg = computed(() => profile.value?.parent_org || branding.value?.parent_org);
const journal = computed(() => profile.value?.journal || null);
const botResolution = computed(() => profile.value?.bot_resolution || null);
const interimExco = computed(() => profile.value?.interim_exco || null);

function formatDate(dateString) {
    if (!dateString) {
        return '';
    }

    return new Date(dateString).toLocaleDateString('en-US', {
        month: 'long',
        day: 'numeric',
        year: 'numeric',
    });
}

onMounted(async () => {
    await Promise.all([loadBranding(), loadSicama()]);
});
</script>

<template>
    <div>
        <section class="border-b border-slate-200/80 bg-gradient-to-br from-surface-muted via-white to-institutional/5">
            <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 sm:py-16 lg:px-8">
                <div class="grid items-center gap-10 lg:grid-cols-[1.1fr_0.9fr]">
                    <div>
                        <p class="label-caps text-institutional">Parent Organization</p>
                        <h1 class="mt-2 font-display text-4xl font-bold tracking-tight text-institutional-dark sm:text-5xl">
                            {{ parentOrg?.short_name || 'SICAMA' }}
                        </h1>
                        <p class="mt-3 text-lg font-medium text-institutional">
                            {{ parentOrg?.full_name }}
                        </p>
                        <blockquote class="mt-6 border-l-4 border-accent-gold pl-5 text-lg italic leading-relaxed text-text-secondary">
                            “{{ parentOrg?.motto }}”
                        </blockquote>
                        <p class="mt-6 max-w-2xl text-base leading-relaxed text-text-secondary">
                            SICAMA is the parent body behind
                            <strong class="text-institutional-dark">{{ journal?.short_name || 'JOCRAMS' }}</strong>,
                            the association’s official journal advancing communication and media scholarship for the good of society.
                        </p>
                        <div class="mt-8 flex flex-wrap gap-3">
                            <RouterLink to="/journal" class="btn-institutional inline-flex items-center gap-2">
                                Visit {{ journal?.short_name || 'JOCRAMS' }}
                                <ArrowRightIcon class="size-4" aria-hidden="true" />
                            </RouterLink>
                            <RouterLink to="/journal/editorial-board" class="btn-secondary inline-flex items-center gap-2 !rounded-xl">
                                Editorial team
                            </RouterLink>
                        </div>
                    </div>

                    <div class="flex justify-center lg:justify-end">
                        <div class="card-modern max-w-md overflow-hidden p-6 sm:p-8">
                            <img
                                :src="parentOrg?.logo_url || '/images/sicama-logo.png'"
                                :alt="`${parentOrg?.short_name || 'SICAMA'} logo`"
                                class="mx-auto max-h-56 w-full object-contain"
                            />
                            <div class="mt-6 rounded-xl bg-institutional/5 p-4 text-center">
                                <p class="text-xs font-semibold uppercase tracking-wide text-institutional">Official Journal</p>
                                <p class="mt-1 font-display text-lg font-bold text-institutional-dark">{{ journal?.full_name }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="section-padding bg-white">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div v-if="loading" class="flex justify-center py-16">
                    <div class="size-10 animate-spin rounded-full border-2 border-institutional/20 border-t-institutional" />
                </div>

                <div v-else class="grid gap-8 xl:grid-cols-2">
                    <article class="card-modern overflow-hidden">
                        <div class="border-b border-slate-100 bg-slate-50/70 px-6 py-4">
                            <div class="flex items-center gap-3">
                                <BuildingLibraryIcon class="size-5 text-institutional" aria-hidden="true" />
                                <div>
                                    <h2 class="font-display text-xl font-bold text-institutional-dark">{{ botResolution?.title }}</h2>
                                    <p class="text-sm text-text-secondary">Meeting held {{ formatDate(botResolution?.meeting_date) }}</p>
                                </div>
                            </div>
                        </div>
                        <div class="space-y-6 p-6 sm:p-8">
                            <p class="text-base leading-relaxed text-text-secondary">{{ botResolution?.summary }}</p>

                            <div
                                v-for="(decision, index) in botResolution?.decisions || []"
                                :key="decision.title"
                                class="rounded-xl border border-slate-100 bg-slate-50/50 p-5"
                            >
                                <p class="text-xs font-semibold uppercase tracking-wide text-institutional">Decision {{ index + 1 }}</p>
                                <h3 class="mt-2 font-semibold text-institutional-dark">{{ decision.title }}</h3>
                                <p class="mt-2 text-sm leading-relaxed text-text-secondary">{{ decision.body }}</p>
                                <ul v-if="decision.responsibilities?.length" class="mt-3 space-y-2 text-sm text-text-secondary">
                                    <li v-for="item in decision.responsibilities" :key="item" class="flex gap-2">
                                        <span class="mt-1.5 size-1.5 shrink-0 rounded-full bg-institutional" />
                                        <span>{{ item }}</span>
                                    </li>
                                </ul>
                                <p v-if="decision.note" class="mt-3 text-sm italic text-text-secondary/90">{{ decision.note }}</p>
                            </div>

                            <div class="rounded-xl border border-institutional/15 bg-institutional/5 p-4 text-sm text-text-secondary">
                                <p>Signed for the BOT</p>
                                <p class="mt-2 font-semibold text-institutional-dark">{{ botResolution?.signed_by }}</p>
                                <p>{{ botResolution?.signed_role }}</p>
                                <p class="mt-1">{{ formatDate(botResolution?.signed_date) }}</p>
                            </div>
                        </div>
                    </article>

                    <article class="card-modern overflow-hidden">
                        <div class="border-b border-slate-100 bg-slate-50/70 px-6 py-4">
                            <div class="flex items-center gap-3">
                                <UserGroupIcon class="size-5 text-institutional" aria-hidden="true" />
                                <div>
                                    <h2 class="font-display text-xl font-bold text-institutional-dark">Interim Executive Committee</h2>
                                    <p class="text-sm text-text-secondary">
                                        Effective {{ formatDate(interimExco?.term_start) }} · {{ interimExco?.term_duration }}
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="p-6 sm:p-8">
                            <ul class="space-y-3">
                                <li
                                    v-for="member in interimExco?.members || []"
                                    :key="`${member.role}-${member.name}`"
                                    class="flex flex-col gap-1 rounded-xl border border-slate-100 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-wide text-institutional">{{ member.role }}</p>
                                        <p class="font-medium text-institutional-dark">{{ member.name }}</p>
                                    </div>
                                    <a
                                        v-if="member.phone"
                                        :href="`tel:${member.phone}`"
                                        class="text-sm text-text-secondary transition hover:text-institutional"
                                    >
                                        {{ member.phone }}
                                    </a>
                                </li>
                            </ul>
                            <p v-if="interimExco?.note" class="mt-5 text-sm leading-relaxed text-text-secondary">
                                {{ interimExco.note }}
                            </p>
                        </div>
                    </article>
                </div>
            </div>
        </section>

        <section class="border-t border-slate-200/80 bg-surface-muted py-14 sm:py-16">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="card-modern flex flex-col items-center gap-6 p-8 text-center sm:p-10 lg:flex-row lg:text-left">
                    <div class="flex size-16 shrink-0 items-center justify-center rounded-2xl bg-institutional/10 text-institutional">
                        <BookOpenIcon class="size-8" aria-hidden="true" />
                    </div>
                    <div class="flex-1">
                        <h2 class="font-display text-2xl font-bold text-institutional-dark">Explore the journal</h2>
                        <p class="mt-2 max-w-2xl text-text-secondary">
                            Read published articles, meet the editorial team, and submit manuscripts to JOCRAMS.
                        </p>
                    </div>
                    <div class="flex flex-wrap justify-center gap-3">
                        <RouterLink to="/journal" class="btn-institutional shrink-0">Published articles</RouterLink>
                        <RouterLink to="/member/journal/submit" class="btn-secondary shrink-0 !rounded-xl">Submit manuscript</RouterLink>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>
