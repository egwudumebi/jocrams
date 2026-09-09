<script setup>
import { ref } from 'vue';
import { useRouter, useRoute, RouterLink } from 'vue-router';
import { LockClosedIcon } from '@heroicons/vue/24/outline';
import AuthPageShell from '../../components/layout/AuthPageShell.vue';
import PasswordInput from '../../components/forms/PasswordInput.vue';
import { useAuth } from '../../composables/useAuth';
import { extractApiError } from '../../utils/apiError';

const router = useRouter();
const route = useRoute();
const { loginMember } = useAuth();
const form = ref({ email: '', password: '', remember: false });
const error = ref('');
const submitting = ref(false);

async function submit() {
    error.value = '';
    submitting.value = true;

    try {
        await loginMember(form.value.email, form.value.password);
        router.push(route.query.redirect || '/member/dashboard');
    } catch (e) {
        error.value = extractApiError(e, 'Invalid email or password. Please try again.');
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <AuthPageShell
        title="Welcome back"
        subtitle="Sign in to manage your membership, applications, payments, and digital credentials."
        :bullets="[
            'Track application and renewal status',
            'Download membership cards and certificates',
            'Access members-only resources and events',
        ]"
    >
        <div class="card-modern p-6 sm:p-8">
            <div class="flex items-center gap-3">
                <span class="flex size-11 items-center justify-center rounded-xl bg-institutional/10 text-institutional">
                    <LockClosedIcon class="size-6" aria-hidden="true" />
                </span>
                <div>
                    <h2 class="font-display text-2xl font-bold text-institutional-dark">Member Login</h2>
                    <p class="text-sm text-text-secondary">Access your membership portal</p>
                </div>
            </div>

            <form class="mt-8 space-y-5" @submit.prevent="submit">
                <div v-if="error" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    {{ error }}
                </div>

                <div>
                    <label class="label" for="login-email">Email address</label>
                    <input
                        id="login-email"
                        v-model="form.email"
                        type="email"
                        required
                        autocomplete="email"
                        class="input !rounded-xl !border-slate-200/80 !py-3 focus:!border-institutional focus:!ring-institutional/20"
                        placeholder="you@example.com"
                    />
                </div>

                <div>
                    <div class="mb-1 flex items-center justify-between">
                        <label class="label !mb-0" for="login-password">Password</label>
                        <RouterLink to="/member/forgot-password" class="text-xs font-medium text-institutional hover:underline">
                            Forgot password?
                        </RouterLink>
                    </div>
                    <PasswordInput
                        id="login-password"
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
                    {{ submitting ? 'Signing in...' : 'Sign In' }}
                </button>
            </form>

            <p class="mt-6 text-center text-sm text-text-secondary">
                No account yet?
                <RouterLink to="/member/register" class="font-semibold text-institutional hover:underline">
                    Create one free
                </RouterLink>
            </p>

            <p class="mt-3 text-center text-sm text-text-secondary">
                Staff administrator?
                <RouterLink to="/admin/login" class="font-semibold text-institutional hover:underline">
                    Admin sign in
                </RouterLink>
            </p>
        </div>
    </AuthPageShell>
</template>
