<script setup>
defineProps({
    title: String,
    description: String,
});
</script>

<template>
    <div class="card p-6">
        <h3 class="font-semibold text-slate-900">{{ title }}</h3>
        <p class="mt-2 text-sm text-slate-600">{{ description }}</p>
    </div>
</template>
