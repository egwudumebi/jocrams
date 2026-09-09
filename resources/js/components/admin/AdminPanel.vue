<script setup>
defineProps({
    title: {
        type: String,
        default: '',
    },
    description: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <section class="admin-panel">
        <header v-if="title || $slots.header" class="admin-panel-header">
            <div class="min-w-0 flex-1">
                <slot name="header">
                    <h2 v-if="title" class="admin-panel-title">{{ title }}</h2>
                    <p v-if="description" class="admin-panel-description">{{ description }}</p>
                </slot>
            </div>
            <div v-if="$slots.actions" class="admin-panel-actions">
                <slot name="actions" />
            </div>
        </header>
        <div class="admin-panel-body">
            <slot />
        </div>
    </section>
</template>
