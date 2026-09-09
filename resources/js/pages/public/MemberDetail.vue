<script setup>
import { computed, ref, onMounted, watch } from 'vue';
import { useRoute, RouterLink } from 'vue-router';
import { applyDynamicMemberSeo } from '../../composables/useSeo';
import { useSiteBranding } from '../../composables/useSiteBranding';
import {
    ArrowLeftIcon,
    CalendarDaysIcon,
    IdentificationIcon,
    LinkIcon,
    MapPinIcon,
    ShieldCheckIcon,
    TrophyIcon,
} from '@heroicons/vue/24/outline';
import { publicApi } from '../../api/client';

const route = useRoute();
const { branding, loadBranding } = useSiteBranding();
const member = ref(null);
const loading = ref(true);
const error = ref('');

const locationLabel = computed(() => {
    if (!member.value) {
        return '';
    }

    return [member.value.city, member.value.state, member.value.country].filter(Boolean).join(', ');
});

const socialLinks = computed(() => {
    const links = member.value?.social_links;

    if (!links || typeof links !== 'object') {
        return [];
    }

    return Object.entries(links)
        .filter(([, url]) => typeof url === 'string' && url.trim() !== '')
        .map(([label, url]) => ({ label, url }));
});

function formatJoinedDate(dateString) {
    if (!dateString) {
        return '';
    }

    return new Date(dateString).toLocaleDateString('en-US', {
        month: 'long',
        day: 'numeric',
        year: 'numeric',
    });
}

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
        const { data } = await publicApi().get(`/members/${route.params.uuid}`);
        member.value = data.data;
    } catch {
        error.value = 'Member not found.';
    } finally {
        loading.value = false;
    }
});

watch(member, (value) => {
    if (!value) {
        return;
    }

    applyDynamicMemberSeo(route, branding.value || window.__APP_SEO__?.branding || {}, value);
});
</script>

