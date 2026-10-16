<script setup>
import { computed, onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import {
    ArrowDownTrayIcon,
    ArrowRightIcon,
    BookOpenIcon,
    DocumentTextIcon,
    IdentificationIcon,
    MapPinIcon,
    NewspaperIcon,
    ShieldCheckIcon,
    TicketIcon,
    UsersIcon,
} from '@heroicons/vue/24/outline';
import { ShieldCheckIcon as ShieldCheckSolidIcon } from '@heroicons/vue/24/solid';
import HeroCarousel from '../../components/landing/HeroCarousel.vue';
import ScrollReveal from '../../components/landing/ScrollReveal.vue';
import SectionHeading from '../../components/landing/SectionHeading.vue';
import { publicApi, journalApi } from '../../api/client';
import { useSiteBranding } from '../../composables/useSiteBranding';
import { resolveHeroSlides } from '../../composables/useHeroSlides';
import { useOrgInfo } from '../../composables/useOrgInfo';
import { italicizeJournalTitleHtml } from '../../utils/journalTitle';

const { branding, loadBranding } = useSiteBranding();
const { journal, loadOrgInfo } = useOrgInfo();
const heroSlides = ref(resolveHeroSlides({}));

const news = ref([]);
const events = ref([]);
const downloads = ref([]);
const editorialBoard = ref([]);
const featuredBoard = ref([]);
const activeMemberCount = ref(null);
const loadingNews = ref(true);
const loadingEvents = ref(true);
const loadingDownloads = ref(true);

const formattedMemberCount = computed(() => {
    if (activeMemberCount.value == null || activeMemberCount.value < 1) {
        return null;
    }

    return activeMemberCount.value.toLocaleString('en-US');
});

const membershipCtaCopy = computed(() => {
    if (formattedMemberCount.value) {
        return `Join ${formattedMemberCount.value} active members — including SICAMA professionals — with access to digital credentials, exclusive resources, and event privileges.`;
    }

    return 'Join our growing community of SICAMA professionals with access to digital credentials, exclusive resources, and event privileges.';
});

const benefits = [
    { icon: IdentificationIcon, title: 'Digital Membership ID', description: 'Instant digital card with QR verification.' },
    { icon: ShieldCheckSolidIcon, title: 'Certificates', description: 'Download professional credentials anytime.' },
    { icon: BookOpenIcon, title: 'Resource Library', description: 'Exclusive publications and research papers.' },
    { icon: TicketIcon, title: 'Event Access', description: 'Priority registration and member discounts.' },
];

function paragraphs(text) {
    if (!text) {
        return [];
    }

    return String(text).split(/\n\s*\n/).map((part) => part.trim()).filter(Boolean);
}

function formatEventDate(dateString) {
    const date = new Date(dateString);

    return {
        month: date.toLocaleString('en-US', { month: 'short' }).toUpperCase(),
        day: date.getDate(),
    };
}

function formatPublishedDate(dateString) {
    return new Date(dateString).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

onMounted(async () => {
    const api = publicApi();
    const journalClient = journalApi();

    try {
        await Promise.all([loadBranding(), loadOrgInfo()]);
        heroSlides.value = resolveHeroSlides({ branding: branding.value });

        const [newsRes, eventsRes, downloadsRes, callsRes, boardRes, membersRes] = await Promise.all([
            api.get('/news', { params: { per_page: 3 } }),
            api.get('/events'),
            api.get('/downloads'),
            api.get('/journal/calls/open').catch(() => ({ data: { data: [] } })),
            journalClient.get('/editorial-board').catch(() => ({ data: { data: [] } })),
            // Public directory total includes SICAMA + MEM- actives (non-expired).
            api.get('/members', { params: { per_page: 1 } }).catch(() => ({ data: { meta: { total: 0 } } })),
        ]);

        news.value = newsRes.data.data.slice(0, 3);
        events.value = eventsRes.data.data.slice(0, 3);
        downloads.value = downloadsRes.data.data.slice(0, 4);
        editorialBoard.value = boardRes.data.data || [];
        featuredBoard.value = editorialBoard.value.slice(0, 6);
        activeMemberCount.value = Number(membersRes.data?.meta?.total) || 0;

        heroSlides.value = resolveHeroSlides({
            calls: callsRes.data.data || [],
            branding: branding.value,
        });
    } finally {
        loadingNews.value = false;
        loadingEvents.value = false;
        loadingDownloads.value = false;

        if (!heroSlides.value.length) {
            heroSlides.value = resolveHeroSlides({ branding: branding.value });
        }
    }
});
</script>

<template>
    <div>
        <!-- Hero -->
        <section class="relative min-h-[34rem] overflow-hidden pb-16 sm:min-h-[38rem] sm:pb-20">
            <HeroCarousel v-if="heroSlides.length" :slides="heroSlides" v-slot="{ slide, theme }">
                <div class="relative z-10 mx-auto max-w-7xl px-4 pt-16 sm:px-6 sm:pt-20 lg:px-8 lg:pt-24">
                    <div class="max-w-2xl lg:max-w-3xl">
                        <p
                            class="label-caps mb-4"
                            :class="theme === 'dark' ? 'text-accent-gold' : 'text-institutional'"
                        >
                            {{ slide.eyebrow }}
                        </p>
                        <h1
                            class="font-display font-extrabold leading-[1.02] tracking-[-0.03em] sm:text-5xl lg:text-[3.25rem]"
                            :class="[
                                theme === 'dark' ? 'text-white' : 'text-institutional-dark',
                                slide.compactTitle ? 'text-3xl sm:text-4xl lg:text-[2.75rem]' : 'text-4xl',
                            ]"
                        >
                            {{ slide.title }}
                            <span v-if="slide.highlight" class="accent-serif text-accent-gold">{{ slide.highlight }}</span><template v-if="slide.suffix">{{ slide.suffix }}</template>
                        </h1>
                        <p
                            class="mt-6 max-w-2xl text-lg leading-relaxed"
                            :class="theme === 'dark' ? 'text-slate-200' : 'text-text-secondary'"
                        >
                            {{ slide.description }}
                        </p>

                        <div class="mt-10 flex flex-wrap gap-4">
                            <RouterLink :to="slide.ctaTo" class="btn-gold hover:-translate-y-0.5">
                                {{ slide.ctaLabel }}
                            </RouterLink>
                            <RouterLink
                                v-if="slide.secondaryCtaTo"
                                :to="slide.secondaryCtaTo"
                                class="hover:-translate-y-0.5"
                                :class="theme === 'dark' ? 'btn-white-outline' : 'btn-institutional'"
                            >
                                {{ slide.secondaryCtaLabel }}
                            </RouterLink>
                        </div>
                    </div>

                    <div class="mt-12 flex flex-wrap gap-3">
                        <div
                            v-for="item in [
                                { label: 'ISSN 3156-2779', icon: BookOpenIcon },
                                { label: 'Peer-reviewed journal', icon: ShieldCheckIcon },
                                { label: 'Published by SICAMA', icon: UsersIcon },
                            ]"
                            :key="item.label"
                            class="inline-flex items-center gap-2.5 rounded-full px-4 py-2 text-sm font-medium shadow-sm backdrop-blur-sm"
                            :class="theme === 'dark'
                                ? 'border border-white/15 bg-white/10 text-white'
                                : 'border border-institutional/10 bg-white/80 text-institutional-dark'"
                        >
                            <span
                                class="flex size-7 shrink-0 items-center justify-center rounded-full"
                                :class="theme === 'dark' ? 'bg-white/15 text-accent-gold' : 'bg-institutional/8 text-institutional'"
                            >
                                <component :is="item.icon" class="size-4" aria-hidden="true" />
                            </span>
                            {{ item.label }}
                        </div>
                    </div>
                </div>
            </HeroCarousel>
        </section>

        <!-- About JOCRAMS — primary content for the About front page -->
        <section id="about" class="relative z-10 border-b border-slate-100 bg-white section-padding pt-12 sm:pt-16">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <ScrollReveal>
                    <div class="max-w-3xl">
                        <p class="label-caps text-institutional">About JOCRAMS</p>
                        <h2 class="mt-2 font-display text-3xl font-bold text-institutional-dark sm:text-4xl">
                            A scholarly home for communication research
                        </h2>
                        <blockquote class="mt-5 border-l-4 border-accent-gold pl-4 text-base italic text-text-secondary">
                            “{{ journal?.motto || branding?.parent_org?.motto || 'Advancing Communication and Media Scholarship for the Good of Society' }}”
                        </blockquote>
                    </div>
                </ScrollReveal>

                <ScrollReveal :delay="60">
                    <div class="mt-10 max-w-3xl space-y-5">
                        <p
                            v-for="(paragraph, index) in paragraphs(journal?.opening_statement)"
                            :key="`open-${index}`"
                            class="text-base leading-relaxed text-text-secondary"
                            v-html="italicizeJournalTitleHtml(paragraph)"
                        />
                    </div>
                </ScrollReveal>

                <ScrollReveal :delay="100">
                    <div class="mt-10 max-w-3xl space-y-5 border-t border-slate-100 pt-10">
                        <p
                            v-for="(paragraph, index) in paragraphs(journal?.about)"
                            :key="`about-${index}`"
                            class="text-base leading-relaxed text-text-secondary"
                            v-html="italicizeJournalTitleHtml(paragraph)"
                        />
                    </div>
                </ScrollReveal>

                <div class="mt-12 grid gap-6 md:grid-cols-2">
                    <ScrollReveal>
                        <div class="rounded-3xl bg-gradient-to-br from-institutional-dark to-institutional p-6 text-white sm:p-8">
                            <h3 class="font-display text-xl font-bold">Our Vision</h3>
                            <p class="mt-3 text-sm leading-relaxed text-slate-200">{{ journal?.vision }}</p>
                        </div>
                    </ScrollReveal>
                    <ScrollReveal :delay="80">
                        <div class="rounded-3xl border border-institutional/15 bg-surface-muted/40 p-6 sm:p-8">
                            <h3 class="font-display text-xl font-bold text-institutional-dark">Our Mission</h3>
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
                    </ScrollReveal>
                </div>
            </div>
        </section>

        <!-- Journal highlights -->
        <section class="section-padding bg-surface-muted">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <ScrollReveal>
                    <SectionHeading
                        eyebrow="The journal"
                        title="Research focus & publishing"
                        description="ISSN 3156-2779 · Peer-reviewed scholarship published by SICAMA"
                        link-to="/journal"
                        link-label="Open journal hub"
                    />
                </ScrollReveal>

                <div class="mt-10 grid gap-8 lg:grid-cols-2">
                    <ScrollReveal>
                        <div>
                            <h3 class="font-display text-lg font-semibold text-institutional-dark">Areas of interest</h3>
                            <div class="mt-4 flex flex-wrap gap-2">
                                <span
                                    v-for="area in (journal?.areas_of_interest || [])"
                                    :key="area"
                                    class="rounded-full border border-institutional/10 bg-white px-3 py-1.5 text-xs font-medium text-institutional-dark"
                                >
                                    {{ area }}
                                </span>
                            </div>
                        </div>
                    </ScrollReveal>
                    <ScrollReveal :delay="80">
                        <div>
                            <h3 class="font-display text-lg font-semibold text-institutional-dark">What we publish</h3>
                            <ul class="mt-4 grid gap-2 sm:grid-cols-2">
                                <li
                                    v-for="item in journal?.what_we_publish || []"
                                    :key="item"
                                    class="flex gap-2 text-sm text-text-secondary"
                                >
                                    <span class="mt-1.5 size-1.5 shrink-0 rounded-full bg-accent-gold" />
                                    {{ item }}
                                </li>
                            </ul>
                            <div class="mt-6 flex flex-wrap gap-3">
                                <RouterLink to="/journal/author-guidelines" class="btn-institutional text-sm">Author guidelines</RouterLink>
                                <RouterLink to="/journal/editorial-board" class="btn-secondary !rounded-xl text-sm">Full editorial board</RouterLink>
                            </div>
                        </div>
                    </ScrollReveal>
                </div>
            </div>
        </section>

        <!-- Editorial board preview -->
        <section v-if="featuredBoard.length" class="section-padding bg-white">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <ScrollReveal>
                    <SectionHeading
                        eyebrow="Leadership"
                        title="Editorial board"
                        description="Scholars guiding peer review and the academic direction of JOCRAMS"
                        link-to="/journal/editorial-board"
                        link-label="View all members"
                    />
                </ScrollReveal>

                <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    <ScrollReveal v-for="(member, index) in featuredBoard" :key="member.uuid" :delay="index * 60">
                        <div class="h-full rounded-2xl border border-slate-100 bg-surface-muted/30 p-5">
                            <p class="font-display text-lg font-semibold text-institutional-dark">{{ member.name }}</p>
                            <p class="mt-1 text-sm font-medium text-institutional">{{ member.role_title }}</p>
                            <p v-if="member.affiliation" class="mt-2 text-sm text-text-secondary">{{ member.affiliation }}</p>
                        </div>
                    </ScrollReveal>
                </div>
            </div>
        </section>

        <!-- News -->
        <section class="section-padding bg-surface-muted pt-16 sm:pt-20">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <ScrollReveal>
                    <SectionHeading
                        eyebrow="Latest"
                        title="News & Updates"
                        description="Latest announcements from the association"
                        link-to="/news"
                        link-label="View all news"
                    />
                </ScrollReveal>

                <div v-if="loadingNews" class="mt-12 flex justify-center">
                    <div class="size-8 animate-spin rounded-full border-2 border-institutional/20 border-t-institutional" />
                </div>
                <div v-else class="mt-12 grid auto-rows-fr gap-8 sm:grid-cols-2 lg:grid-cols-3">
                    <ScrollReveal v-for="(article, index) in news" :key="article.uuid" :delay="index * 100">
                        <RouterLink :to="`/news/${article.uuid}`" class="group hover-lift card-modern flex h-full flex-col overflow-hidden">
                            <div class="relative aspect-[16/10] shrink-0 overflow-hidden bg-gradient-to-br from-institutional/15 via-slate-100 to-accent-gold/20">
                                <div class="absolute inset-0 opacity-[0.07]" style="background-image: radial-gradient(circle at 1px 1px, #0a3d91 1px, transparent 0); background-size: 20px 20px;" />
                                <NewspaperIcon class="absolute inset-0 m-auto size-12 text-institutional/25 transition duration-500 group-hover:scale-110 group-hover:text-institutional/40" aria-hidden="true" />
                            </div>
                            <div class="flex flex-1 flex-col p-6 sm:p-7">
                                <span class="label-caps w-fit rounded-full bg-accent-gold/15 px-3 py-1 text-institutional-dark">
                                    {{ article.category?.name || 'News' }}
                                </span>
                                <h3 class="mt-4 line-clamp-2 font-display text-xl font-semibold text-institutional-dark transition group-hover:text-institutional">
                                    {{ article.title }}
                                </h3>
                                <p class="mt-3 line-clamp-3 flex-1 text-sm leading-relaxed text-text-secondary">{{ article.excerpt }}</p>
                                <p class="mt-5 text-xs font-medium text-text-secondary/80">{{ formatPublishedDate(article.published_at) }}</p>
                            </div>
                        </RouterLink>
                    </ScrollReveal>
                </div>
            </div>
        </section>

        <!-- Events -->
        <section class="section-padding bg-surface-muted">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <ScrollReveal>
                    <SectionHeading
                        eyebrow="Calendar"
                        title="Upcoming Events"
                        description="Conferences, workshops, and member gatherings"
                        link-to="/events"
                        link-label="View all events"
                    />
                </ScrollReveal>

                <div v-if="loadingEvents" class="mt-12 flex justify-center">
                    <div class="size-8 animate-spin rounded-full border-2 border-institutional/20 border-t-institutional" />
                </div>
                <div v-else class="mt-12 grid auto-rows-fr gap-8 lg:grid-cols-3">
                    <ScrollReveal v-for="(event, index) in events" :key="event.uuid" :delay="index * 100">
                        <article class="hover-lift card-modern flex h-full flex-col overflow-hidden">
                            <div class="flex items-center gap-4 border-b border-slate-100 bg-gradient-to-r from-institutional/5 to-transparent px-6 py-4">
                                <div class="flex size-16 shrink-0 flex-col items-center justify-center rounded-xl bg-institutional text-white shadow-md shadow-institutional/25">
                                    <span class="text-[10px] font-bold uppercase tracking-wider">{{ formatEventDate(event.starts_at).month }}</span>
                                    <span class="font-display text-2xl font-bold leading-none">{{ formatEventDate(event.starts_at).day }}</span>
                                </div>
                                <div class="min-w-0">
                                    <h3 class="line-clamp-2 font-display text-lg font-semibold text-institutional-dark">{{ event.title }}</h3>
                                </div>
                            </div>
                            <div class="flex flex-1 flex-col p-6">
                                <p class="line-clamp-3 flex-1 text-sm leading-relaxed text-text-secondary">{{ event.description }}</p>
                                <div class="mt-5 flex items-center gap-1.5 text-xs text-text-secondary">
                                    <MapPinIcon class="size-4 shrink-0 text-institutional/70" aria-hidden="true" />
                                    {{ event.location || 'Virtual' }}
                                </div>
                                <RouterLink to="/events" class="link-arrow group mt-5 w-fit">
                                    Register Now
                                    <ArrowRightIcon class="size-4 transition group-hover:translate-x-0.5" aria-hidden="true" />
                                </RouterLink>
                            </div>
                        </article>
                    </ScrollReveal>
                </div>
            </div>
        </section>

        <!-- Resources -->
        <section class="section-padding bg-white">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <ScrollReveal>
                    <SectionHeading
                        eyebrow="Library"
                        title="Public Resources"
                        description="Download publications, reports, and guidelines"
                        link-to="/downloads"
                        link-label="Browse library"
                    />
                </ScrollReveal>

                <div v-if="loadingDownloads" class="mt-12 flex justify-center">
                    <div class="size-8 animate-spin rounded-full border-2 border-institutional/20 border-t-institutional" />
                </div>
                <div v-else-if="downloads.length === 0" class="empty-state mt-12">
                    <DocumentTextIcon class="mx-auto size-10 text-institutional/30" aria-hidden="true" />
                    <p class="mt-4 font-medium text-institutional-dark">No public resources yet</p>
                    <p class="mt-1 text-sm text-text-secondary">Check back soon or sign in for members-only materials.</p>
                    <RouterLink to="/member/login" class="btn-institutional mt-6 inline-flex text-sm">Member Login</RouterLink>
                </div>
                <div v-else class="mt-12 grid auto-rows-fr gap-5 sm:grid-cols-2">
                    <ScrollReveal v-for="(item, index) in downloads" :key="item.uuid" :delay="index * 80">
                        <div class="group hover-lift card-modern flex h-full items-center gap-5 p-5 sm:p-6">
                            <span class="flex size-14 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-red-50 to-orange-50 text-red-600 ring-1 ring-red-100">
                                <DocumentTextIcon class="size-7" aria-hidden="true" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <h3 class="line-clamp-2 font-semibold text-institutional-dark">{{ item.title }}</h3>
                                <p class="mt-1 line-clamp-2 text-sm text-text-secondary">{{ item.description || 'Association publication' }}</p>
                            </div>
                            <RouterLink
                                :to="`/downloads`"
                                class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-institutional/8 text-institutional transition hover:bg-institutional hover:text-white"
                            >
                                <ArrowDownTrayIcon class="size-5" aria-hidden="true" />
                            </RouterLink>
                        </div>
                    </ScrollReveal>
                </div>
            </div>
        </section>

        <!-- Membership CTA -->
        <section class="relative overflow-hidden bg-gradient-to-br from-institutional-dark via-institutional to-[#0c4a8c] section-padding">
            <div class="pointer-events-none absolute inset-0 opacity-30" aria-hidden="true">
                <div class="absolute -right-24 -top-24 size-96 rounded-full bg-accent-gold/20 blur-3xl" />
                <div class="absolute -bottom-32 -left-24 size-80 rounded-full bg-white/10 blur-3xl" />
            </div>

            <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <ScrollReveal animation="scale-in">
                    <div class="mx-auto max-w-2xl text-center">
                        <p class="label-caps text-accent-gold/90">Membership</p>
                        <h2 class="mt-3 font-display text-3xl font-bold text-white sm:text-4xl">Unlock Exclusive Member Benefits</h2>
                        <p
                            v-if="formattedMemberCount"
                            class="mt-4 inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-4 py-1.5 text-sm font-medium text-white"
                        >
                            <UsersIcon class="size-4 text-accent-gold" aria-hidden="true" />
                            {{ formattedMemberCount }} active members
                        </p>
                        <p class="mt-4 text-base leading-relaxed text-slate-300">
                            {{ membershipCtaCopy }}
                        </p>
                    </div>
                </ScrollReveal>

                <div class="mt-14 grid auto-rows-fr gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    <ScrollReveal v-for="(benefit, index) in benefits" :key="benefit.title" :delay="index * 80">
                        <div class="benefit-card">
                            <div class="benefit-icon-wrap">
                                <component :is="benefit.icon" class="size-7 text-accent-gold" aria-hidden="true" />
                            </div>
                            <h3 class="mt-5 font-semibold text-white">{{ benefit.title }}</h3>
                            <p class="mt-2 flex-1 text-sm leading-relaxed text-slate-300">{{ benefit.description }}</p>
                        </div>
                    </ScrollReveal>
                </div>

                <ScrollReveal :delay="160">
                    <div class="mt-14 text-center">
                        <RouterLink to="/member/register" class="btn-gold inline-flex hover:-translate-y-0.5">
                            Join Us Today
                        </RouterLink>
                    </div>
                </ScrollReveal>
            </div>
        </section>
    </div>
</template>
