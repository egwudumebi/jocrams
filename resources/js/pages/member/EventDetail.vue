<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute, RouterLink } from 'vue-router';
import {
    CalendarDaysIcon,
    CheckBadgeIcon,
    ClockIcon,
    MapPinIcon,
    TicketIcon,
} from '@heroicons/vue/24/outline';
import EventBannerThumb from '../../components/admin/EventBannerThumb.vue';
import { publicApi } from '../../api/client';
import { useAuth } from '../../composables/useAuth';

const route = useRoute();
const { memberUser, memberToken, getMemberClient } = useAuth();

const event = ref(null);
const myRegistration = ref(null);
const loading = ref(true);
const submitting = ref(false);
const message = ref('');
const error = ref('');

const form = ref({
    name: '',
    email: '',
    phone: '',
    member_type: 'member',
});

const isRegistered = computed(() => !!myRegistration.value);
const isConfirmed = computed(() => myRegistration.value?.status === 'confirmed');
const isPending = computed(() => myRegistration.value?.status === 'pending');

const fee = computed(() => {
    if (!event.value) return 0;
    const category = form.value.member_type || 'member';

    return Number(event.value.fees_by_category?.[category] ?? event.value.fee ?? 0);
});

const isPaid = computed(() => fee.value > 0);
const registrationOpen = computed(() => event.value?.registration_stats?.registration_open ?? false);
const isFull = computed(() => event.value?.registration_stats?.is_full ?? false);
const showRegistrationForm = computed(() => !isRegistered.value && registrationOpen.value && !isFull.value);

onMounted(async () => {
    await Promise.all([loadEvent(), loadMyRegistration()]);

    form.value.name = memberUser.value?.name || '';
    form.value.email = memberUser.value?.email || '';
    form.value.phone = memberUser.value?.phone || '';

    const reference = route.query.reference || route.query.trxref;
    if (typeof reference === 'string' && reference) {
        await verifyPayment(reference);
    }
});

async function loadEvent() {
    loading.value = true;

    try {
        const { data } = await publicApi().get(`/events/${route.params.uuid}`);
        event.value = data.data;
    } finally {
        loading.value = false;
    }
}

async function loadMyRegistration() {
    try {
        const { data } = await getMemberClient().get(`/events/${route.params.uuid}/registration`);
        myRegistration.value = data.data;
    } catch {
        myRegistration.value = null;
    }
}

function formatDateTime(value) {
    return value ? new Date(value).toLocaleString() : '—';
}

function registrationStatusClass(status) {
    const map = {
        confirmed: 'bg-emerald-100 text-emerald-800',
        pending: 'bg-amber-100 text-amber-800',
        cancelled: 'bg-slate-100 text-slate-600',
    };

    return map[status] || 'bg-slate-100 text-slate-700';
}

function ticketTypeLabel(value) {
    const map = {
        member: 'Member',
        non_member: 'Non-member',
        student: 'Student',
        institution: 'Institution',
    };

    return map[value] || value || '—';
}

async function verifyPayment(reference) {
    try {
        const { data } = await publicApi(memberToken.value || undefined).get(
            `/payments/verify/${encodeURIComponent(reference)}`,
        );
        message.value = data.message;
        await loadMyRegistration();
    } catch (e) {
        error.value = e.response?.data?.message || 'Unable to verify payment.';
    }
}

async function register() {
    message.value = '';
    error.value = '';
    submitting.value = true;

    try {
        const { data } = await getMemberClient().post(`/events/${event.value.uuid}/register`, { ...form.value });
        message.value = data.message;
        myRegistration.value = data.data;
    } catch (e) {
        if (e.response?.status === 422 && e.response?.data?.requires_payment) {
            await startPayment();
            return;
        }

        error.value = e.response?.data?.message
            || Object.values(e.response?.data?.errors || {}).flat().join(' ')
            || 'Unable to register.';
    } finally {
        submitting.value = false;
    }
}

