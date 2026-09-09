<script setup>
import { ref, onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import {
    ArrowRightIcon,
    CalendarDaysIcon,
    CreditCardIcon,
    DocumentTextIcon,
    IdentificationIcon,
} from '@heroicons/vue/24/outline';
import AdminPageIntro from '../../components/admin/AdminPageIntro.vue';
import { useAuth } from '../../composables/useAuth';

const { memberUser, fetchMemberProfile, getMemberClient, isMemberEmailVerified, resendMemberVerificationEmail } = useAuth();
const snapshot = ref(null);
const payments = ref([]);
const verifyMessage = ref('');
const resending = ref(false);
const loading = ref(true);

onMounted(async () => {
    await fetchMemberProfile();

    try {
        const { data } = await getMemberClient().get('/dashboard');
        snapshot.value = data.data;
    } catch {
        snapshot.value = null;
    }

    if (memberUser.value?.member) {
        try {
            const { data } = await getMemberClient().get('/payments');
            payments.value = data.data?.slice(0, 5) || [];
        } catch {
            payments.value = [];
        }
    }

    loading.value = false;
});

async function resendVerification() {
    verifyMessage.value = '';
    resending.value = true;

    try {
        const data = await resendMemberVerificationEmail();
        verifyMessage.value = data.message;
    } catch (e) {
        verifyMessage.value = e.response?.data?.message || 'Unable to send verification email.';
    } finally {
        resending.value = false;
    }
}

function statusClass(status) {
    const map = {
        active: 'bg-emerald-100 text-emerald-800',
        pending: 'bg-amber-100 text-amber-800',
        expired: 'bg-red-100 text-red-800',
        successful: 'bg-emerald-100 text-emerald-800',
        failed: 'bg-red-100 text-red-800',
    };

    return map[status] || 'bg-slate-100 text-slate-700';
}

function formatDate(value) {
    return value ? new Date(value).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' }) : '—';
}

function eventDay(value) {
    return value ? new Date(value).getDate() : '—';
}

function eventMonth(value) {
    return value ? new Date(value).toLocaleDateString(undefined, { month: 'short' }) : '';
}
</script>

<template>
    <div>
        <AdminPageIntro :description="`Welcome back, ${memberUser?.name || 'member'}. Here is a snapshot of your membership activity.`" />

        <div v-if="memberUser && !isMemberEmailVerified()" class="mt-6 card-modern border-amber-200 bg-amber-50 p-6">
            <h2 class="font-semibold text-amber-900">Verify your email address</h2>
            <p class="mt-1 text-sm text-amber-800">
                We sent a verification link to <strong>{{ memberUser.email }}</strong>. Please verify your email to secure your account.
            </p>
            <p v-if="verifyMessage" class="mt-3 text-sm text-amber-900">{{ verifyMessage }}</p>
            <button type="button" class="btn-primary mt-4" :disabled="resending" @click="resendVerification">
                {{ resending ? 'Sending…' : 'Resend verification email' }}
            </button>
        </div>

        <div v-if="!memberUser?.member" class="mt-6 card-modern border-brand-200 bg-brand-50 p-6">
            <h2 class="font-semibold text-brand-900">Complete Your Membership</h2>
            <p class="mt-1 text-sm text-brand-700">You haven't submitted a membership application yet.</p>
            <RouterLink to="/member/applications" class="btn-primary mt-4 inline-flex">Start Application</RouterLink>
        </div>

        <div v-else-if="loading" class="mt-6 empty-state">Loading dashboard...</div>

        <div v-else class="mt-6 space-y-6">
            <div class="grid gap-4 sm:grid-cols-3">
                <div class="card-modern p-5">
                    <div class="flex items-center gap-3">
                        <span class="flex size-12 items-center justify-center rounded-xl bg-institutional/10">
                            <CalendarDaysIcon class="size-6 text-institutional" />
                        </span>
                        <div>
                            <p class="text-sm text-slate-500">Events attended</p>
                            <p class="text-2xl font-bold text-slate-900">{{ snapshot?.events_attended ?? 0 }}</p>
                        </div>
                    </div>
                </div>
                <div class="card-modern p-5">
                    <div class="flex items-center gap-3">
                        <span class="flex size-12 items-center justify-center rounded-xl bg-institutional/10">
                            <DocumentTextIcon class="size-6 text-institutional" />
                        </span>
                        <div>
                            <p class="text-sm text-slate-500">Journal submissions</p>
                            <p class="text-2xl font-bold text-slate-900">{{ snapshot?.journal_submissions_count ?? 0 }}</p>
                        </div>
                    </div>
                </div>
                <div class="card-modern p-5">
                    <div class="flex items-center gap-3">
                        <span class="flex size-12 items-center justify-center rounded-xl bg-institutional/10">
                            <IdentificationIcon class="size-6 text-institutional" />
                        </span>
                        <div>
                            <p class="text-sm text-slate-500">Credentials</p>
                            <p class="text-2xl font-bold text-slate-900">{{ snapshot?.credentials_count ?? 0 }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-3">
                <div class="card-modern p-6 lg:col-span-1">
                    <p class="text-sm font-medium text-slate-500">Membership Status</p>
                    <p class="mt-1 text-2xl font-bold capitalize text-slate-900">{{ snapshot?.membership?.status || memberUser.member.status }}</p>
                    <span class="mt-2 inline-flex badge capitalize" :class="statusClass(snapshot?.membership?.status || memberUser.member.status)">
                        {{ snapshot?.membership?.status || memberUser.member.status }}
                    </span>
                    <dl class="mt-5 space-y-3 text-sm">
                        <div>
                            <dt class="text-slate-500">Number</dt>
                            <dd class="font-medium">{{ snapshot?.membership?.membership_number || memberUser.member.membership_number || 'Pending' }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Tier</dt>
                            <dd class="font-medium">{{ snapshot?.membership?.tier || memberUser.member.tier?.name }}</dd>
                        </div>
                        <div v-if="snapshot?.membership?.expires_at || memberUser.member.expires_at">
                            <dt class="text-slate-500">Expires</dt>
                            <dd>{{ formatDate(snapshot?.membership?.expires_at || memberUser.member.expires_at) }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="card-modern p-6 lg:col-span-2">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="font-display text-lg font-bold text-slate-900">Upcoming events</h2>
                        <RouterLink to="/member/events" class="inline-flex items-center gap-1 text-sm font-semibold text-institutional hover:underline">
                            Browse events
                            <ArrowRightIcon class="size-4" />
                        </RouterLink>
                    </div>
                    <div v-if="!snapshot?.upcoming_events?.length" class="mt-4 text-sm text-slate-500">
                        No upcoming registered events.
                        <RouterLink to="/member/events" class="ml-1 font-medium text-institutional hover:underline">Find an event</RouterLink>
                    </div>
                    <ul v-else class="mt-4 divide-y divide-slate-100">
                        <li v-for="event in snapshot.upcoming_events" :key="event.uuid" class="flex items-center justify-between gap-4 py-4">
                            <div class="flex items-center gap-4">
                                <div class="flex size-14 shrink-0 flex-col items-center justify-center rounded-xl bg-institutional/10 text-institutional">
                                    <span class="text-[10px] font-bold uppercase">{{ eventMonth(event.starts_at) }}</span>
                                    <span class="text-xl font-bold leading-none">{{ eventDay(event.starts_at) }}</span>
                                </div>
                                <div>
                                    <RouterLink :to="`/member/events/${event.uuid}`" class="font-medium text-slate-900 hover:text-institutional">
                                        {{ event.title }}
                                    </RouterLink>
                                    <p class="mt-1 text-sm text-slate-500">{{ event.location || 'Location TBA' }} · {{ formatDate(event.starts_at) }}</p>
                                </div>
                            </div>
                            <span class="badge bg-brand-50 text-brand-700 capitalize">{{ event.registration_status }}</span>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <div class="card-modern p-6">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="font-display text-lg font-bold text-slate-900">Recent journal submissions</h2>
                        <RouterLink to="/member/journal" class="inline-flex items-center gap-1 text-sm font-semibold text-institutional hover:underline">
                            Open journal
                            <ArrowRightIcon class="size-4" />
                        </RouterLink>
                    </div>
                    <div v-if="!snapshot?.journal_submissions?.length" class="mt-4 text-sm text-slate-500">No submissions yet.</div>
                    <ul v-else class="mt-4 divide-y divide-slate-100">
                        <li v-for="submission in snapshot.journal_submissions" :key="submission.uuid" class="flex justify-between py-3 text-sm">
                            <RouterLink :to="`/member/journal/${submission.uuid}`" class="font-medium text-slate-900 hover:text-institutional">
                                {{ submission.title }}
                            </RouterLink>
                            <span class="badge capitalize">{{ submission.status.replaceAll('_', ' ') }}</span>
                        </li>
                    </ul>
                </div>

                <div class="card-modern p-6">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="font-display text-lg font-bold text-slate-900">Recent payments</h2>
                        <RouterLink to="/member/payments" class="inline-flex items-center gap-1 text-sm font-semibold text-institutional hover:underline">
                            View all
                            <CreditCardIcon class="size-4" />
                        </RouterLink>
                    </div>
                    <div v-if="payments.length === 0" class="mt-4 text-sm text-slate-500">No payments yet.</div>
                    <ul v-else class="mt-4 divide-y divide-slate-100">
                        <li v-for="p in payments" :key="p.uuid" class="flex justify-between py-3 text-sm">
                            <span>{{ p.purpose }} — {{ p.reference }}</span>
                            <span :class="['badge capitalize', statusClass(p.status)]">{{ p.status }}</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</template>
