<script setup>
import { computed, onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import {
    AcademicCapIcon,
    ArrowRightIcon,
    CheckBadgeIcon,
    DocumentTextIcon,
    ScaleIcon,
} from '@heroicons/vue/24/outline';
import BankTransferCard from '../../components/payments/BankTransferCard.vue';
import { useOrgInfo } from '../../composables/useOrgInfo';

const { journal, bank, fees, contact, loadOrgInfo } = useOrgInfo();

const guidelines = computed(() => journal.value?.author_guidelines || null);

onMounted(() => {
    loadOrgInfo();
});
</script>

<template>
    <div>
        <section class="relative overflow-hidden border-b border-slate-200/80 bg-gradient-to-br from-institutional-dark via-institutional to-[#0c4a8c]">
            <div class="pointer-events-none absolute inset-0 opacity-30" aria-hidden="true">
                <div class="absolute -right-20 top-10 size-72 rounded-full bg-accent-gold/20 blur-3xl" />
                <div class="absolute -bottom-24 left-10 size-64 rounded-full bg-white/10 blur-3xl" />
            </div>
            <div class="relative mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
                <p class="label-caps text-accent-gold">For authors</p>
                <h1 class="mt-3 max-w-3xl font-display text-4xl font-bold tracking-tight text-white sm:text-5xl">
                    Author Guidelines
                </h1>
                <p class="mt-4 max-w-2xl text-lg leading-relaxed text-slate-200">
                    {{ guidelines?.intro || 'Prepare and submit manuscripts to JOCRAMS with confidence.' }}
                </p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <RouterLink to="/member/journal/submit" class="btn-gold inline-flex items-center gap-2">
                        Submit manuscript
                        <ArrowRightIcon class="size-4" />
                    </RouterLink>
                    <RouterLink to="/journal" class="btn-white-outline inline-flex items-center gap-2">
                        Browse journal
                    </RouterLink>
                </div>
            </div>
        </section>

        <section class="section-padding bg-white">
            <div class="mx-auto grid max-w-7xl gap-8 px-4 lg:grid-cols-[1.1fr_0.9fr] sm:px-6 lg:px-8">
                <div class="space-y-6">
                    <article class="card-modern p-6 sm:p-8">
                        <div class="flex items-center gap-3">
                            <span class="flex size-11 items-center justify-center rounded-2xl bg-institutional/10 text-institutional">
                                <DocumentTextIcon class="size-6" />
                            </span>
                            <h2 class="font-display text-2xl font-bold text-institutional-dark">Manuscript format</h2>
                        </div>
                        <p class="mt-4 text-sm leading-relaxed text-text-secondary">{{ guidelines?.language }}</p>
                        <ul class="mt-5 space-y-2.5">
                            <li
                                v-for="item in guidelines?.format || []"
                                :key="item"
                                class="flex gap-3 text-sm text-institutional-dark"
                            >
                                <CheckBadgeIcon class="mt-0.5 size-5 shrink-0 text-accent-gold" />
                                <span>{{ item }}</span>
                            </li>
                        </ul>
                    </article>

                    <article class="card-modern p-6 sm:p-8">
                        <div class="flex items-center gap-3">
                            <span class="flex size-11 items-center justify-center rounded-2xl bg-institutional/10 text-institutional">
                                <AcademicCapIcon class="size-6" />
                            </span>
                            <h2 class="font-display text-2xl font-bold text-institutional-dark">Article structure</h2>
                        </div>
                        <ol class="mt-5 columns-1 gap-x-8 space-y-2 sm:columns-2">
                            <li
                                v-for="(item, index) in guidelines?.structure || []"
                                :key="item"
                                class="break-inside-avoid text-sm text-text-secondary"
                            >
                                <span class="font-mono text-xs text-institutional">{{ String(index + 1).padStart(2, '0') }}</span>
                                {{ item }}
                            </li>
                        </ol>
                    </article>

                    <article class="card-modern p-6 sm:p-8">
                        <div class="flex items-center gap-3">
                            <span class="flex size-11 items-center justify-center rounded-2xl bg-institutional/10 text-institutional">
                                <ScaleIcon class="size-6" />
                            </span>
                            <h2 class="font-display text-2xl font-bold text-institutional-dark">Standards & review</h2>
                        </div>
                        <div class="mt-5 grid gap-4">
                            <div class="rounded-2xl bg-surface-muted/60 p-4">
                                <p class="text-sm font-semibold text-institutional-dark">Referencing</p>
                                <p class="mt-1 text-sm leading-relaxed text-text-secondary">{{ guidelines?.referencing }}</p>
                            </div>
                            <div class="rounded-2xl bg-surface-muted/60 p-4">
                                <p class="text-sm font-semibold text-institutional-dark">Similarity & AI screening</p>
                                <p class="mt-1 text-sm leading-relaxed text-text-secondary">{{ guidelines?.similarity }}</p>
                            </div>
                            <div class="rounded-2xl bg-surface-muted/60 p-4">
                                <p class="text-sm font-semibold text-institutional-dark">Peer review</p>
                                <p class="mt-1 text-sm leading-relaxed text-text-secondary">{{ guidelines?.peer_review }}</p>
                            </div>
                            <div class="rounded-2xl bg-surface-muted/60 p-4">
                                <p class="text-sm font-semibold text-institutional-dark">Ethics</p>
                                <p class="mt-1 text-sm leading-relaxed text-text-secondary">{{ guidelines?.ethics }}</p>
                            </div>
                        </div>
                    </article>

                    <article class="card-modern p-6 sm:p-8">
                        <h2 class="font-display text-2xl font-bold text-institutional-dark">Submission checklist</h2>
                        <ul class="mt-5 space-y-3">
                            <li
                                v-for="(item, index) in guidelines?.checklist || []"
                                :key="item"
                                class="flex gap-3 rounded-2xl border border-slate-100 p-3 text-sm text-text-secondary"
                            >
                                <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-institutional text-xs font-bold text-white">
                                    {{ index + 1 }}
                                </span>
                                <span>{{ item }}</span>
                            </li>
                        </ul>
                        <p class="mt-5 rounded-2xl border border-institutional/15 bg-institutional/5 p-4 text-sm leading-relaxed text-institutional-dark">
                            {{ guidelines?.submission_note }}
                        </p>
                        <p v-if="contact?.email || guidelines?.submission_email" class="mt-3 text-sm text-text-secondary">
                            Editorial email:
                            <a
                                :href="`mailto:${guidelines?.submission_email || contact?.email}`"
                                class="font-semibold text-institutional hover:underline"
                            >
                                {{ guidelines?.submission_email || contact?.email }}
                            </a>
                        </p>
                    </article>
                </div>

                <aside class="space-y-6 lg:sticky lg:top-24 lg:self-start">
                    <BankTransferCard
                        :bank="bank"
                        :fees="fees"
                        show-fees
                        title="Publication fees — UBA transfer"
                        description="Pay manuscript review and publication fees by direct transfer. Attach evidence when you email your manuscript."
                    />

                    <div class="card-modern p-6">
                        <h3 class="font-display text-lg font-bold text-institutional-dark">Fee summary</h3>
                        <dl class="mt-4 space-y-3 text-sm">
                            <div class="flex items-center justify-between gap-3 border-b border-slate-100 pb-3">
                                <dt class="text-text-secondary">Review fee (non-refundable)</dt>
                                <dd class="font-semibold text-institutional-dark">₦{{ Number(fees?.manuscript_review || 10000).toLocaleString() }}</dd>
                            </div>
                            <div class="flex items-center justify-between gap-3 border-b border-slate-100 pb-3">
                                <dt class="text-text-secondary">— Peer review</dt>
                                <dd class="font-medium">₦{{ Number(fees?.peer_review_processing || 5000).toLocaleString() }}</dd>
                            </div>
                            <div class="flex items-center justify-between gap-3 border-b border-slate-100 pb-3">
                                <dt class="text-text-secondary">— Turnitin / AI check</dt>
                                <dd class="font-medium">₦{{ Number(fees?.turnitin_check || 5000).toLocaleString() }}</dd>
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <dt class="text-text-secondary">Publication fee (on acceptance)</dt>
                                <dd class="font-semibold text-institutional-dark">₦{{ Number(fees?.publication || 30000).toLocaleString() }}</dd>
                            </div>
                        </dl>
                    </div>
                </aside>
            </div>
        </section>
    </div>
</template>