<template>
    <div>
        <section class="border-b border-slate-200/80 bg-gradient-to-br from-surface-muted via-white to-institutional/5">
            <div class="mx-auto max-w-4xl px-4 py-10 sm:px-6 lg:px-8">
                <RouterLink to="/members" class="inline-flex items-center gap-2 text-sm font-semibold text-institutional transition hover:text-institutional-dark">
                    <ArrowLeftIcon class="size-4" aria-hidden="true" />
                    Back to directory
                </RouterLink>

                <div v-if="loading" class="mt-10 flex justify-center py-16">
                    <div class="size-10 animate-spin rounded-full border-2 border-institutional/20 border-t-institutional" />
                </div>

                <div v-else-if="error" class="mt-8 rounded-2xl border border-red-200 bg-red-50 p-6 text-red-700">
                    {{ error }}
                </div>

                <div v-else class="mt-8 overflow-hidden rounded-2xl bg-gradient-to-r from-institutional to-brand-700 shadow-lg">
                    <div class="flex flex-col gap-6 p-6 sm:flex-row sm:items-center sm:p-8">
                        <img
                            v-if="member.profile_image"
                            :src="member.profile_image"
                            :alt="member.name"
                            class="size-24 rounded-full border-4 border-white/30 object-cover shadow-md sm:size-28"
                        />
                        <div
                            v-else
                            class="flex size-24 items-center justify-center rounded-full border-4 border-white/30 bg-white/15 text-3xl font-bold text-white sm:size-28"
                        >
                            {{ memberInitials(member.name) }}
                        </div>
                        <div class="min-w-0 flex-1 text-white">
                            <p class="label-caps text-white/70">Member profile</p>
                            <h1 class="mt-1 font-display text-3xl font-bold sm:text-4xl">{{ member.name }}</h1>
                            <p class="mt-2 text-lg text-white/85">{{ member.tier?.name }}</p>
                            <p v-if="member.position" class="mt-1 text-sm text-white/75">{{ member.position }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section v-if="member && !loading" class="section-padding bg-white">
            <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                <div class="grid gap-6 lg:grid-cols-[1.4fr_1fr]">
                    <div class="space-y-6">
                        <article v-if="member.professional_bio" class="card-modern p-6 sm:p-8">
                            <h2 class="font-display text-xl font-bold text-institutional-dark">About</h2>
                            <p class="mt-4 whitespace-pre-line text-base leading-relaxed text-text-secondary">
                                {{ member.professional_bio }}
                            </p>
                        </article>

                        <article v-if="member.achievements?.length" class="card-modern p-6 sm:p-8">
                            <div class="flex items-center gap-2">
                                <TrophyIcon class="size-5 text-institutional" aria-hidden="true" />
                                <h2 class="font-display text-xl font-bold text-institutional-dark">Achievements</h2>
                            </div>
                            <ul class="mt-4 space-y-3">
                                <li
                                    v-for="(achievement, index) in member.achievements"
                                    :key="index"
                                    class="rounded-xl border border-slate-100 bg-slate-50/60 px-4 py-3 text-sm text-text-secondary"
                                >
                                    {{ achievement }}
                                </li>
                            </ul>
                        </article>
                    </div>

                    <aside class="space-y-6">
                        <div class="card-modern p-6">
                            <h2 class="font-display text-lg font-bold text-institutional-dark">Membership details</h2>
                            <dl class="mt-5 space-y-4 text-sm">
                                <div class="flex gap-3">
                                    <IdentificationIcon class="size-5 shrink-0 text-institutional" aria-hidden="true" />
                                    <div>
                                        <dt class="text-text-secondary">Member number</dt>
                                        <dd class="mt-0.5 font-mono font-medium text-institutional-dark">{{ member.membership_number }}</dd>
                                    </div>
                                </div>
                                <div v-if="member.joined_at" class="flex gap-3">
                                    <CalendarDaysIcon class="size-5 shrink-0 text-institutional" aria-hidden="true" />
                                    <div>
                                        <dt class="text-text-secondary">Member since</dt>
                                        <dd class="mt-0.5 font-medium text-institutional-dark">{{ formatJoinedDate(member.joined_at) }}</dd>
                                    </div>
                                </div>
                                <div v-if="locationLabel" class="flex gap-3">
                                    <MapPinIcon class="size-5 shrink-0 text-institutional" aria-hidden="true" />
                                    <div>
                                        <dt class="text-text-secondary">Location</dt>
                                        <dd class="mt-0.5 font-medium text-institutional-dark">{{ locationLabel }}</dd>
                                    </div>
                                </div>
                                <div v-if="member.orcid_url" class="flex gap-3">
                                    <LinkIcon class="size-5 shrink-0 text-institutional" aria-hidden="true" />
                                    <div>
                                        <dt class="text-text-secondary">ORCID</dt>
                                        <dd class="mt-0.5">
                                            <a
                                                :href="member.orcid_url"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="font-medium text-institutional hover:underline"
                                            >
                                                {{ member.orcid || member.orcid_url }}
                                            </a>
                                        </dd>
                                    </div>
                                </div>
                            </dl>
                        </div>

                        <div v-if="socialLinks.length" class="card-modern p-6">
                            <h2 class="font-display text-lg font-bold text-institutional-dark">Links</h2>
                            <ul class="mt-4 space-y-2">
                                <li v-for="link in socialLinks" :key="link.label">
                                    <a
                                        :href="link.url"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="inline-flex items-center gap-2 text-sm font-medium text-institutional hover:underline"
                                    >
                                        <LinkIcon class="size-4" aria-hidden="true" />
                                        {{ link.label }}
                                    </a>
                                </li>
                            </ul>
                        </div>

                        <div class="card-modern border-institutional/15 bg-institutional/5 p-6">
                            <div class="flex gap-3">
                                <ShieldCheckIcon class="size-6 shrink-0 text-institutional" aria-hidden="true" />
                                <div>
                                    <h2 class="font-display text-lg font-bold text-institutional-dark">Verify credentials</h2>
                                    <p class="mt-2 text-sm leading-relaxed text-text-secondary">
                                        Confirm this member&apos;s issued credentials using our public verification tool.
                                    </p>
                                    <RouterLink to="/verify" class="btn-institutional mt-4 inline-flex !rounded-xl text-sm">
                                        Verify a credential
                                    </RouterLink>
                                </div>
                            </div>
                        </div>
                    </aside>
                </div>
            </div>
        </section>
    </div>
</template>
