<script setup>
import { computed, ref } from 'vue';
import {
    BuildingLibraryIcon,
    ClipboardDocumentIcon,
    EnvelopeIcon,
    PhoneIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    bank: {
        type: Object,
        default: null,
    },
    fees: {
        type: Object,
        default: null,
    },
    title: {
        type: String,
        default: 'Pay by bank transfer',
    },
    description: {
        type: String,
        default: 'Transfer directly to the SICAMA UBA account. An administrator will verify and approve your payment.',
    },
    showFees: {
        type: Boolean,
        default: false,
    },
});

const copied = ref('');

const rows = computed(() => {
    if (!props.bank) {
        return [];
    }

    return [
        { label: 'Account name', value: props.bank.account_name },
        { label: 'Account number', value: props.bank.account_number },
        { label: 'Bank', value: props.bank.bank_name },
    ].filter((row) => row.value);
});

async function copyValue(value, key) {
    if (!value) {
        return;
    }

    try {
        await navigator.clipboard.writeText(String(value));
        copied.value = key;
        window.setTimeout(() => {
            if (copied.value === key) {
                copied.value = '';
            }
        }, 1800);
    } catch {
        copied.value = '';
    }
}

function formatFee(amount) {
    if (amount === null || amount === undefined) {
        return '—';
    }

    return `₦${Number(amount).toLocaleString()}`;
}
</script>

<template>
    <div class="overflow-hidden rounded-3xl border border-institutional/15 bg-white shadow-sm">
        <div class="bg-gradient-to-r from-institutional-dark via-institutional to-[#0c4a8c] px-6 py-5 text-white sm:px-8">
            <div class="flex items-start gap-3">
                <span class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-white/15">
                    <BuildingLibraryIcon class="size-6 text-accent-gold" />
                </span>
                <div>
                    <p class="label-caps text-accent-gold/90">Official payment channel</p>
                    <h3 class="mt-1 font-display text-xl font-bold">{{ title }}</h3>
                    <p class="mt-2 text-sm leading-relaxed text-slate-200">{{ description }}</p>
                </div>
            </div>
        </div>

        <div class="space-y-4 p-6 sm:p-8">
            <div
                v-for="row in rows"
                :key="row.label"
                class="flex flex-col gap-2 rounded-2xl border border-slate-100 bg-surface-muted/50 p-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ row.label }}</p>
                    <p class="mt-1 font-medium text-institutional-dark">{{ row.value }}</p>
                </div>
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 self-start rounded-xl bg-white px-3 py-2 text-xs font-semibold text-institutional shadow-sm ring-1 ring-slate-200 transition hover:bg-institutional hover:text-white"
                    @click="copyValue(row.value, row.label)"
                >
                    <ClipboardDocumentIcon class="size-4" />
                    {{ copied === row.label ? 'Copied' : 'Copy' }}
                </button>
            </div>

            <div
                v-if="showFees && fees"
                class="grid gap-3 sm:grid-cols-2"
            >
                <div class="rounded-2xl border border-amber-100 bg-amber-50/80 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-amber-800">Membership registration</p>
                    <p class="mt-1 font-display text-2xl font-bold text-institutional-dark">
                        {{ formatFee(fees.membership_registration) }}
                    </p>
                </div>
                <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Manuscript review</p>
                    <p class="mt-1 font-display text-2xl font-bold text-institutional-dark">
                        {{ formatFee(fees.manuscript_review) }}
                    </p>
                    <p class="mt-1 text-xs text-slate-500">Publication fee {{ formatFee(fees.publication) }} on acceptance</p>
                </div>
            </div>

            <div class="rounded-2xl border border-emerald-100 bg-emerald-50/70 p-4">
                <p class="text-sm font-semibold text-emerald-900">After payment</p>
                <p class="mt-1 text-sm leading-relaxed text-emerald-800">
                    Upload your receipt in the member Payments page so an admin can verify it in the app.
                    You can also email evidence to
                    <a
                        v-if="bank?.evidence_email"
                        :href="`mailto:${bank.evidence_email}`"
                        class="font-semibold underline"
                    >{{ bank.evidence_email }}</a>
                    <span v-else>the editorial office</span>
                    or WhatsApp the officers below.
                </p>
            </div>

            <div v-if="bank?.payment_contacts?.length" class="grid gap-3 sm:grid-cols-2">
                <div
                    v-for="person in bank.payment_contacts"
                    :key="person.phone"
                    class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm"
                >
                    <p class="text-xs font-semibold uppercase tracking-wide text-institutional">{{ person.role }}</p>
                    <p class="mt-1 font-medium text-institutional-dark">{{ person.name }}</p>
                    <a :href="`tel:${person.phone}`" class="mt-2 inline-flex items-center gap-1.5 text-sm text-text-secondary hover:text-institutional">
                        <PhoneIcon class="size-4" />
                        {{ person.phone }}
                    </a>
                </div>
            </div>

            <a
                v-if="bank?.evidence_email"
                :href="`mailto:${bank.evidence_email}?subject=Evidence%20of%20Payment`"
                class="btn-institutional inline-flex w-full items-center justify-center gap-2 sm:w-auto"
            >
                <EnvelopeIcon class="size-4" />
                Email payment evidence
            </a>
        </div>
    </div>
</template>
