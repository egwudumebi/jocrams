<script setup>
import FieldHelp from './FieldHelp.vue';

defineProps({
    label: {
        type: String,
        required: true,
    },
    help: {
        type: String,
        default: '',
    },
    required: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <div class="form-field-label">
        <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
            <span class="label !mb-0">{{ label }}</span>
            <FieldHelp v-if="help" :text="help" />
            <span v-if="required" class="field-badge field-badge-required">Required</span>
            <span v-else class="field-badge field-badge-optional">Optional</span>
        </div>
    </div>
</template>
