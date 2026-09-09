<script setup>
defineProps({
    title: {
        type: String,
        required: true,
    },
    description: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <div class="empty-state bg-white">
        <div v-if="$slots.icon" class="mx-auto flex size-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
            <slot name="icon" />
        </div>
        <p class="mt-3 font-medium text-slate-800">{{ title }}</p>
        <p v-if="description" class="mt-1 text-sm text-text-secondary">{{ description }}</p>
        <div v-if="$slots.action" class="mt-4">
            <slot name="action" />
        </div>
    </div>
</template>
