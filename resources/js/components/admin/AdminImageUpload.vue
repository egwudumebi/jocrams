<script setup>
import { computed, onUnmounted, ref, watch } from 'vue';
import { ArrowUpTrayIcon, PhotoIcon, XMarkIcon } from '@heroicons/vue/24/outline';
import FormFieldLabel from './FormFieldLabel.vue';

const props = defineProps({
    existingUrl: {
        type: String,
        default: '',
    },
    accept: {
        type: String,
        default: 'image/jpeg,image/png,image/webp',
    },
    label: {
        type: String,
        default: 'Image',
    },
    help: {
        type: String,
        default: 'JPEG, PNG, or WebP. Recommended 1600×900 or wider.',
    },
    required: {
        type: Boolean,
        default: false,
    },
    aspectClass: {
        type: String,
        default: 'aspect-[21/9]',
    },
});

const emit = defineEmits(['update:file', 'clear']);

const inputRef = ref(null);
const dragActive = ref(false);
const localPreview = ref('');
const selectedFile = ref(null);
const errorMessage = ref('');

const previewUrl = computed(() => localPreview.value || props.existingUrl || '');
const hasPreview = computed(() => Boolean(previewUrl.value));
const fileLabel = computed(() => {
    if (selectedFile.value) {
        return selectedFile.value.name;
    }

    if (props.existingUrl) {
        return 'Current banner image';
    }

    return '';
});

watch(
    () => props.existingUrl,
    () => {
        if (!selectedFile.value) {
            revokePreview();
        }
    },
);

onUnmounted(revokePreview);

function revokePreview() {
    if (localPreview.value) {
        URL.revokeObjectURL(localPreview.value);
        localPreview.value = '';
    }
}

function validateFile(file) {
    const allowed = props.accept.split(',').map((type) => type.trim());
    if (!allowed.includes(file.type)) {
        errorMessage.value = 'Please choose a JPEG, PNG, or WebP image.';
        return false;
    }

    if (file.size > 5 * 1024 * 1024) {
        errorMessage.value = 'Image must be 5 MB or smaller.';
        return false;
    }

    errorMessage.value = '';
    return true;
}

function applyFile(file) {
    if (!file || !validateFile(file)) {
        return;
    }

    revokePreview();
    selectedFile.value = file;
    localPreview.value = URL.createObjectURL(file);
    emit('update:file', file);
}

function onInputChange(event) {
    applyFile(event.target.files?.[0] || null);
}

function onDrop(event) {
    dragActive.value = false;
    applyFile(event.dataTransfer?.files?.[0] || null);
}

function openPicker() {
    inputRef.value?.click();
}

function clearImage() {
    revokePreview();
    selectedFile.value = null;
    errorMessage.value = '';
    if (inputRef.value) {
        inputRef.value.value = '';
    }
    emit('update:file', null);
    emit('clear');
}

function onDragEnter() {
    dragActive.value = true;
}

function onDragLeave(event) {
    if (event.currentTarget === event.target) {
        dragActive.value = false;
    }
}
</script>

<template>
    <div class="form-field">
        <FormFieldLabel :label="label" :help="help" :required="required" />

        <div
            class="image-upload"
            :class="{ 'image-upload-active': dragActive, 'image-upload-filled': hasPreview }"
            @dragenter.prevent="onDragEnter"
            @dragover.prevent
            @dragleave="onDragLeave"
            @drop.prevent="onDrop"
        >
            <input
                ref="inputRef"
                type="file"
                class="sr-only"
                :accept="accept"
                @change="onInputChange"
            />

            <div v-if="hasPreview" class="image-upload-preview">
                <img :src="previewUrl" :alt="label" class="size-full object-cover" />
                <div class="image-upload-overlay">
                    <button type="button" class="btn-secondary !rounded-xl !bg-white/95" @click="openPicker">
                        Replace image
                    </button>
                    <button type="button" class="btn-secondary !rounded-xl !bg-white/95" @click="clearImage">
                        Remove
                    </button>
                </div>
            </div>

            <button
                v-else
                type="button"
                class="image-upload-empty"
                :class="aspectClass"
                @click="openPicker"
            >
                <span class="image-upload-icon">
                    <PhotoIcon class="size-7" />
                </span>
                <span class="font-medium text-slate-800">Click to upload or drag and drop</span>
                <span class="text-xs text-slate-500">Banner image for event listings</span>
                <span class="mt-1 inline-flex items-center gap-1 text-xs font-semibold text-institutional">
                    <ArrowUpTrayIcon class="size-4" />
                    Browse files
                </span>
            </button>
        </div>

        <div v-if="fileLabel" class="mt-2 flex items-center justify-between gap-3 rounded-xl bg-slate-50 px-3 py-2 text-sm text-slate-600">
            <span class="truncate">{{ fileLabel }}</span>
            <button type="button" class="shrink-0 rounded-lg p-1 text-slate-400 hover:bg-white hover:text-red-600" aria-label="Remove image" @click="clearImage">
                <XMarkIcon class="size-4" />
            </button>
        </div>

        <p v-if="errorMessage" class="form-error">{{ errorMessage }}</p>
    </div>
</template>
