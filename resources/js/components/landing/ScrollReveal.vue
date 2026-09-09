<script setup>
import { useScrollReveal } from '../../composables/useScrollReveal';

const props = defineProps({
    animation: {
        type: String,
        default: 'fade-up',
        validator: (value) => ['fade-up', 'fade-in', 'slide-left', 'slide-right', 'scale-in'].includes(value),
    },
    delay: {
        type: Number,
        default: 0,
    },
});

const { element, isVisible } = useScrollReveal();
</script>

<template>
    <div
        ref="element"
        class="reveal h-full"
        :class="[`reveal-${animation}`, isVisible && 'reveal-visible']"
        :style="{ transitionDelay: `${delay}ms` }"
    >
        <slot />
    </div>
</template>
