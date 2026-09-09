<script setup>
import { CheckIcon } from '@heroicons/vue/20/solid';

defineProps({
    label: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <div class="inline-flex items-center gap-2 text-sm font-medium text-text-primary">
        <span class="flex size-5 shrink-0 items-center justify-center rounded-full bg-success">
            <CheckIcon class="size-3 text-white" aria-hidden="true" />
        </span>
        {{ label }}
    </div>
</template>
