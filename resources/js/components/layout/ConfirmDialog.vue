<script setup>
import { onUnmounted, watch } from 'vue';

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    title: {
        type: String,
        default: 'Confirm action',
    },
    message: {
        type: String,
        default: 'Are you sure you want to continue?',
    },
    confirmLabel: {
        type: String,
        default: 'Confirm',
    },
    cancelLabel: {
        type: String,
        default: 'Cancel',
    },
    confirmVariant: {
        type: String,
        default: 'danger',
        validator: (value) => ['danger', 'primary'].includes(value),
    },
});

const emit = defineEmits(['confirm', 'cancel']);

watch(
    () => props.open,
    (isOpen) => {
        document.body.style.overflow = isOpen ? 'hidden' : '';
    },
);

onUnmounted(() => {
    document.body.style.overflow = '';
});

function handleCancel() {
    emit('cancel');
}

function handleConfirm() {
    emit('confirm');
}
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition-opacity duration-150"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="open"
                class="fixed inset-0 z-[60] flex items-end justify-center bg-slate-900/40 p-4 sm:items-center"
                role="dialog"
                aria-modal="true"
                :aria-label="title"
                @click.self="handleCancel"
            >
                <Transition
                    enter-active-class="transition duration-200 ease-out"
                    enter-from-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    enter-to-class="opacity-100 translate-y-0 sm:scale-100"
                    leave-active-class="transition duration-150 ease-in"
                    leave-from-class="opacity-100 translate-y-0 sm:scale-100"
                    leave-to-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                >
                    <div v-if="open" class="w-full max-w-sm rounded-2xl border border-slate-200 bg-white p-5 shadow-2xl">
                        <h2 class="font-display text-lg font-bold text-slate-900">{{ title }}</h2>
                        <p class="mt-2 text-sm text-slate-600">{{ message }}</p>
                        <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                            <button type="button" class="btn-secondary w-full sm:w-auto" @click="handleCancel">
                                {{ cancelLabel }}
                            </button>
                            <button
                                type="button"
                                class="w-full sm:w-auto"
                                :class="confirmVariant === 'danger' ? 'btn-danger' : 'btn-primary'"
                                @click="handleConfirm"
                            >
                                {{ confirmLabel }}
                            </button>
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>
