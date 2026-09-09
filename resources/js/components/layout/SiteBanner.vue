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
        announcement: 'bg-slate-900 text-white',
    };

    return map[banner.value?.style] || map.info;
});

const isExternalLink = computed(() => {
    const link = banner.value?.link;

    return link && /^https?:\/\//i.test(link);
});

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
        v-if="banner && !dismissed"
        class="relative z-[60] w-full px-4 py-2.5 text-center text-sm sm:px-6"
        :class="styleClass"
        role="status"
    >
        <div class="mx-auto flex max-w-7xl items-center justify-center gap-3">
            <p class="font-medium leading-snug">
                {{ banner.message }}
                <a
                    v-if="banner.link && isExternalLink"
                    :href="banner.link"
                    class="ml-2 inline-flex font-semibold underline underline-offset-2"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    {{ banner.link_label || 'Learn more' }}
                </a>
                <RouterLink
                    v-else-if="banner.link"
                    :to="banner.link"
                    class="ml-2 inline-flex font-semibold underline underline-offset-2"
                >
                    {{ banner.link_label || 'Learn more' }}
                </RouterLink>
            </p>
            <button
                type="button"
                class="absolute right-3 top-1/2 -translate-y-1/2 rounded-lg p-1 opacity-80 transition hover:bg-black/10 hover:opacity-100 sm:right-4"
                aria-label="Dismiss banner"
                @click="dismiss"
            >
                <XMarkIcon class="size-4" />
            </button>
        </div>
    </div>
</template>
