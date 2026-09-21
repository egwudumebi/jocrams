<script setup>
defineProps({
    text: {
        type: String,
        default: 'Journal of Communication Research and Media Studies',
    },
    as: {
        type: String,
        default: 'span',
    },
});
</script>

<template>
    <component :is="as" class="italic">{{ text }}</component>
</template>
