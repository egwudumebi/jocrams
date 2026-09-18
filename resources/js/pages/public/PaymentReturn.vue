<script setup>
import { onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { CheckCircleIcon, ExclamationCircleIcon } from '@heroicons/vue/24/outline';
import { publicApi } from '../../api/client';

const route = useRoute();
const router = useRouter();

const loading = ref(true);
const message = ref('');
const error = ref('');

onMounted(async () => {
    const reference = route.query.reference || route.query.trxref;

    if (typeof reference !== 'string' || !reference) {
        loading.value = false;
        error.value = 'Missing payment reference.';
        return;
    }

    try {
        const { data } = await publicApi().get(`/payments/verify/${encodeURIComponent(reference)}`);
        message.value = data.message || 'Payment verified successfully.';

        const redirectTo = data.redirect_to || '/';
        window.setTimeout(() => {
            router.replace({ path: redirectTo, query: { reference } });
        }, 1200);
    } catch (e) {
        error.value = e.response?.data?.message || 'Unable to verify payment.';
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <section class="mx-auto flex min-h-[50vh] max-w-lg items-center px-4 py-16 sm:px-6">
        <div class="card-modern w-full p-8 text-center">
            <div v-if="loading" class="space-y-4">
                <div class="mx-auto size-10 animate-spin rounded-full border-2 border-institutional/20 border-t-institutional" />
                <p class="text-sm text-slate-600">Confirming your payment…</p>
            </div>

            <div v-else-if="message" class="space-y-4">
                <CheckCircleIcon class="mx-auto size-12 text-emerald-600" />
                <h1 class="font-display text-2xl font-bold text-slate-900">Payment confirmed</h1>
                <p class="text-sm text-slate-600">{{ message }}</p>
                <p class="text-xs text-slate-500">Redirecting you now…</p>
            </div>

            <div v-else class="space-y-4">
                <ExclamationCircleIcon class="mx-auto size-12 text-red-500" />
                <h1 class="font-display text-2xl font-bold text-slate-900">Verification issue</h1>
                <p class="text-sm text-slate-600">{{ error }}</p>
            </div>
        </div>
    </section>
</template>
