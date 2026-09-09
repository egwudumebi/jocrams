<script setup>
import { computed, onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { ArrowLeftIcon, UserGroupIcon } from '@heroicons/vue/24/outline';
import { journalApi } from '../../api/client';
import { useSiteBranding } from '../../composables/useSiteBranding';
import { renderRichTextHtml, RICH_TEXT_PROSE_CLASSES } from '../../utils/html';

const { branding, loadBranding } = useSiteBranding();
const members = ref([]);
const loading = ref(true);

const groupedMembers = computed(() => {
    const groups = new Map();

    for (const member of members.value) {
        const role = member.role_title || 'Editorial Board Member';

        if (!groups.has(role)) {
            groups.set(role, []);
        }

        groups.get(role).push(member);
    }

    return [...groups.entries()];
});

function memberInitials(name) {
    if (!name) {
        return '?';
    }

    return name
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part.charAt(0))
        .join('')
        .toUpperCase();
}

onMounted(async () => {
    await loadBranding();

    try {
        const { data } = await journalApi().get('/editorial-board');
        members.value = data.data || [];
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <div>
        <section class="border-b border-slate-200/80 bg-gradient-to-br from-surface-muted via-white to-institutional/5">
            <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
                <RouterLink to="/journal" class="inline-flex items-center gap-2 text-sm font-semibold text-institutional transition hover:text-institutional-dark">
                    <ArrowLeftIcon class="size-4" aria-hidden="true" />
                    Back to journal
                </RouterLink>
                <div class="mt-6 flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                    <div class="max-w-3xl">
                        <p class="label-caps text-institutional">{{ branding?.site_name || 'JOCRAMS' }}</p>
                        <h1 class="mt-2 font-display text-4xl font-bold tracking-tight text-institutional-dark sm:text-5xl">
                            Editorial Team
                        </h1>
                        <p class="mt-4 text-lg leading-relaxed text-text-secondary">
                            The editorial team guiding peer review, production, and publication for
                            {{ branding?.journal_full_name || branding?.site_tagline }}.
                        </p>
                        <p class="mt-3 text-sm text-text-secondary">
                            Published by
                            <RouterLink to="/sicama" class="font-medium text-institutional hover:underline">
                                {{ branding?.parent_org?.short_name || 'SICAMA' }}
                            </RouterLink>
                        </p>
                    </div>
                    <img
                        :src="branding?.parent_org?.logo_url || '/images/sicama-logo.png'"
                        alt="SICAMA logo"
                        class="h-24 w-auto object-contain lg:h-28"
                    />
                </div>
            </div>
        </section>

        <section class="section-padding bg-white">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div v-if="loading" class="flex justify-center py-16">
                    <div class="size-10 animate-spin rounded-full border-2 border-institutional/20 border-t-institutional" />
                </div>

                <div v-else-if="!members.length" class="empty-state mx-auto max-w-xl">
                    <UserGroupIcon class="mx-auto size-12 text-institutional/25" aria-hidden="true" />
                    <h2 class="mt-5 font-display text-2xl font-bold text-institutional-dark">Editorial team coming soon</h2>
                    <p class="mt-3 text-text-secondary">Board members will be listed here as appointments are confirmed.</p>
                </div>

                <div v-else class="space-y-10">
                    <section v-for="[role, roleMembers] in groupedMembers" :key="role">
                        <h2 class="font-display text-2xl font-bold text-institutional-dark">{{ role }}</h2>
                        <div class="mt-5 grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                            <article
                                v-for="member in roleMembers"
                                :key="member.uuid"
                                class="card-modern flex h-full flex-col p-6"
                            >
                                <div class="flex items-start gap-4">
                                    <img
                                        v-if="member.profile_image"
                                        :src="member.profile_image"
                                        :alt="member.name"
                                        class="size-14 rounded-full object-cover"
                                    />
                                    <div
                                        v-else
                                        class="flex size-14 items-center justify-center rounded-full bg-institutional/10 text-lg font-bold text-institutional"
                                    >
                                        {{ memberInitials(member.name) }}
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <h3 class="font-semibold text-institutional-dark">{{ member.name }}</h3>
                                        <p class="text-sm text-institutional">{{ member.role_title }}</p>
                                    </div>
                                </div>
                                <p v-if="member.affiliation" class="mt-4 text-sm leading-relaxed text-text-secondary">
                                    {{ member.affiliation }}
                                </p>
                                <div
                                    v-if="member.bio"
                                    :class="['mt-4 text-sm', RICH_TEXT_PROSE_CLASSES]"
                                    v-html="renderRichTextHtml(member.bio)"
                                />
                            </article>
                        </div>
                    </section>
                </div>
            </div>
        </section>
    </div>
</template>
