<script setup>
import { ref, onMounted } from 'vue';
import { useRoute, useRouter, RouterLink } from 'vue-router';
import AuthPageShell from '../../components/layout/AuthPageShell.vue';
import { useAuth } from '../../composables/useAuth';

const route = useRoute();
const router = useRouter();
const { memberToken, fetchMemberProfile, getMemberClient } = useAuth();
const message = ref('');
const error = ref('');
const verifying = ref(true);

onMounted(async () => {
    if (!memberToken.value) {
        return router.replace({
            name: 'member.login',
            query: { redirect: route.fullPath },
        });
    }

    const id = route.query.id;
    const hash = route.query.hash;
    const expires = route.query.expires;
    const signature = route.query.signature;

    if (typeof id !== 'string' || typeof hash !== 'string' || typeof expires !== 'string' || typeof signature !== 'string') {
        error.value = 'This verification link is invalid or incomplete.';
        verifying.value = false;
        return;
    }

    try {
        const params = new URLSearchParams({ expires, signature });
        const { data } = await getMemberClient().get(`/auth/email/verify/${id}/${hash}?${params}`);
        message.value = data.message;
        await fetchMemberProfile();
        setTimeout(() => router.push('/member/dashboard'), 1500);
    } catch (e) {
        error.value = e.response?.data?.message || 'Unable to verify your email. The link may have expired.';
    } finally {
        verifying.value = false;
    }
});
</script>

<template>
    <AuthPageShell title="Verify your email" subtitle="Confirm your email address to secure your member account.">
        <div class="card-modern p-6 sm:p-8">
            <h2 class="font-display text-2xl font-bold text-institutional-dark">Email verification</h2>

            <div v-if="verifying" class="mt-6 text-sm text-text-secondary">Verifying your email address…</div>

            <div v-else-if="message" class="mt-6 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                {{ message }} Redirecting to your dashboard…
            </div>

            <div v-else-if="error" class="mt-6 space-y-4">
                <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ error }}</div>
                <RouterLink to="/member/dashboard" class="btn-institutional inline-flex">Go to dashboard</RouterLink>
            </div>
        </div>
    </AuthPageShell>
</template>
