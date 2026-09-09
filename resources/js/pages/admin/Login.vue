<script setup>
import { ref } from 'vue';
import { useRouter, useRoute, RouterLink } from 'vue-router';
import { ShieldCheckIcon } from '@heroicons/vue/24/outline';
import AdminAuthPageShell from '../../components/layout/AdminAuthPageShell.vue';
import PasswordInput from '../../components/forms/PasswordInput.vue';
import { useAuth } from '../../composables/useAuth';
import { extractApiError } from '../../utils/apiError';

const router = useRouter();
const route = useRoute();
const { loginAdmin } = useAuth();
const form = ref({ email: '', password: '', remember: false });
const error = ref('');
const submitting = ref(false);

async function submit() {
    error.value = '';
    submitting.value = true;

    try {
        await loginAdmin(form.value.email, form.value.password);
        router.push(route.query.redirect || '/admin/dashboard');
    } catch (e) {
        error.value = extractApiError(e, 'Invalid credentials. Please try again.');
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <AdminAuthPageShell
        title="Admin Control Center"
        subtitle="Secure access for association staff to manage members, approvals, payments, content, and communications."
        :bullets="[
            'Review and approve membership applications',
            'Monitor payments, renewals, and member activity',
            'Publish news, manage events, and run campaigns',
        ]"
    >
        <div class="card-modern border-slate-200/80 p-6 shadow-lg shadow-slate-200/60 sm:p-8">
            <div class="flex items-center gap-3">
                <span class="flex size-11 items-center justify-center rounded-xl bg-institutional text-white shadow-md shadow-institutional/25">
                    <ShieldCheckIcon class="size-6" aria-hidden="true" />
                </span>
                <div>
                    <h2 class="font-display text-2xl font-bold text-institutional-dark">Admin Sign In</h2>
                    <p class="text-sm text-text-secondary">Operations & Control Center</p>
                </div>
            </div>

            <form class="mt-8 space-y-5" @submit.prevent="submit">
                <div v-if="error" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    {{ error }}
                </div>

                <div>
                    <label class="label" for="admin-email">Work email</label>
                    <input
                        id="admin-email"
                        v-model="form.email"
                        type="email"
                        required
                        autocomplete="username"
                        class="input !rounded-xl !border-slate-200/80 !py-3 focus:!border-institutional focus:!ring-institutional/20"
                        placeholder="admin@jocrams.test"
                    />
                </div>

                <div>
                    <div class="mb-1 flex items-center justify-between">
                        <label class="label !mb-0" for="admin-password">Password</label>
                        <RouterLink to="/admin/forgot-password" class="text-xs font-medium text-institutional hover:underline">
                            Forgot password?
                        </RouterLink>
                    </div>
                    <PasswordInput
                        id="admin-password"
                        v-model="form.password"
                        required
                        autocomplete="current-password"
                        input-class="input !rounded-xl !border-slate-200/80 !py-3 focus:!border-institutional focus:!ring-institutional/20"
                        placeholder="Enter your password"
                    />
                </div>

                <label class="flex items-center gap-2 text-sm text-text-secondary">
                    <input v-model="form.remember" type="checkbox" class="rounded border-slate-300 text-institutional focus:ring-institutional" />
                    Keep me signed in on this device
                </label>

                <button type="submit" class="btn-institutional w-full" :disabled="submitting">
                    {{ submitting ? 'Signing in...' : 'Sign In to Dashboard' }}
                </button>
            </form>

            <p class="mt-6 rounded-xl border border-amber-100 bg-amber-50/80 px-4 py-3 text-center text-xs leading-relaxed text-amber-900">
                This area is restricted to authorized Jocrams staff. Member accounts should use
                <RouterLink to="/member/login" class="font-semibold text-institutional hover:underline">
                    member login
                </RouterLink>.
            </p>
        </div>
    </AdminAuthPageShell>
</template>
