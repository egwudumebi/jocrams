<script setup>
import { ref } from 'vue';
import { EyeIcon, EyeSlashIcon } from '@heroicons/vue/24/outline';

const model = defineModel({ type: String, default: '' });

defineProps({
    id: {
        type: String,
        default: undefined,
    },
    placeholder: {
        type: String,
        default: '',
    },
    autocomplete: {
        type: String,
        default: 'current-password',
    },
    required: {
        type: Boolean,
        default: false,
    },
    minlength: {
        type: [Number, String],
        default: undefined,
    },
    inputClass: {
        type: String,
        default: 'input',
    },
});

const visible = ref(false);
</script>

<template>
    <div class="relative">
        <input
            :id="id"
            v-model="model"
            :type="visible ? 'text' : 'password'"
            :placeholder="placeholder"
            :autocomplete="autocomplete"
            :required="required"
            :minlength="minlength"
            :class="[inputClass, 'pr-12']"
        />
        <button
            type="button"
            tabindex="-1"
            class="absolute inset-y-0 right-0 z-10 flex w-11 items-center justify-center text-slate-500 transition hover:text-institutional focus:outline-none focus-visible:text-institutional focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-institutional/30"
            :aria-label="visible ? 'Hide password' : 'Show password'"
            :aria-pressed="visible"
            @click="visible = !visible"
        >
            <EyeSlashIcon v-if="visible" class="size-5 shrink-0" aria-hidden="true" />
            <EyeIcon v-else class="size-5 shrink-0" aria-hidden="true" />
        </button>
    </div>
</template>
