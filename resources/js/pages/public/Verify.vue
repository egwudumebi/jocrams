<script setup>
import { ref, onMounted, watch } from 'vue';
import { useRoute, RouterLink } from 'vue-router';
import {
    CheckCircleIcon,
    QrCodeIcon,
    ShieldCheckIcon,
    XCircleIcon,
} from '@heroicons/vue/24/outline';
import { publicApi } from '../../api/client';

const route = useRoute();
const token = ref(route.params.token || '');
const result = ref(null);
const loading = ref(false);

const steps = [
    'Scan the QR code on a membership card or certificate',
    'Or paste the verification token from the credential',
    'We confirm the credential is valid and not expired',
];

async function verify() {
    if (! token.value.trim()) {
        return;
    }

    loading.value = true;
    result.value = null;

    try {
        const { data } = await publicApi().get(`/verify/${encodeURIComponent(token.value.trim())}`);
        result.value = data;
    } catch {
        result.value = { valid: false, result: 'not_found' };
    } finally {
        loading.value = false;
    }
}

function formatDate(dateString) {
    if (! dateString) {
        return '—';
    }

    return new Date(dateString).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

function resultMessage() {
    if (! result.value) {
        return '';
    }

    if (result.value.valid) {
        return 'This credential is authentic and currently valid.';
    }

    return result.value.result === 'expired'
        ? 'This credential has expired and is no longer valid.'
        : 'We could not find a credential matching this token.';
}

onMounted(() => {
    if (token.value) {
        verify();
    }
});

watch(() => route.params.token, (t) => {
    token.value = t || '';

    if (t) {
        verify();
    }
});
</script>

<template>
    <div>
        <!-- Page header -->
        <section class="border-b border-slate-200/80 bg-gradient-to-br from-surface-muted via-white to-institutional/5">
            <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 sm:py-16 lg:px-8">
                <div class="max-w-2xl">
                    <p class="label-caps text-institutional">Trust & authenticity</p>
                    <h1 class="mt-2 font-display text-4xl font-bold tracking-tight text-institutional-dark sm:text-5xl">
                        Verify Credential
                    </h1>
                    <p class="mt-4 text-lg leading-relaxed text-text-secondary">
                        Confirm the authenticity of a Jocrams membership card or certificate using its QR code or verification token.
                    </p>
                </div>
            </div>
        </section>

        <!-- Content -->
        <section class="section-padding bg-white">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="grid gap-10 lg:grid-cols-5 lg:gap-14">
                    <!-- Form -->
                    <div class="lg:col-span-3">
                        <div class="card-modern p-6 sm:p-8 lg:p-10">
                            <div class="flex items-center gap-3">
                                <span class="flex size-12 items-center justify-center rounded-2xl bg-institutional/10 text-institutional">
                                    <ShieldCheckIcon class="size-7" aria-hidden="true" />
                                </span>
                                <div>
                                    <h2 class="font-display text-xl font-bold text-institutional-dark">Check a credential</h2>
                                    <p class="text-sm text-text-secondary">Enter the token printed on the card or encoded in the QR code</p>
                                </div>
                            </div>

                            <form class="mt-8" @submit.prevent="verify">
                                <label class="label" for="verify-token">Verification token</label>
                                <input
                                    id="verify-token"
                                    v-model="token"
                                    type="text"
                                    required
                                    autocomplete="off"
                                    spellcheck="false"
                                    class="input font-mono !rounded-xl !border-slate-200/80 !py-3 focus:!border-institutional focus:!ring-institutional/20"
                                    placeholder="e.g. jocrams-vrf-xxxxxxxx"
                                />

                                <button type="submit" class="btn-institutional mt-5 w-full sm:w-auto" :disabled="loading || ! token.trim()">
                                    {{ loading ? 'Verifying...' : 'Verify Credential' }}
                                </button>
                            </form>

                            <!-- Result -->
                            <div
                                v-if="result"
                                class="mt-8 rounded-2xl border p-6 sm:p-7"
                                :class="result.valid
                                    ? 'border-emerald-200 bg-emerald-50/80'
                                    : 'border-red-200 bg-red-50/80'"
                            >
                                <div class="flex items-start gap-4">
                                    <span
                                        class="flex size-12 shrink-0 items-center justify-center rounded-full"
                                        :class="result.valid ? 'bg-emerald-100 text-emerald-600' : 'bg-red-100 text-red-600'"
                                    >
                                        <CheckCircleIcon v-if="result.valid" class="size-7" aria-hidden="true" />
                                        <XCircleIcon v-else class="size-7" aria-hidden="true" />
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <h3
                                            class="font-display text-lg font-bold"
                                            :class="result.valid ? 'text-emerald-900' : 'text-red-900'"
                                        >
                                            {{ result.valid ? 'Valid credential' : 'Verification failed' }}
                                        </h3>
                                        <p class="mt-1 text-sm" :class="result.valid ? 'text-emerald-800' : 'text-red-800'">
                                            {{ resultMessage() }}
                                        </p>

                                        <dl v-if="result.valid && result.member" class="mt-6 grid gap-4 sm:grid-cols-2">
                                            <div class="rounded-xl bg-white/70 px-4 py-3 ring-1 ring-emerald-100">
                                                <dt class="label-caps !text-emerald-800/70">Member</dt>
                                                <dd class="mt-1 font-semibold text-emerald-950">{{ result.member.name }}</dd>
                                            </div>
                                            <div class="rounded-xl bg-white/70 px-4 py-3 ring-1 ring-emerald-100">
                                                <dt class="label-caps !text-emerald-800/70">Membership #</dt>
                                                <dd class="mt-1 font-semibold text-emerald-950">{{ result.member.membership_number }}</dd>
                                            </div>
                                            <div class="rounded-xl bg-white/70 px-4 py-3 ring-1 ring-emerald-100">
                                                <dt class="label-caps !text-emerald-800/70">Tier</dt>
                                                <dd class="mt-1 font-semibold text-emerald-950">{{ result.member.tier }}</dd>
                                            </div>
                                            <div v-if="result.member.title" class="rounded-xl bg-white/70 px-4 py-3 ring-1 ring-emerald-100">
                                                <dt class="label-caps !text-emerald-800/70">Credential</dt>
                                                <dd class="mt-1 font-semibold text-emerald-950">{{ result.member.title }}</dd>
                                            </div>
                                            <div class="rounded-xl bg-white/70 px-4 py-3 ring-1 ring-emerald-100">
                                                <dt class="label-caps !text-emerald-800/70">Issued</dt>
                                                <dd class="mt-1 font-semibold text-emerald-950">{{ formatDate(result.member.issued_at) }}</dd>
                                            </div>
                                            <div class="rounded-xl bg-white/70 px-4 py-3 ring-1 ring-emerald-100">
                                                <dt class="label-caps !text-emerald-800/70">Expires</dt>
                                                <dd class="mt-1 font-semibold text-emerald-950">{{ formatDate(result.member.expires_at) }}</dd>
                                            </div>
                                        </dl>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sidebar -->
                    <aside class="lg:col-span-2">
                        <div class="sticky top-24 space-y-6">
                            <div class="card-modern p-6 sm:p-7">
                                <div class="flex items-center gap-3">
                                    <QrCodeIcon class="size-8 text-institutional" aria-hidden="true" />
                                    <h2 class="font-display text-lg font-bold text-institutional-dark">How it works</h2>
                                </div>
                                <ol class="mt-5 space-y-4">
                                    <li
                                        v-for="(step, index) in steps"
                                        :key="step"
                                        class="flex gap-3 text-sm leading-relaxed text-text-secondary"
                                    >
                                        <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-institutional/10 text-xs font-bold text-institutional">
                                            {{ index + 1 }}
                                        </span>
                                        {{ step }}
                                    </li>
                                </ol>
                            </div>

                            <div class="rounded-2xl bg-gradient-to-br from-institutional to-institutional-dark p-6 text-white sm:p-7">
                                <h2 class="font-display text-lg font-bold">Need a credential?</h2>
                                <p class="mt-2 text-sm leading-relaxed text-slate-200">
                                    Active members receive digital membership cards and certificates with unique verification tokens.
                                </p>
                                <RouterLink to="/member/login" class="btn-gold mt-5 inline-flex text-sm">
                                    Member Login
                                </RouterLink>
                            </div>

                            <div class="rounded-2xl border border-slate-200/80 bg-surface-muted/50 p-6 text-sm text-text-secondary">
                                <p class="font-medium text-institutional-dark">Reporting fraud?</p>
                                <p class="mt-2 leading-relaxed">
                                    If you suspect a forged credential, please
                                    <RouterLink to="/contact" class="font-semibold text-institutional hover:underline">contact us</RouterLink>
                                    with the token and any details you have.
                                </p>
                            </div>
                        </div>
                    </aside>
                </div>
            </div>
        </section>
    </div>
</template>
