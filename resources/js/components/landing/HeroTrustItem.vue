<script setup>
defineProps({
    label: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <div class="inline-flex items-center gap-2.5 rounded-full border border-institutional/10 bg-white/80 px-4 py-2 text-sm font-medium text-institutional-dark shadow-sm backdrop-blur-sm">
        <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-institutional/8 text-institutional">
            <slot />
        </span>
        {{ label }}
    </div>
</template>
