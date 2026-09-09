<script setup>
import { computed, ref, watch } from 'vue';
import {
    CheckBadgeIcon,
    ExclamationCircleIcon,
    IdentificationIcon,
    SparklesIcon,
} from '@heroicons/vue/24/outline';
import { publicApi } from '../../api/client';

const props = defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    autoVerified: {
        type: Boolean,
        default: false,
    },
    verifiedMemberName: {
        type: String,
        default: '',
    },
    verifiedTierName: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['update:modelValue', 'verified', 'invalid']);

const status = ref(props.autoVerified ? 'verified' : 'idle');
const memberName = ref(props.verifiedMemberName);
const tierName = ref(props.verifiedTierName);
const errorMessage = ref('');
let verifyTimer = null;

const displayValue = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value.toUpperCase()),
});

watch(
    () => props.autoVerified,
    (isVerified) => {
        if (isVerified) {
            status.value = 'verified';
            memberName.value = props.verifiedMemberName;
            tierName.value = props.verifiedTierName;
        }
    },
    { immediate: true },
);

watch(displayValue, (value) => {
    if (props.autoVerified) {
        return;
    }

    clearTimeout(verifyTimer);
    errorMessage.value = '';

    const trimmed = value.trim();
    if (!trimmed) {
        status.value = 'idle';
        emit('invalid');
        return;
    }

    status.value = 'typing';
    verifyTimer = setTimeout(() => verify(trimmed), 450);
});

async function verify(number) {
    status.value = 'verifying';
    errorMessage.value = '';

    try {
        const { data } = await publicApi().post('/members/verify-number', {
            membership_number: number,
        });

        if (data.data?.valid) {
            status.value = 'verified';
            memberName.value = data.data.member_name || '';
            tierName.value = data.data.tier_name || '';
            emit('verified', data.data);
            return;
        }

        status.value = 'invalid';
        errorMessage.value = 'No active membership found for this number.';
        emit('invalid');
    } catch {
        status.value = 'invalid';
        errorMessage.value = 'Unable to verify membership right now. Try again.';
        emit('invalid');
    }
}
</script>

<template>
    <Transition
        enter-active-class="transition duration-300 ease-out"
        enter-from-class="opacity-0 -translate-y-2 scale-[0.98]"
        enter-to-class="opacity-100 translate-y-0 scale-100"
        leave-active-class="transition duration-200 ease-in"
        leave-from-class="opacity-100 translate-y-0 scale-100"
        leave-to-class="opacity-0 -translate-y-1 scale-[0.98]"
    >
        <div
            class="relative overflow-hidden rounded-2xl border border-institutional/20 bg-gradient-to-br from-institutional/8 via-white to-accent-gold/10 p-4 shadow-sm"
        >
            <div class="pointer-events-none absolute -right-6 -top-6 size-24 rounded-full bg-institutional/10 blur-2xl" />
            <div class="pointer-events-none absolute -bottom-8 -left-4 size-20 rounded-full bg-accent-gold/20 blur-2xl" />

            <div class="relative flex items-start gap-3">
                <span
                    class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-institutional text-white shadow-md shadow-institutional/25"
                >
                    <IdentificationIcon class="size-5" />
                </span>

                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <p class="text-sm font-semibold text-institutional-dark">Member verification</p>
                        <SparklesIcon class="size-4 text-accent-gold" />
                    </div>
                    <p class="mt-0.5 text-xs text-slate-600">
                        Enter your membership number to unlock member ticket pricing.
                    </p>

                    <div v-if="autoVerified" class="mt-3 flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2.5">
                        <CheckBadgeIcon class="size-5 shrink-0 text-emerald-600" />
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-emerald-900">
                                Verified as {{ verifiedMemberName || 'active member' }}
                            </p>
                            <p v-if="verifiedTierName" class="text-xs text-emerald-700">{{ verifiedTierName }}</p>
                            <p class="mt-0.5 font-mono text-xs text-emerald-800">{{ displayValue }}</p>
                        </div>
                    </div>

                    <div v-else class="mt-3">
                        <label class="sr-only" for="event-membership-number">Membership number</label>
                        <div
                            class="flex items-center gap-2 rounded-xl border bg-white px-3 py-2 transition"
                            :class="{
                                'border-slate-200 focus-within:border-institutional focus-within:ring-2 focus-within:ring-institutional/15': status === 'idle' || status === 'typing',
                                'border-institutional/40 ring-2 ring-institutional/10': status === 'verifying',
                                'border-emerald-300 ring-2 ring-emerald-100': status === 'verified',
                                'border-red-300 ring-2 ring-red-100': status === 'invalid',
                            }"
                        >
                            <span class="rounded-md bg-institutional/10 px-2 py-0.5 font-mono text-xs font-bold text-institutional">
                                MEM
                            </span>
                            <input
                                id="event-membership-number"
                                v-model="displayValue"
                                type="text"
                                autocomplete="off"
                                spellcheck="false"
                                placeholder="MEM-XXXXXXXX"
                                class="min-w-0 flex-1 border-0 bg-transparent font-mono text-sm uppercase tracking-wide text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-0"
                            />
                            <span v-if="status === 'verifying'" class="size-4 animate-spin rounded-full border-2 border-institutional/20 border-t-institutional" />
                            <CheckBadgeIcon v-else-if="status === 'verified'" class="size-5 text-emerald-600" />
                            <ExclamationCircleIcon v-else-if="status === 'invalid'" class="size-5 text-red-500" />
                        </div>

                        <p v-if="status === 'verified'" class="mt-2 text-xs font-medium text-emerald-700">
                            Welcome back, {{ memberName }}<span v-if="tierName"> · {{ tierName }}</span>
                        </p>
                        <p v-else-if="status === 'invalid'" class="mt-2 text-xs font-medium text-red-600">
                            {{ errorMessage }}
                        </p>
                        <p v-else class="mt-2 text-xs text-slate-500">
                            Found on your membership card or credential email.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </Transition>
</template>
