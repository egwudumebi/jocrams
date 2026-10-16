<script setup>
import { computed, onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { XMarkIcon } from '@heroicons/vue/24/outline';
import { publicApi } from '../../api/client';

const banner = ref(null);
const dismissed = ref(false);

const styleClass = computed(() => {
    const map = {
        info: 'bg-institutional text-white',
        warning: 'bg-amber-500 text-amber-950',
        success: 'bg-emerald-600 text-white',
        announcement: 'bg-slate-950 text-white',
    };

    return map[banner.value?.style] || map.announcement;
});

const badgeClass = computed(() => {
    const map = {
        info: 'bg-white/15 text-white ring-white/25',
        warning: 'bg-amber-950/10 text-amber-950 ring-amber-950/20',
        success: 'bg-white/15 text-white ring-white/25',
        announcement: 'bg-rose-500 text-white ring-rose-300/40',
    };

    return map[banner.value?.style] || map.announcement;
});

const isExternalLink = computed(() => {
    const link = banner.value?.link;

    return link && /^https?:\/\//i.test(link);
});

const tickerText = computed(() => String(banner.value?.message || '').trim());

onMounted(async () => {
    try {
        const { data } = await publicApi().get('/site-banner');
        banner.value = data.data;
    } catch {
        banner.value = null;
    }
});

function dismiss() {
    dismissed.value = true;
}
</script>

<template>
    <div
        v-if="banner && !dismissed && tickerText"
        class="site-breaking-banner sticky top-0 z-[60] w-full overflow-hidden"
        :class="styleClass"
        role="status"
        aria-live="polite"
    >
        <div class="relative flex items-stretch">
            <div
                class="relative z-10 flex shrink-0 items-center gap-2 px-3 py-2 pr-4 text-[11px] font-bold uppercase tracking-[0.14em] sm:px-4"
                :class="badgeClass"
            >
                <span class="inline-flex size-1.5 animate-pulse rounded-full bg-current" aria-hidden="true" />
                Breaking
            </div>

            <div class="site-breaking-track min-w-0 flex-1 overflow-hidden py-2 pr-10 sm:pr-12">
                <div class="site-breaking-marquee flex w-max items-center gap-10 whitespace-nowrap text-sm font-medium">
                    <span
                        v-for="copy in 2"
                        :key="copy"
                        class="inline-flex items-center gap-3"
                    >
                        <span>{{ tickerText }}</span>
                        <a
                            v-if="banner.link && isExternalLink"
                            :href="banner.link"
                            class="inline-flex shrink-0 font-semibold underline underline-offset-2"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            {{ banner.link_label || 'Read more' }}
                        </a>
                        <RouterLink
                            v-else-if="banner.link"
                            :to="banner.link"
                            class="inline-flex shrink-0 font-semibold underline underline-offset-2"
                        >
                            {{ banner.link_label || 'Read more' }}
                        </RouterLink>
                        <span class="opacity-40" aria-hidden="true">•</span>
                    </span>
                </div>
            </div>

            <button
                type="button"
                class="absolute right-1.5 top-1/2 z-10 -translate-y-1/2 rounded-md p-1.5 opacity-80 transition hover:bg-black/10 hover:opacity-100 sm:right-2"
                aria-label="Dismiss breaking news"
                @click="dismiss"
            >
                <XMarkIcon class="size-4" />
            </button>
        </div>
    </div>
</template>

<style scoped>
.site-breaking-marquee {
    animation: site-breaking-scroll 42s linear infinite;
}

.site-breaking-track:hover .site-breaking-marquee {
    animation-play-state: paused;
}

@keyframes site-breaking-scroll {
    from {
        transform: translateX(0);
    }

    to {
        transform: translateX(-50%);
    }
}

@media (prefers-reduced-motion: reduce) {
    .site-breaking-marquee {
        animation: none;
        max-width: 100%;
        flex-wrap: wrap;
        white-space: normal;
        gap: 0.75rem;
    }

    .site-breaking-track {
        overflow: visible;
        padding-right: 2.5rem;
    }
}
</style>
