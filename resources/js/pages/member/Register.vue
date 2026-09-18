<script setup>
import { ref, onMounted } from 'vue';
import { useRouter, RouterLink } from 'vue-router';
import { UserPlusIcon } from '@heroicons/vue/24/outline';
import AuthPageShell from '../../components/layout/AuthPageShell.vue';
import PasswordInput from '../../components/forms/PasswordInput.vue';
import { useAuth } from '../../composables/useAuth';
import { publicApi } from '../../api/client';
import { extractApiError } from '../../utils/apiError';

const router = useRouter();
const { registerMember } = useAuth();
const tiers = ref([]);
const form = ref({ name: '', email: '', phone: '', password: '', password_confirmation: '' });
const error = ref('');
const submitting = ref(false);

onMounted(async () => {
    try {
        const { data } = await publicApi().get('/membership-tiers');
        tiers.value = data.data;
    } catch {
        tiers.value = [];
    }
});

async function submit() {
    error.value = '';
    submitting.value = true;

    try {
        await registerMember(form.value);
        router.push('/member/applications');
    } catch (e) {
        error.value = extractApiError(e, 'Registration failed. Please check your details.');
    } finally {
        submitting.value = false;
    }
}

function formatDues(amount) {
    return Number(amount).toLocaleString();
}
</script>

<template>
    <AuthPageShell
        title="Join our association"
        subtitle="Create your account to start a membership application. Choose your tier during the application process."
        :bullets="[
            'Create your account and complete your application',
            'Pay the registration fee by UBA bank transfer',
            'Appear in the public directory after admin approval',
        ]"
    >
        <template #aside>
            <div v-if="tiers.length" class="mt-10 space-y-3">
                <p class="label-caps text-accent-gold/90">Membership tiers</p>
                <div
                    v-for="tier in tiers"
                    :key="tier.uuid"
                    class="rounded-xl border border-white/15 bg-white/8 px-4 py-3 backdrop-blur-sm"
                >
                    <p class="font-semibold text-white">{{ tier.name }}</p>
                    <p class="mt-0.5 text-sm text-slate-300">₦{{ formatDues(tier.annual_dues) }} / year</p>
                </div>
            </div>
        </template>

        <div class="card-modern p-6 sm:p-8">
            <div class="flex items-center gap-3">
                <span class="flex size-11 items-center justify-center rounded-xl bg-accent-gold/20 text-institutional-dark">
                    <UserPlusIcon class="size-6" aria-hidden="true" />
                </span>
                <div>
                    <h2 class="font-display text-2xl font-bold text-institutional-dark">Create Account</h2>
                    <p class="text-sm text-text-secondary">Register to begin your membership application</p>
                </div>
            </div>

            <form class="mt-8 space-y-5" @submit.prevent="submit">
                <div v-if="error" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    {{ error }}
                </div>

                <div>
                    <label class="label" for="register-name">Full name *</label>
                    <input
                        id="register-name"
                        v-model="form.name"
                        required
                        autocomplete="name"
                        class="input !rounded-xl !border-slate-200/80 !py-3 focus:!border-institutional focus:!ring-institutional/20"
                        placeholder="Your full name"
                    />
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label class="label" for="register-email">Email *</label>
                        <input
                            id="register-email"
                            v-model="form.email"
                            type="email"
                            required
                            autocomplete="email"
                            class="input !rounded-xl !border-slate-200/80 !py-3 focus:!border-institutional focus:!ring-institutional/20"
                            placeholder="you@example.com"
                        />
                    </div>
                    <div>
                        <label class="label" for="register-phone">Phone</label>
                        <input
                            id="register-phone"
                            v-model="form.phone"
                            type="tel"
                            autocomplete="tel"
                            class="input !rounded-xl !border-slate-200/80 !py-3 focus:!border-institutional focus:!ring-institutional/20"
                            placeholder="+234 800 000 0000"
                        />
                    </div>
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label class="label" for="register-password">Password *</label>
                        <PasswordInput
                            id="register-password"
                            v-model="form.password"
                            required
                            minlength="8"
                            autocomplete="new-password"
                            input-class="input !rounded-xl !border-slate-200/80 !py-3 focus:!border-institutional focus:!ring-institutional/20"
                            placeholder="Min. 8 characters"
                        />
                    </div>
                    <div>
                        <label class="label" for="register-password-confirm">Confirm password *</label>
                        <PasswordInput
                            id="register-password-confirm"
                            v-model="form.password_confirmation"
                            required
                            autocomplete="new-password"
                            input-class="input !rounded-xl !border-slate-200/80 !py-3 focus:!border-institutional focus:!ring-institutional/20"
                            placeholder="Repeat password"
                        />
                    </div>
                </div>

                <p class="text-xs leading-relaxed text-text-secondary">
                    By creating an account, you agree to proceed with the membership application process. Tier selection happens on the next step.
                </p>

                <button type="submit" class="btn-gold w-full" :disabled="submitting">
                    {{ submitting ? 'Creating account...' : 'Create Account' }}
                </button>
            </form>

            <p class="mt-6 text-center text-sm text-text-secondary">
                Already have an account?
                <RouterLink to="/member/login" class="font-semibold text-institutional hover:underline">
                    Sign in
                </RouterLink>
            </p>
            <p class="mt-3 text-center text-sm text-text-secondary">
                Prefer email verification instead?
                <RouterLink to="/join" class="font-semibold text-institutional hover:underline">
                    Apply without a password
                </RouterLink>
            </p>
        </div>
    </AuthPageShell>
</template>
