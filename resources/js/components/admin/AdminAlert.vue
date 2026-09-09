<script setup>
defineProps({
    type: {
        type: String,
        default: 'success',
        validator: (value) => ['success', 'error', 'info'].includes(value),
    },
});
</script>

<template>
    <p
        class="mb-4 rounded-xl border px-4 py-3 text-sm"
        :class="{
            'border-emerald-200 bg-emerald-50 text-emerald-800': type === 'success',
            'border-red-200 bg-red-50 text-red-700': type === 'error',
            'border-brand-200 bg-brand-50 text-brand-900': type === 'info',
        }"
    >
        <slot />
    </p>
</template>