async function startPayment() {
    const payload = {
        ...form.value,
        gateway: 'paystack',
        idempotency_key: `event-${event.value.uuid}-${Date.now()}`,
    };

    const { data } = await getMemberClient().post(`/events/${event.value.uuid}/payments/initialize`, payload);

    if (data.data?.authorization_url) {
        window.location.href = data.data.authorization_url;
    } else {
        error.value = 'Unable to start payment.';
    }
}

async function submitRegistration() {
    if (isPaid.value) {
        submitting.value = true;

        try {
            await startPayment();
        } catch (e) {
            error.value = e.response?.data?.message || 'Unable to start payment.';
        } finally {
            submitting.value = false;
        }

        return;
    }

    await register();
}
</script>

<template>
    <div>
        <RouterLink to="/member/events" class="inline-flex items-center text-sm font-semibold text-institutional hover:underline">
            ← Back to events
        </RouterLink>

        <div v-if="loading" class="mt-6 text-slate-500">Loading event...</div>

        <div v-else-if="event" class="mt-6 grid gap-8 lg:grid-cols-[1.4fr_1fr]">
            <div class="card-modern overflow-hidden">
                <div class="overflow-hidden">
                    <EventBannerThumb :url="event.banner_url" :title="event.title" variant="card" />
                </div>
                <div class="p-6 sm:p-8">
                    <p class="label-caps text-institutional">Event</p>
                    <h1 class="mt-2 font-display text-3xl font-bold text-institutional-dark sm:text-4xl">{{ event.title }}</h1>
                    <div
                        v-if="event.description"
                        class="prose prose-lg mt-4 max-w-none text-text-secondary [&_a]:text-brand-600 [&_a]:underline [&_b]:font-semibold [&_h3]:font-semibold [&_i]:italic [&_ol]:mb-2 [&_ol]:ml-5 [&_ol]:list-decimal [&_p]:mb-2 [&_strong]:font-semibold [&_u]:underline [&_ul]:mb-2 [&_ul]:ml-5 [&_ul]:list-disc"
                        v-html="event.description"
                    />
                    <div
                        v-if="event.body"
                        class="prose mt-6 max-w-none text-slate-700 [&_a]:text-brand-600 [&_a]:underline [&_b]:font-semibold [&_h3]:font-semibold [&_i]:italic [&_ol]:mb-2 [&_ol]:ml-5 [&_ol]:list-decimal [&_p]:mb-2 [&_strong]:font-semibold [&_u]:underline [&_ul]:mb-2 [&_ul]:ml-5 [&_ul]:list-disc"
                        v-html="event.body"
                    />

                    <div v-if="event.sessions?.length" class="mt-8">
                        <h2 class="font-display text-xl font-bold text-slate-900">Sessions</h2>
                        <ul class="mt-4 space-y-3">
                            <li v-for="session in event.sessions" :key="session.id" class="rounded-xl border border-slate-100 p-4">
                                <p class="font-medium text-slate-900">{{ session.title }}</p>
                                <p class="mt-1 text-sm text-slate-500">{{ formatDateTime(session.starts_at) }}</p>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <aside class="card-modern h-fit p-6">
                <div class="flex items-start justify-between gap-3">
                    <h2 class="font-display text-lg font-bold text-slate-900">
                        {{ isRegistered ? 'Your registration' : 'Registration' }}
                    </h2>
                    <span
                        v-if="isRegistered"
                        class="badge capitalize"
                        :class="registrationStatusClass(myRegistration.status)"
                    >
                        {{ myRegistration.status }}
                    </span>
                </div>

                <dl class="mt-4 space-y-4 text-sm">
                    <div class="flex gap-3">
                        <CalendarDaysIcon class="size-5 shrink-0 text-institutional" />
                        <div>
                            <dt class="text-slate-500">Date</dt>
                            <dd class="font-medium">{{ formatDateTime(event.starts_at) }}</dd>
                        </div>
                    </div>
                    <div class="flex gap-3">
                        <MapPinIcon class="size-5 shrink-0 text-institutional" />
                        <div>
                            <dt class="text-slate-500">Location</dt>
                            <dd class="font-medium">{{ event.location || event.virtual_url || 'TBA' }}</dd>
                        </div>
                    </div>
                    <div v-if="!isRegistered" class="flex gap-3">
                        <TicketIcon class="size-5 shrink-0 text-institutional" />
                        <div>
                            <dt class="text-slate-500">Fee</dt>
                            <dd class="font-medium">{{ isPaid ? `NGN ${fee.toLocaleString()}` : 'Free' }}</dd>
                        </div>
                    </div>
                </dl>

                <div v-if="message && !isRegistered" class="mt-5 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">{{ message }}</div>
                <div v-if="error" class="mt-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ error }}</div>

                <div
                    v-if="isConfirmed"
                    class="mt-5 rounded-2xl border border-emerald-200 bg-gradient-to-br from-emerald-50 via-white to-white p-5"
                >
                    <div class="flex items-center gap-2 text-emerald-700">
                        <CheckBadgeIcon class="size-5" />
                        <p class="text-sm font-semibold uppercase tracking-wide">You're registered</p>
                    </div>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div>
                            <dt class="text-slate-500">Registration number</dt>
                            <dd class="font-mono font-semibold text-slate-900">{{ myRegistration.registration_number }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Ticket type</dt>
                            <dd class="font-medium text-slate-800">{{ ticketTypeLabel(myRegistration.member_type) }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Registered as</dt>
                            <dd class="font-medium text-slate-800">{{ myRegistration.registrant_name }}</dd>
                        </div>
                    </dl>
                    <p class="mt-4 text-sm text-slate-600">
                        Bring your registration number to the venue. A confirmation email was sent to
                        <span class="font-semibold text-slate-900">{{ myRegistration.registrant_email }}</span>.
                    </p>
                </div>

                <div
                    v-else-if="isPending"
                    class="mt-5 rounded-2xl border border-amber-200 bg-gradient-to-br from-amber-50 via-white to-white p-5"
                >
                    <div class="flex items-center gap-2 text-amber-800">
                        <ClockIcon class="size-5" />
                        <p class="text-sm font-semibold uppercase tracking-wide">Registration pending</p>
                    </div>
                    <p class="mt-3 text-sm text-slate-600">
                        Your registration is awaiting payment confirmation. If you completed payment, it may take a moment to update.
                    </p>
                    <dl v-if="myRegistration.registration_number" class="mt-4 space-y-2 text-sm">
                        <div>
                            <dt class="text-slate-500">Registration number</dt>
                            <dd class="font-mono font-semibold text-slate-900">{{ myRegistration.registration_number }}</dd>
                        </div>
                    </dl>
                    <RouterLink to="/member/events" class="btn-secondary mt-4 inline-flex w-full justify-center !rounded-xl">
                        Back to my events
                    </RouterLink>
                </div>

                <div v-if="!registrationOpen && !isRegistered" class="mt-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    Registration is currently closed.
                </div>
                <div v-else-if="isFull && !isRegistered" class="mt-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    This event is full.
                </div>

                <form v-else-if="showRegistrationForm" class="mt-6 space-y-4" @submit.prevent="submitRegistration">
                    <div>
                        <label class="label">Full name</label>
                        <input v-model="form.name" required class="input" />
                    </div>
                    <div>
                        <label class="label">Email</label>
                        <input v-model="form.email" type="email" required class="input" />
                    </div>
                    <div>
                        <label class="label">Phone</label>
                        <input v-model="form.phone" class="input" />
                    </div>
                    <div>
                        <label class="label">Ticket type</label>
                        <select v-model="form.member_type" class="input">
                            <option value="member">Member</option>
                            <option value="student">Student</option>
                            <option value="institution">Institution</option>
                        </select>
                    </div>
                    <button type="submit" class="btn-institutional w-full" :disabled="submitting">
                        {{ submitting ? 'Processing…' : (isPaid ? 'Pay & Register' : 'Register') }}
                    </button>
                </form>
            </aside>
        </div>
    </div>
</template>
