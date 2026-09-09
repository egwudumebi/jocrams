<template>
    <div class="admin-toolbar">
        <div v-if="$slots.search" class="admin-toolbar-search">
            <slot name="search" />
        </div>
        <div v-if="$slots.actions" class="admin-toolbar-actions">
            <slot name="actions" />
        </div>
    </div>
</template>
