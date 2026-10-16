<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { ChevronLeftIcon, ChevronRightIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    slides: {
        type: Array,
        required: true,
    },
    intervalMs: {
        type: Number,
        default: 5500,
    },
});

const activeIndex = ref(0);
const isPaused = ref(false);
const prefersReducedMotion = ref(false);
const canHoverPause = ref(false);
let timer = null;
let touchStartX = null;
let touchStartY = null;
let reducedMotionQuery = null;
let onReducedMotionChange = null;

const activeSlide = computed(() => props.slides[activeIndex.value] ?? props.slides[0] ?? null);
const activeTheme = computed(() => activeSlide.value?.theme || 'light');
const hasMultipleSlides = computed(() => props.slides.length > 1);

function goTo(index) {
    if (!props.slides.length) {
        return;
    }

    activeIndex.value = (index + props.slides.length) % props.slides.length;
    startTimer();
}

function next() {
    goTo(activeIndex.value + 1);
}

function previous() {
    goTo(activeIndex.value - 1);
}

function stopTimer() {
    if (timer) {
        clearTimeout(timer);
        timer = null;
    }
}

function startTimer() {
    stopTimer();

    // Keep auto-advance even when reduced-motion is on (only decorative ken-burns is skipped).
    if (!hasMultipleSlides.value || isPaused.value) {
        return;
    }

    timer = window.setTimeout(() => {
        if (!props.slides.length || isPaused.value) {
            return;
        }

        activeIndex.value = (activeIndex.value + 1) % props.slides.length;
        startTimer();
    }, props.intervalMs);
}

function pauseForHover() {
    if (!canHoverPause.value) {
        return;
    }

    isPaused.value = true;
}

function resumeFromHover() {
    if (!canHoverPause.value) {
        return;
    }

    isPaused.value = false;
}

function onVisibilityChange() {
    if (document.hidden) {
        stopTimer();
    } else {
        startTimer();
    }
}

function onTouchStart(event) {
    const touch = event.changedTouches?.[0];
    if (!touch) {
        return;
    }

    touchStartX = touch.clientX;
    touchStartY = touch.clientY;
}

function onTouchEnd(event) {
    if (touchStartX === null || touchStartY === null || !hasMultipleSlides.value) {
        touchStartX = null;
        touchStartY = null;
        return;
    }

    const touch = event.changedTouches?.[0];
    if (!touch) {
        return;
    }

    const deltaX = touch.clientX - touchStartX;
    const deltaY = touch.clientY - touchStartY;
    touchStartX = null;
    touchStartY = null;

    // Horizontal swipe only; ignore mostly-vertical scrolls.
    if (Math.abs(deltaX) < 48 || Math.abs(deltaX) < Math.abs(deltaY) * 1.2) {
        return;
    }

    if (deltaX < 0) {
        next();
    } else {
        previous();
    }
}

watch(
    () => props.slides.length,
    (length) => {
        if (activeIndex.value >= length) {
            activeIndex.value = 0;
        }

        startTimer();
    },
);

watch(isPaused, (paused) => {
    if (paused) {
        stopTimer();
    } else {
        startTimer();
    }
});

onMounted(() => {
    if (typeof window !== 'undefined' && window.matchMedia) {
        reducedMotionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
        prefersReducedMotion.value = reducedMotionQuery.matches;
        canHoverPause.value = window.matchMedia('(hover: hover) and (pointer: fine)').matches;

        onReducedMotionChange = (event) => {
            prefersReducedMotion.value = event.matches;
        };

        if (typeof reducedMotionQuery.addEventListener === 'function') {
            reducedMotionQuery.addEventListener('change', onReducedMotionChange);
        } else if (typeof reducedMotionQuery.addListener === 'function') {
            // Older WebKit / Android WebView
            reducedMotionQuery.addListener(onReducedMotionChange);
        }
    }

    document.addEventListener('visibilitychange', onVisibilityChange);
    startTimer();
});

onUnmounted(() => {
    stopTimer();
    document.removeEventListener('visibilitychange', onVisibilityChange);

    if (reducedMotionQuery && onReducedMotionChange) {
        if (typeof reducedMotionQuery.removeEventListener === 'function') {
            reducedMotionQuery.removeEventListener('change', onReducedMotionChange);
        } else if (typeof reducedMotionQuery.removeListener === 'function') {
            reducedMotionQuery.removeListener(onReducedMotionChange);
        }
    }
});

function slideLayerStyle(slide) {
    const position = slide.imagePosition || 'center';

    return {
        backgroundImage: slide.imageUrl ? `url(${slide.imageUrl})` : undefined,
        backgroundPosition: position,
    };
}
</script>

