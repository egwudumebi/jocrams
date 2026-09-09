<script setup>
import { nextTick, onMounted, onUnmounted, ref } from 'vue';
import { QuestionMarkCircleIcon } from '@heroicons/vue/24/outline';

defineProps({
    text: {
        type: String,
        required: true,
    },
});

const triggerRef = ref(null);
const open = ref(false);
const position = ref({ top: 0, left: 0 });

const tooltipWidth = 240;

function updatePosition() {
    const trigger = triggerRef.value;
    if (!trigger) {
        return;
    }

    const rect = trigger.getBoundingClientRect();
    const padding = 12;
    let left = rect.left + rect.width / 2 - tooltipWidth / 2;

    if (left < padding) {
        left = padding;
    }

    if (left + tooltipWidth > window.innerWidth - padding) {
        left = window.innerWidth - tooltipWidth - padding;
    }

    position.value = {
        top: rect.bottom + 8,
        left,
    };
}

async function showTooltip() {
    open.value = true;
    await nextTick();
    updatePosition();
}

function hideTooltip() {
    open.value = false;
}

function onViewportChange() {
    if (open.value) {
        updatePosition();
    }
}

onMounted(() => {
    window.addEventListener('scroll', onViewportChange, true);
    window.addEventListener('resize', onViewportChange);
});

onUnmounted(() => {
    window.removeEventListener('scroll', onViewportChange, true);
    window.removeEventListener('resize', onViewportChange);
});
</script>

<template>
    <span
        ref="triggerRef"
        class="field-help"
        tabindex="0"
        :aria-label="text"
        @mouseenter="showTooltip"
        @mouseleave="hideTooltip"
        @focus="showTooltip"
        @blur="hideTooltip"
    >
        <QuestionMarkCircleIcon class="size-4" aria-hidden="true" />
    </span>

    <Teleport to="body">
        <span
            v-if="open"
            class="field-help-tooltip field-help-tooltip-floating"
            role="tooltip"
            :style="{ top: `${position.top}px`, left: `${position.left}px` }"
        >
            {{ text }}
        </span>
    </Teleport>
</template>
