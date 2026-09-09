<script setup>
defineProps({
    description: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <div v-if="description || $slots.default || $slots.actions" class="admin-page-intro">
        <div class="min-w-0 flex-1">
            <p v-if="description" class="max-w-3xl text-sm leading-relaxed text-text-secondary">
                {{ description }}
            </p>
            <slot />
        </div>
        <div v-if="$slots.actions" class="admin-page-intro-actions">
            <slot name="actions" />
        </div>
    </div>
</template>
