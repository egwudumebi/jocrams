<script setup>
import { computed, ref, watch } from 'vue';
import { ArrowUpTrayIcon, DocumentArrowUpIcon } from '@heroicons/vue/24/outline';
import { useAuth } from '../../composables/useAuth';
import { extractApiError } from '../../utils/apiError';

const props = defineProps({
    purpose: {
        type: String,
        default: 'dues',
    },
    amount: {
        type: [Number, String],
        default: '',
    },
    relatedUuid: {
        type: String,
        default: '',
    },
    purposes: {
        type: Array,
        default: () => [
            { value: 'dues', label: 'Membership dues / renewal' },
            { value: 'registration', label: 'Membership registration fee' },
            { value: 'journal_submission_fee', label: 'Journal manuscript review fee' },
            { value: 'journal_publication_fee', label: 'Journal publication fee' },
            { value: 'event_fee', label: 'Event registration fee' },
        ],
    },
});

const emit = defineEmits(['submitted']);

const { getMemberClient } = useAuth();
const submitting = ref(false);
const error = ref('');
const message = ref('');
const file = ref(null);

const form = ref({
    purpose: props.purpose,
    amount: props.amount ? String(props.amount) : '',
    payer_reference: '',
    member_note: '',
    related_uuid: props.relatedUuid || '',
});

watch(
    () => [props.purpose, props.amount, props.relatedUuid],
    () => {
        form.value.purpose = props.purpose;
        if (props.amount !== '' && props.amount !== null && props.amount !== undefined) {
            form.value.amount = String(props.amount);
        }
        form.value.related_uuid = props.relatedUuid || '';
    },
);

const needsRelated = computed(() =>
    ['journal_submission_fee', 'journal_publication_fee', 'event_fee'].includes(form.value.purpose),
);

function onFileChange(event) {
    file.value = event.target.files?.[0] || null;
}

async function submit() {
    error.value = '';
    message.value = '';

    if (!file.value) {
        error.value = 'Please attach your bank transfer receipt (JPG, PNG, or PDF).';
        return;
    }

    submitting.value = true;

    try {
        const fd = new FormData();
        fd.append('purpose', form.value.purpose);
        fd.append('amount', form.value.amount);
        fd.append('receipt', file.value);

        if (form.value.payer_reference) {
            fd.append('payer_reference', form.value.payer_reference);
        }

        if (form.value.member_note) {
            fd.append('member_note', form.value.member_note);
        }

        if (form.value.related_uuid) {
            fd.append('related_uuid', form.value.related_uuid);
        }

        const { data } = await getMemberClient().post('/payment-proofs', fd, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });

        message.value = data.message || 'Receipt submitted for verification.';
        file.value = null;
        form.value.payer_reference = '';
        form.value.member_note = '';
        emit('submitted', data.data);
    } catch (e) {
        error.value = extractApiError(e, 'Unable to submit payment receipt.');
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-7">
        <div class="flex items-start gap-3">
            <span class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-institutional/10 text-institutional">
                <DocumentArrowUpIcon class="size-6" />
            </span>
            <div>
                <h3 class="font-display text-lg font-bold text-institutional-dark">Submit payment receipt</h3>
                <p class="mt-1 text-sm text-text-secondary">
                    Transfer to the UBA account, then upload your receipt here. An admin will verify and activate your payment in the app.
                </p>
            </div>
        </div>

        <div v-if="message" class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ message }}
        </div>
        <div v-if="error" class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ error }}
        </div>

        <form class="mt-5 space-y-4" @submit.prevent="submit">
            <div>
                <label class="label">Payment type</label>
                <select v-model="form.purpose" class="input" required>
                    <option v-for="item in purposes" :key="item.value" :value="item.value">
                        {{ item.label }}
                    </option>
                </select>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label">Amount (NGN)</label>
                    <input v-model="form.amount" type="number" min="0.01" step="0.01" class="input" required />
                </div>
                <div>
                    <label class="label">Bank transfer reference</label>
                    <input v-model="form.payer_reference" type="text" class="input" placeholder="Optional" />
                </div>
            </div>

            <div v-if="needsRelated">
                <label class="label">
                    {{ form.purpose === 'event_fee' ? 'Event registration UUID' : 'Journal submission UUID' }}
                </label>
                <input v-model="form.related_uuid" type="text" class="input" :required="needsRelated" placeholder="Paste UUID if not prefilled" />
            </div>

            <div>
                <label class="label">Note to admin</label>
                <textarea v-model="form.member_note" rows="2" class="input" placeholder="Optional details" />
            </div>

            <div>
                <label class="label">Receipt file</label>
                <label class="mt-1 flex cursor-pointer flex-col items-center justify-center rounded-2xl border border-dashed border-institutional/30 bg-institutional/5 px-4 py-8 text-center transition hover:bg-institutional/10">
                    <ArrowUpTrayIcon class="size-8 text-institutional" />
                    <span class="mt-2 text-sm font-medium text-institutional-dark">
                        {{ file ? file.name : 'Upload JPG, PNG, or PDF (max 5MB)' }}
                    </span>
                    <input type="file" class="hidden" accept=".jpg,.jpeg,.png,.pdf,.webp,image/*,application/pdf" @change="onFileChange" />
                </label>
            </div>

            <button type="submit" class="btn-institutional inline-flex items-center gap-2" :disabled="submitting">
                <DocumentArrowUpIcon class="size-4" />
                {{ submitting ? 'Submitting…' : 'Submit for verification' }}
            </button>
        </form>
    </div>
</template>
