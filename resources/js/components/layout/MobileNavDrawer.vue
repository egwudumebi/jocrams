<script setup>
import { onUnmounted, watch } from 'vue';
import { useRoute } from 'vue-router';
import { XMarkIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    title: {
        type: String,
        default: 'Menu',
    },
    panelClass: {
        type: String,
        default: 'bg-white',
    },
    headerClass: {
        type: String,
        default: 'border-slate-200/80',
    },
    titleClass: {
        type: String,
        default: 'text-slate-900',
    },
    closeButtonClass: {
        type: String,
        default: 'text-slate-500 hover:bg-slate-100',
    },
});

const emit = defineEmits(['close']);

const route = useRoute();

watch(
    () => route.fullPath,
    () => {
        if (props.open) {
            emit('close');
        }
    },
);

watch(
    () => props.open,
    (isOpen) => {
        document.body.style.overflow = isOpen ? 'hidden' : '';
    },
);

onUnmounted(() => {
    document.body.style.overflow = '';
});
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition-opacity duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-200"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="open"
                class="fixed inset-0 z-50 lg:hidden"
                role="dialog"
                aria-modal="true"
                :aria-label="title"
            >
                <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" @click="emit('close')" />

                <Transition
                    enter-active-class="transition-transform duration-200"
                    enter-from-class="-translate-x-full"
                    enter-to-class="translate-x-0"
                    leave-active-class="transition-transform duration-200"
                    leave-from-class="translate-x-0"
                    leave-to-class="-translate-x-full"
                >
                    <aside
                        v-if="open"
                        class="absolute inset-y-0 left-0 flex w-[min(88vw,20rem)] max-w-xs flex-col shadow-xl"
                        :class="panelClass"
                    >
                        <div class="flex items-center justify-between border-b px-4 py-3" :class="headerClass">
                            <span class="font-display text-base font-semibold" :class="titleClass">{{ title }}</span>
                            <button
                                type="button"
                                class="rounded-lg p-2"
                                :class="closeButtonClass"
                                aria-label="Close menu"
                                @click="emit('close')"
                            >
                                <XMarkIcon class="size-5" />
                            </button>
                        </div>
                        <div class="flex min-h-0 flex-1 flex-col overflow-y-auto">
                            <slot />
                        </div>
                    </aside>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>
