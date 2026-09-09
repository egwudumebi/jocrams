<script setup>
import { computed, ref, onMounted, watch } from 'vue';
import { useRoute, useRouter, RouterLink } from 'vue-router';
import axios from 'axios';
import AdminAuthPageShell from '../../components/layout/AdminAuthPageShell.vue';
import PasswordInput from '../../components/forms/PasswordInput.vue';
import PasswordRequirements from '../../components/forms/PasswordRequirements.vue';
import { passwordMeetsRequirements, passwordsMatch } from '../../utils/passwordValidation';
import { extractApiError } from '../../utils/apiError';

const route = useRoute();
const router = useRouter();
const form = ref({ email: '', token: '', password: '', password_confirmation: '' });
const message = ref('');
const error = ref('');
const submitting = ref(false);

const canSubmit = computed(() => {
    if (!form.value.token) {
        return false;
    }

    return passwordMeetsRequirements(form.value.password)
        && passwordsMatch(form.value.password, form.value.password_confirmation);
});

onMounted(() => {
    form.value.email = typeof route.query.email === 'string' ? route.query.email : '';
    form.value.token = typeof route.query.token === 'string' ? route.query.token : '';
});

watch(
    () => [form.value.password, form.value.password_confirmation],
    () => {
        if (error.value) {
            error.value = '';
        }
    },
);

function extractErrorMessage(err) {
    return extractApiError(err, 'Unable to reset password.');
}

async function submit() {
    if (!canSubmit.value) {
        return;
    }

    error.value = '';
    submitting.value = true;

    try {
        const { data } = await axios.post('/api/v1/auth/password/reset', form.value);
        message.value = data.message;
        setTimeout(() => router.push('/admin/login'), 1500);
    } catch (e) {
        error.value = extractErrorMessage(e);
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <AdminAuthPageShell title="Set new admin password" subtitle="Choose a strong password for your admin account.">
        <form class="space-y-4" @submit.prevent="submit">
            <div v-if="message" class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">{{ message }}</div>
            <div v-if="error" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ error }}</div>

            <div>
                <label class="label">Email</label>
                <input v-model="form.email" type="email" required class="input" readonly />
            </div>
            <div>
                <label class="label">New password</label>
                <PasswordInput
                    v-model="form.password"
                    required
                    autocomplete="new-password"
                    input-class="input"
                />
                <PasswordRequirements
                    :password="form.password"
                    confirmation=""
                    :show-confirmation="false"
                    class="mt-2"
                />
            </div>
            <div>
                <label class="label">Confirm password</label>
                <PasswordInput
                    v-model="form.password_confirmation"
                    required
                    autocomplete="new-password"
                    input-class="input"
                />
            </div>

            <PasswordRequirements
                :password="form.password"
                :confirmation="form.password_confirmation"
                :show-password-rules="false"
            />

            <button type="submit" class="btn-primary w-full" :disabled="submitting || !canSubmit">
                {{ submitting ? 'Saving…' : 'Reset password' }}
            </button>

            <p class="text-center text-sm text-slate-500">
                <RouterLink to="/admin/login" class="text-brand-600 hover:underline">Back to admin login</RouterLink>
            </p>
        </form>
    </AdminAuthPageShell>
</template>
