<script setup>
import { PhotoIcon } from '@heroicons/vue/24/outline';

defineProps({
    url: {
        type: String,
        default: '',
    },
    title: {
        type: String,
        default: 'Event',
    },
    variant: {
        type: String,
        default: 'card',
        validator: (value) => ['card', 'thumb', 'hero'].includes(value),
    },
});
</script>

<template>
    <div
        class="event-banner-thumb"
        :class="{
            'event-banner-thumb-inline': variant === 'thumb',
            'event-banner-thumb-card': variant === 'card',
            'event-banner-thumb-hero': variant === 'hero',
        }"
    >
        <img
            v-if="url"
            :src="url"
            :alt="`${title} banner`"
            class="size-full object-cover"
        />
        <div v-else class="event-banner-thumb-placeholder">
            <PhotoIcon class="size-5 text-slate-400 sm:size-6" aria-hidden="true" />
            <span class="event-banner-thumb-placeholder-text">No banner</span>
        </div>
    </div>
</template>
