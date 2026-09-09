<script setup>
import { RouterLink } from 'vue-router';
import { ArrowRightIcon } from '@heroicons/vue/20/solid';

defineProps({
    eyebrow: {
        type: String,
        default: '',
    },
    title: {
        type: String,
        required: true,
    },
    description: {
        type: String,
        default: '',
    },
    linkTo: {
        type: String,
        default: '',
    },
    linkLabel: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p v-if="eyebrow" class="label-caps text-institutional">{{ eyebrow }}</p>
            <h2 class="font-display text-3xl font-bold tracking-tight text-institutional-dark sm:text-4xl">
                {{ title }}
            </h2>
            <p v-if="description" class="mt-2 max-w-xl text-base text-text-secondary">{{ description }}</p>
        </div>
        <RouterLink
            v-if="linkTo && linkLabel"
            :to="linkTo"
            class="link-arrow group shrink-0"
        >
            {{ linkLabel }}
            <ArrowRightIcon class="size-4 transition group-hover:translate-x-0.5" aria-hidden="true" />
        </RouterLink>
    </div>
</template>
