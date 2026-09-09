<script setup>
import { computed, ref } from 'vue';
import { useCountUp } from '../../composables/useCountUp';

const props = defineProps({
    target: {
        type: Number,
        required: true,
    },
    suffix: {
        type: String,
        default: '',
    },
    label: {
        type: String,
        required: true,
    },
});

const element = ref(null);
const { displayValue, format } = useCountUp(props.target, { element, duration: 1800 });

const formattedValue = computed(() => `${format(displayValue.value)}${props.suffix}`);
</script>

<template>
    <div ref="element" class="metric-card group">
        <span class="metric-icon">
            <slot />
        </span>
        <div>
            <p class="font-display text-2xl font-bold text-institutional sm:text-3xl">{{ formattedValue }}</p>
            <p class="label-caps mt-1">{{ label }}</p>
        </div>
    </div>
</template>
