<script setup>
import { computed } from 'vue';
import { passwordsMatch, unmetPasswordRules } from '../../utils/passwordValidation';

const props = defineProps({
    password: {
        type: String,
        default: '',
    },
    confirmation: {
        type: String,
        default: '',
    },
    showConfirmation: {
        type: Boolean,
        default: true,
    },
    showPasswordRules: {
        type: Boolean,
        default: true,
    },
});

const failingRules = computed(() => {
    if (!props.showPasswordRules) {
        return [];
    }

    return unmetPasswordRules(props.password);
});

const showMismatch = computed(() => {
    if (!props.showConfirmation || !props.confirmation) {
        return false;
    }

    return !passwordsMatch(props.password, props.confirmation);
});
</script>

<template>
    <div v-if="failingRules.length || showMismatch" class="password-requirements" aria-live="polite">
        <p v-if="failingRules.length" class="password-requirements-title">Password must include:</p>
        <ul v-if="failingRules.length" class="password-requirements-list">
            <li v-for="rule in failingRules" :key="rule.id">
                {{ rule.label }}
            </li>
        </ul>
        <p v-if="showMismatch" class="password-requirements-mismatch">
            Passwords do not match
        </p>
    </div>
</template>
