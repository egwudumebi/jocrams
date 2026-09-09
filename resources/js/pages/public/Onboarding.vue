<script setup>
import { computed, onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { CheckCircleIcon, UserPlusIcon } from '@heroicons/vue/24/outline';
import AuthPageShell from '../../components/layout/AuthPageShell.vue';
import { publicApi } from '../../api/client';
import { extractApiError } from '../../utils/apiError';

const steps = ['Personal info', 'Verify email', 'Professional details', 'Submit'];
const step = ref(0);
const sessionId = ref('');
const tiers = ref([]);
const error = ref('');
const submitting = ref(false);
const success = ref(null);

const personal = ref({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
});

const otp = ref('');

const professional = ref({
    membership_tier_id: '',
    institution: '',
    qualification: '',
    years_experience: 0,
});

const documents = ref([]);

const selectedTier = computed(() => tiers.value.find((tier) => String(tier.id) === String(professional.value.membership_tier_id)));

onMounted(async () => {
    try {
        const { data } = await publicApi().get('/membership-tiers');
        tiers.value = data.data || [];
        if (tiers.value.length) {
            professional.value.membership_tier_id = tiers.value[0].id;
        }
    } catch {
        tiers.value = [];
    }
});

function formatDues(amount) {
    return Number(amount).toLocaleString();
}

async function startOnboarding() {
    error.value = '';
    submitting.value = true;

    try {
        const { data } = await publicApi().post('/onboarding/start', personal.value);
        sessionId.value = data.session_id;
        if (data.otp_debug_code) {
            otp.value = data.otp_debug_code;
        }
        step.value = 1;
    } catch (e) {
        error.value = extractError(e);
    } finally {
        submitting.value = false;
    }
}

async function verifyEmail() {
    error.value = '';
    submitting.value = true;

    try {
        await publicApi().post('/onboarding/verify-email', {
            session_id: sessionId.value,
            otp: otp.value,
        });
        step.value = 2;
    } catch (e) {
        error.value = extractError(e);
    } finally {
        submitting.value = false;
    }
}

async function saveProfessionalDetails() {
    error.value = '';
    submitting.value = true;

    try {
        const formData = new FormData();
        formData.append('session_id', sessionId.value);
        formData.append('membership_tier_id', professional.value.membership_tier_id);
        formData.append('institution', professional.value.institution);
        formData.append('qualification', professional.value.qualification);
        formData.append('years_experience', String(professional.value.years_experience));
        documents.value.forEach((file) => formData.append('supporting_documents[]', file));

        await publicApi().post('/onboarding/professional-details', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        step.value = 3;
    } catch (e) {
        error.value = extractError(e);
    } finally {
        submitting.value = false;
    }
}

async function submitApplication() {
    error.value = '';
    submitting.value = true;

    try {
        const { data } = await publicApi().post('/onboarding/submit', {
            session_id: sessionId.value,
        });
        success.value = data;
        step.value = 4;
    } catch (e) {
        error.value = extractError(e);
    } finally {
        submitting.value = false;
    }
}

function onDocumentsChange(event) {
    documents.value = Array.from(event.target.files || []);
}

function extractError(e) {
    return extractApiError(e, 'Something went wrong. Please try again.');
}
</script>

<template>
    <AuthPageShell
        title="Apply for membership"
        subtitle="Complete our guided application with email verification. No password required until your account is created."
        :bullets="[
            'Verify your email with a one-time code',
            'Upload supporting documents',
            'Track your application after approval',
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
                    <h2 class="font-display text-2xl font-bold text-institutional-dark">Membership Application</h2>
                    <p class="text-sm text-text-secondary">Step {{ Math.min(step + 1, steps.length) }} of {{ steps.length }}: {{ steps[Math.min(step, steps.length - 1)] }}</p>
                </div>
            </div>

            <div class="mt-6 flex gap-2">
                <div
                    v-for="(label, index) in steps"
                    :key="label"
                    class="h-1.5 flex-1 rounded-full"
                    :class="index <= step ? 'bg-institutional' : 'bg-slate-200'"
                />
            </div>

            <p v-if="error" class="mt-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ error }}</p>

            <form v-if="step === 0" class="mt-8 space-y-5" @submit.prevent="startOnboarding">
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label class="label-caps">First name</label>
                        <input v-model="personal.first_name" type="text" required class="input-modern mt-2" />
                    </div>
                    <div>
                        <label class="label-caps">Last name</label>
                        <input v-model="personal.last_name" type="text" required class="input-modern mt-2" />
                    </div>
                </div>
                <div>
                    <label class="label-caps">Email</label>
                    <input v-model="personal.email" type="email" required class="input-modern mt-2" />
                </div>
                <div>
                    <label class="label-caps">Phone</label>
                    <input v-model="personal.phone" type="tel" required class="input-modern mt-2" />
                </div>
                <button type="submit" class="btn-primary w-full" :disabled="submitting">
                    {{ submitting ? 'Sending code...' : 'Continue' }}
                </button>
                <p class="text-center text-sm text-text-secondary">
                    Already have an account?
                    <RouterLink to="/member/register" class="font-medium text-institutional hover:underline">Register with password</RouterLink>
                </p>
            </form>

            <form v-else-if="step === 1" class="mt-8 space-y-5" @submit.prevent="verifyEmail">
                <p class="text-sm text-text-secondary">Enter the 6-digit code sent to <strong>{{ personal.email }}</strong>.</p>
                <div>
                    <label class="label-caps">Verification code</label>
                    <input v-model="otp" type="text" inputmode="numeric" maxlength="6" required class="input-modern mt-2 tracking-[0.4em]" />
                </div>
                <button type="submit" class="btn-primary w-full" :disabled="submitting">
                    {{ submitting ? 'Verifying...' : 'Verify email' }}
                </button>
            </form>

            <form v-else-if="step === 2" class="mt-8 space-y-5" @submit.prevent="saveProfessionalDetails">
                <div>
                    <label class="label-caps">Membership tier</label>
                    <select v-model="professional.membership_tier_id" required class="input-modern mt-2">
                        <option v-for="tier in tiers" :key="tier.uuid" :value="tier.id">{{ tier.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="label-caps">Institution / organization</label>
                    <input v-model="professional.institution" type="text" required class="input-modern mt-2" />
                </div>
                <div>
                    <label class="label-caps">Qualification</label>
                    <input v-model="professional.qualification" type="text" required class="input-modern mt-2" />
                </div>
                <div>
                    <label class="label-caps">Years of experience</label>
                    <input v-model.number="professional.years_experience" type="number" min="0" max="80" required class="input-modern mt-2" />
                </div>
                <div>
                    <label class="label-caps">Supporting documents</label>
                    <input type="file" multiple accept=".pdf,.doc,.docx,.png,.jpg,.jpeg" class="mt-2 block w-full text-sm" @change="onDocumentsChange" />
                    <p v-if="selectedTier" class="mt-1 text-xs text-text-secondary">At least {{ selectedTier.min_documents_required }} document(s) required before submission.</p>
                </div>
                <button type="submit" class="btn-primary w-full" :disabled="submitting">
                    {{ submitting ? 'Saving...' : 'Continue' }}
                </button>
            </form>

            <div v-else-if="step === 3" class="mt-8 space-y-5">
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5 text-sm text-slate-700">
                    <p><strong>Name:</strong> {{ personal.first_name }} {{ personal.last_name }}</p>
                    <p class="mt-2"><strong>Email:</strong> {{ personal.email }}</p>
                    <p class="mt-2"><strong>Tier:</strong> {{ selectedTier?.name }}</p>
                    <p class="mt-2"><strong>Institution:</strong> {{ professional.institution }}</p>
                    <p class="mt-2"><strong>Documents:</strong> {{ documents.length }} uploaded</p>
                </div>
                <button type="button" class="btn-primary w-full" :disabled="submitting" @click="submitApplication">
                    {{ submitting ? 'Submitting...' : 'Submit application' }}
                </button>
            </div>

            <div v-else class="mt-8 text-center">
                <CheckCircleIcon class="mx-auto size-12 text-green-600" />
                <h3 class="mt-4 font-display text-xl font-bold text-institutional-dark">Application submitted</h3>
                <p class="mt-2 text-sm text-text-secondary">
                    Your application is under review. Check your email to set your password, then sign in to track progress.
                </p>
                <RouterLink to="/member/login" class="btn-primary mt-6 inline-flex">Go to sign in</RouterLink>
            </div>
        </div>
    </AuthPageShell>
</template>
