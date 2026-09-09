<script setup>
import { ref } from 'vue';
import { RouterLink } from 'vue-router';
import axios from 'axios';
import AdminAuthPageShell from '../../components/layout/AdminAuthPageShell.vue';
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
    <AdminAuthPageShell title="Admin password reset" subtitle="We will email you a secure reset link.">
        <form class="space-y-4" @submit.prevent="submit">
            <div v-if="message" class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">{{ message }}</div>
            <div v-if="error" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ error }}</div>

            <div>
                <label class="label">Admin email</label>
                <input v-model="form.email" type="email" required class="input" />
            </div>

            <button type="submit" class="btn-primary w-full" :disabled="submitting">
                {{ submitting ? 'Sending…' : 'Send reset link' }}
            </button>

            <p class="text-center text-sm text-slate-500">
                <RouterLink to="/admin/login" class="text-brand-600 hover:underline">Back to admin login</RouterLink>
            </p>
        </form>
    </AdminAuthPageShell>
</template>
