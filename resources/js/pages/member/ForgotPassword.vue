<script setup>
import { ref } from 'vue';
import { RouterLink } from 'vue-router';
import axios from 'axios';
import AuthPageShell from '../../components/layout/AuthPageShell.vue';
import { extractApiError } from '../../utils/apiError';

const form = ref({ email: '' });
const message = ref('');
const error = ref('');
const submitting = ref(false);

async function submit() {
    error.value = '';
    message.value = '';
    submitting.value = true;

    try {
        const { data } = await axios.post('/api/v1/auth/password/forgot', form.value);
        message.value = data.message;
    } catch (e) {
        error.value = extractApiError(e, 'Unable to send reset link.');
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <AuthPageShell
        title="Reset password"
        subtitle="Enter your email and we will send you a link to choose a new password."
        :bullets="['Secure password reset link', 'Link expires after 60 minutes', 'Contact support if you need help']"
    >
        <div class="card-modern p-6 sm:p-8">
            <h2 class="font-display text-2xl font-bold text-institutional-dark">Forgot password</h2>

            <form class="mt-6 space-y-4" @submit.prevent="submit">
                <div v-if="message" class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">{{ message }}</div>
                <div v-if="error" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ error }}</div>

                <div>
                    <label class="label" for="forgot-email">Email address</label>
                    <input id="forgot-email" v-model="form.email" type="email" required class="input" />
                </div>

                <button type="submit" class="btn-institutional w-full" :disabled="submitting">
                    {{ submitting ? 'Sending…' : 'Send reset link' }}
                </button>
            </form>

            <p class="mt-6 text-center text-sm text-text-secondary">
                <RouterLink to="/member/login" class="font-semibold text-institutional hover:underline">Back to sign in</RouterLink>
            </p>
        </div>
    </AuthPageShell>
</template>