<template>
    <div
        class="absolute inset-0 touch-pan-y"
        @touchstart.passive="onTouchStart"
        @touchend.passive="onTouchEnd"
    >
        <!-- Background layers -->
        <div
            v-for="(slide, index) in slides"
            :key="slide.id"
            class="absolute inset-0 transition-opacity duration-700"
            :class="index === activeIndex ? 'opacity-100' : 'pointer-events-none opacity-0'"
            aria-hidden="true"
        >
            <div
                v-if="slide.imageUrl"
                class="hero-ken-burns absolute inset-0 bg-cover bg-no-repeat"
                :class="{ 'hero-ken-burns-active': index === activeIndex && !prefersReducedMotion }"
                :style="slideLayerStyle(slide)"
            />
            <div
                v-else
                class="absolute inset-0 bg-gradient-to-br from-institutional-dark via-institutional to-[#0c4a8c]"
            />

            <div
                v-if="slide.theme === 'dark'"
                class="absolute inset-0 opacity-40"
                style="background-image: radial-gradient(circle at 20% 20%, rgba(201,162,39,0.25), transparent 35%), radial-gradient(circle at 80% 70%, rgba(255,255,255,0.08), transparent 40%);"
            />
            <div
                v-if="slide.theme === 'dark'"
                class="absolute inset-0 opacity-[0.07]"
                style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 28px 28px;"
            />
        </div>

        <!-- Theme overlays -->
        <div
            class="absolute inset-0 transition-colors duration-700"
            :class="activeTheme === 'dark'
                ? 'bg-gradient-to-r from-institutional-dark/95 via-institutional/88 to-institutional/55'
                : 'bg-gradient-to-r from-white/96 via-white/82 to-white/15'"
            aria-hidden="true"
        />
        <div
            class="absolute inset-0"
            :class="activeTheme === 'dark'
                ? 'bg-gradient-to-t from-institutional-dark/90 via-transparent to-institutional-dark/20'
                : 'bg-gradient-to-t from-white via-white/20 to-transparent'"
            aria-hidden="true"
        />

        <!-- Controls -->
        <div
            v-if="hasMultipleSlides"
            class="absolute right-3 top-4 z-30 flex items-center gap-2 sm:right-6 sm:top-8 lg:right-8"
        >
            <span
                class="rounded-full px-3 py-1 font-mono text-[11px] font-semibold tracking-wider backdrop-blur-sm"
                :class="activeTheme === 'dark' ? 'bg-white/10 text-white/80' : 'bg-institutional/8 text-institutional'"
            >
                {{ String(activeIndex + 1).padStart(2, '0') }} / {{ String(slides.length).padStart(2, '0') }}
            </span>
        </div>

        <div
            v-if="hasMultipleSlides"
            class="absolute bottom-24 left-1/2 z-30 flex -translate-x-1/2 items-center gap-2 sm:bottom-36"
            role="tablist"
            aria-label="Hero slides"
        >
            <button
                v-for="(slide, index) in slides"
                :key="`${slide.id}-dot`"
                type="button"
                class="group flex min-h-8 min-w-8 items-center justify-center gap-2"
                :aria-label="`Go to slide ${index + 1}: ${slide.eyebrow}`"
                :aria-selected="index === activeIndex"
                role="tab"
                @click="goTo(index)"
            >
                <span
                    class="block h-1.5 rounded-full transition-all duration-300"
                    :class="index === activeIndex
                        ? (activeTheme === 'dark' ? 'w-10 bg-accent-gold' : 'w-10 bg-institutional')
                        : (activeTheme === 'dark' ? 'w-4 bg-white/30 group-hover:bg-white/50' : 'w-4 bg-institutional/25 group-hover:bg-institutional/45')"
                />
            </button>
        </div>

        <button
            v-if="hasMultipleSlides"
            type="button"
            class="absolute left-2 top-1/2 z-30 -translate-y-1/2 rounded-full p-2.5 shadow-lg backdrop-blur-md transition sm:left-5 lg:left-6"
            :class="activeTheme === 'dark'
                ? 'bg-white/10 text-white hover:bg-white/20'
                : 'bg-white/90 text-institutional hover:bg-white'"
            aria-label="Previous slide"
            @mouseenter="pauseForHover"
            @mouseleave="resumeFromHover"
            @focus="pauseForHover"
            @blur="resumeFromHover"
            @click="previous"
        >
            <ChevronLeftIcon class="size-5" />
        </button>

        <button
            v-if="hasMultipleSlides"
            type="button"
            class="absolute right-2 top-1/2 z-30 -translate-y-1/2 rounded-full p-2.5 shadow-lg backdrop-blur-md transition sm:right-5 lg:right-6"
            :class="activeTheme === 'dark'
                ? 'bg-white/10 text-white hover:bg-white/20'
                : 'bg-white/90 text-institutional hover:bg-white'"
            aria-label="Next slide"
            @mouseenter="pauseForHover"
            @mouseleave="resumeFromHover"
            @focus="pauseForHover"
            @blur="resumeFromHover"
            @click="next"
        >
            <ChevronRightIcon class="size-5" />
        </button>

        <div v-if="activeSlide" class="sr-only" aria-live="polite">
            {{ activeSlide.title }} {{ activeSlide.highlight }} {{ activeSlide.suffix }}
        </div>

        <Transition name="hero-content" mode="out-in">
            <div :key="activeSlide?.id" class="relative z-10">
                <slot
                    :slide="activeSlide"
                    :theme="activeTheme"
                    :active-index="activeIndex"
                    :total="slides.length"
                />
            </div>
        </Transition>
    </div>
</template>

<style scoped>
.hero-ken-burns {
    transform: scale(1);
}

.hero-ken-burns-active {
    animation: hero-ken-burns 18s ease-out forwards;
}

.hero-content-enter-active,
.hero-content-leave-active {
    transition:
        opacity 0.45s ease,
        transform 0.45s ease;
}

.hero-content-enter-from {
    opacity: 0;
    transform: translateY(16px);
}

.hero-content-leave-to {
    opacity: 0;
    transform: translateY(-10px);
}

@keyframes hero-ken-burns {
    from {
        transform: scale(1);
    }
    to {
        transform: scale(1.08);
    }
}

@media (prefers-reduced-motion: reduce) {
    .hero-ken-burns-active {
        animation: none;
    }

    .hero-content-enter-active,
    .hero-content-leave-active {
        transition: opacity 0.2s ease;
    }

    .hero-content-enter-from,
    .hero-content-leave-to {
        transform: none;
    }
}
</style>
