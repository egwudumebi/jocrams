<script setup>
defineProps({
    value: {
        type: String,
        required: true,
    },
    label: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <div class="flex flex-col items-center gap-3 text-center sm:flex-row sm:text-left">
        <span class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-institutional/10 text-institutional">
            <slot />
        </span>
        <div>
            <p class="font-display text-2xl font-bold text-institutional sm:text-3xl">{{ value }}</p>
            <p class="mt-0.5 text-sm text-text-secondary">{{ label }}</p>
        </div>
    </div>
</template>
