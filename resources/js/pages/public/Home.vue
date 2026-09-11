<script setup>
import { onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import {
    ArrowDownTrayIcon,
    ArrowRightIcon,
    BookOpenIcon,
    CalendarDaysIcon,
    DocumentTextIcon,
    GlobeAltIcon,
    IdentificationIcon,
    LinkIcon,
    MapPinIcon,
    NewspaperIcon,
    ShieldCheckIcon,
    TicketIcon,
    UsersIcon,
} from '@heroicons/vue/24/outline';
import { ShieldCheckIcon as ShieldCheckSolidIcon } from '@heroicons/vue/24/solid';
import AnimatedMetricStat from '../../components/landing/AnimatedMetricStat.vue';
import HeroCarousel from '../../components/landing/HeroCarousel.vue';
import ScrollReveal from '../../components/landing/ScrollReveal.vue';
import SectionHeading from '../../components/landing/SectionHeading.vue';
import { publicApi } from '../../api/client';
import { useSiteBranding } from '../../composables/useSiteBranding';
import { resolveHeroSlides } from '../../composables/useHeroSlides';

const { branding, loadBranding } = useSiteBranding();
const heroSlides = ref(resolveHeroSlides({}));

const news = ref([]);
const events = ref([]);
const downloads = ref([]);
const loadingNews = ref(true);
const loadingEvents = ref(true);
const loadingDownloads = ref(true);

const benefits = [
    { icon: IdentificationIcon, title: 'Digital Membership ID', description: 'Instant digital card with QR verification.' },
    { icon: ShieldCheckSolidIcon, title: 'Certificates', description: 'Download professional credentials anytime.' },
    { icon: BookOpenIcon, title: 'Resource Library', description: 'Exclusive publications and research papers.' },
    { icon: TicketIcon, title: 'Event Access', description: 'Priority registration and member discounts.' },
];

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

    try {
        await loadBranding();
        heroSlides.value = resolveHeroSlides({ branding: branding.value });

        const [newsRes, eventsRes, downloadsRes, callsRes] = await Promise.all([
            api.get('/news', { params: { per_page: 3 } }),
            api.get('/events'),
            api.get('/downloads'),
            api.get('/journal/calls/open').catch(() => ({ data: { data: [] } })),
        ]);

        news.value = newsRes.data.data.slice(0, 3);
        events.value = eventsRes.data.data.slice(0, 3);
        downloads.value = downloadsRes.data.data.slice(0, 4);

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
        <section class="relative min-h-[34rem] overflow-hidden pb-28 sm:min-h-[38rem] sm:pb-32">
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
                                { label: 'Trusted Since 1987', icon: ShieldCheckIcon },
                                { label: '10,000+ Active Members', icon: UsersIcon },
                                { label: '50+ Industry Partners', icon: LinkIcon },
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

        <!-- Metrics (floating bar) -->
        <section class="relative z-10 -mt-20 sm:-mt-24">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="card-modern grid gap-8 p-8 sm:grid-cols-2 sm:p-10 lg:grid-cols-4 lg:gap-6">
                    <AnimatedMetricStat :target="12450" suffix="+" label="Active Members">
                        <UsersIcon class="size-7" aria-hidden="true" />
                    </AnimatedMetricStat>
                    <AnimatedMetricStat :target="37" suffix="+" label="Years Established">
                        <CalendarDaysIcon class="size-7" aria-hidden="true" />
                    </AnimatedMetricStat>
                    <AnimatedMetricStat :target="1280" suffix="+" label="Published Documents">
                        <DocumentTextIcon class="size-7" aria-hidden="true" />
                    </AnimatedMetricStat>
                    <AnimatedMetricStat :target="28" label="Branch Offices">
                        <GlobeAltIcon class="size-7" aria-hidden="true" />
                    </AnimatedMetricStat>
                </div>
            </div>
        </section>

        <!-- News -->
        <section class="section-padding bg-white pt-28 sm:pt-32">
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
                        <p class="mt-4 text-base leading-relaxed text-slate-300">
                            Join thousands of professionals with access to digital credentials, exclusive resources, and event privileges.
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
