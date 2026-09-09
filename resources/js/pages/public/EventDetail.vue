<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useRoute, useRouter, RouterLink } from 'vue-router';
import {
    CalendarDaysIcon,
    ClockIcon,
    MapPinIcon,
    TicketIcon,
} from '@heroicons/vue/24/outline';
import { publicApi } from '../../api/client';
import { useAuth } from '../../composables/useAuth';
import { applyDynamicEventSeo } from '../../composables/useSeo';
import { useSiteBranding } from '../../composables/useSiteBranding';
import EventBannerThumb from '../../components/admin/EventBannerThumb.vue';
import EventMembershipNumberPanel from '../../components/events/EventMembershipNumberPanel.vue';

const route = useRoute();
const router = useRouter();
const { isMemberAuthenticated, memberUser, memberToken, getMemberClient } = useAuth();
const { branding, loadBranding } = useSiteBranding();

const event = ref(null);
const loading = ref(true);
const submitting = ref(false);
const message = ref('');
const error = ref('');
const membershipVerified = ref(false);
const confirmedRegistration = ref(null);

const form = ref({
    name: '',
    email: '',
    phone: '',
    member_type: 'non_member',
    membership_number: '',
});

onMounted(async () => {
    await loadBranding();
    await loadEvent();
    if (memberUser.value) {
        form.value.name = memberUser.value.name || '';
        form.value.email = memberUser.value.email || '';
        form.value.phone = memberUser.value.phone || '';
        form.value.member_type = 'member';
        form.value.membership_number = memberUser.value.member?.membership_number || '';
        membershipVerified.value = !!memberUser.value.member?.membership_number;
    }

    const reference = route.query.reference || route.query.trxref;
    if (typeof reference === 'string' && reference) {
        await verifyPayment(reference);
    }
});

const isMemberTicket = computed(() => form.value.member_type === 'member');
const requiresMembershipNumber = computed(() => isMemberTicket.value && !isMemberAuthenticated.value);
const membershipReady = computed(() => !requiresMembershipNumber.value || membershipVerified.value);
const autoVerifiedMembership = computed(() => isMemberAuthenticated.value && isMemberTicket.value);
const verifiedMemberName = computed(() => memberUser.value?.name || '');
const verifiedTierName = computed(() => memberUser.value?.member?.tier?.name || '');

watch(event, (value) => {
    if (!value) {
        return;
    }

    applyDynamicEventSeo(route, branding.value || window.__APP_SEO__?.branding || {}, value);
});

watch(() => form.value.member_type, (type) => {
    membershipVerified.value = autoVerifiedMembership.value;
    error.value = '';

    if (type !== 'member') {
        form.value.membership_number = '';
    }
});

const fee = computed(() => {
    if (!event.value) return 0;
    const category = form.value.member_type || 'non_member';
    return Number(event.value.fees_by_category?.[category] ?? event.value.fee ?? 0);
});

const isPaid = computed(() => fee.value > 0);
const registrationOpen = computed(() => event.value?.registration_stats?.registration_open ?? false);
const isFull = computed(() => event.value?.registration_stats?.is_full ?? false);

async function loadEvent() {
    loading.value = true;
    try {
        const { data } = await publicApi().get(`/events/${route.params.uuid}`);
        event.value = data.data;
    } finally {
        loading.value = false;
    }
}

function formatDateTime(value) {
    return value ? new Date(value).toLocaleString() : '—';
}

async function verifyPayment(reference) {
    try {
        const { data } = await publicApi(memberToken.value || undefined).get(
            `/payments/verify/${encodeURIComponent(reference)}`,
        );
        message.value = data.message;

        if (data.registration?.status === 'confirmed') {
            confirmedRegistration.value = data.registration;
        }
    } catch (e) {
        error.value = e.response?.data?.message || 'Unable to verify payment.';
    }
}

async function register() {
    message.value = '';
    error.value = '';
    submitting.value = true;

    try {
        const payload = { ...form.value };
        let response;

        if (isMemberAuthenticated.value) {
            response = await getMemberClient().post(`/events/${event.value.uuid}/register`, payload);
        } else {
            response = await publicApi().post(`/events/${event.value.uuid}/register`, payload);
        }

        message.value = response.data.message;

        if (response.data.data?.status === 'confirmed') {
            confirmedRegistration.value = {
                registration_number: response.data.data.registration_number,
                registrant_name: response.data.data.registrant_name || form.value.name,
                registrant_email: response.data.data.registrant_email || form.value.email,
                status: 'confirmed',
                event_title: event.value?.title,
                event_date: event.value?.starts_at ? formatDateTime(event.value.starts_at) : '',
                event_location: event.value?.location || event.value?.virtual_url || 'TBA',
            };
        }
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

    const client = isMemberAuthenticated.value ? getMemberClient() : publicApi();
    const path = `/events/${event.value.uuid}/payments/initialize`;
    const { data } = await client.post(path, payload);

    if (data.data?.authorization_url) {
        window.location.href = data.data.authorization_url;
    } else {
        error.value = 'Unable to start payment.';
    }
}

async function submitRegistration() {
    if (requiresMembershipNumber.value && !membershipVerified.value) {
        error.value = 'Verify your membership number to continue with member pricing.';
        return;
    }

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

function onMembershipVerified() {
    membershipVerified.value = true;
    error.value = '';
}

function onMembershipInvalid() {
    membershipVerified.value = false;
}
</script>

<template>
    <div>
        <section class="border-b border-slate-200/80 bg-gradient-to-br from-surface-muted via-white to-institutional/5">
            <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
                <RouterLink to="/events" class="text-sm font-semibold text-institutional hover:underline">← Back to events</RouterLink>
                <div v-if="loading" class="mt-6 text-slate-500">Loading event...</div>
                <div v-else-if="event" class="mt-6 grid gap-8 lg:grid-cols-[1.4fr_1fr]">
                    <div>
                        <div class="mb-6 overflow-hidden rounded-2xl">
                            <EventBannerThumb :url="event.banner_url" :title="event.title" variant="card" />
                        </div>
                        <p class="label-caps text-institutional">Event</p>
                        <h1 class="mt-2 font-display text-4xl font-bold text-institutional-dark">{{ event.title }}</h1>
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

                    <aside class="card-modern h-fit p-6">
                        <dl class="space-y-4 text-sm">
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
                            <div class="flex gap-3">
                                <TicketIcon class="size-5 shrink-0 text-institutional" />
                                <div>
                                    <dt class="text-slate-500">Fee</dt>
                                    <dd class="font-medium">{{ isPaid ? `NGN ${fee.toLocaleString()}` : 'Free' }}</dd>
                                </div>
                            </div>
                        </dl>

                        <div v-if="message && !confirmedRegistration" class="mt-5 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">{{ message }}</div>
                        <div v-if="error" class="mt-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ error }}</div>

                        <div
                            v-if="confirmedRegistration"
                            class="mt-5 rounded-2xl border border-emerald-200 bg-gradient-to-br from-emerald-50 via-white to-white p-5"
                        >
                            <p class="text-sm font-semibold uppercase tracking-wide text-emerald-700">Registration confirmed</p>
                            <p class="mt-2 text-lg font-bold text-slate-900">{{ confirmedRegistration.event_title || event.title }}</p>
                            <dl class="mt-4 space-y-3 text-sm">
                                <div>
                                    <dt class="text-slate-500">Registration number</dt>
                                    <dd class="font-mono font-semibold text-slate-900">{{ confirmedRegistration.registration_number }}</dd>
                                </div>
                                <div>
                                    <dt class="text-slate-500">Date</dt>
                                    <dd class="font-medium text-slate-800">{{ confirmedRegistration.event_date || formatDateTime(event.starts_at) }}</dd>
                                </div>
                                <div>
                                    <dt class="text-slate-500">Location</dt>
                                    <dd class="font-medium text-slate-800">{{ confirmedRegistration.event_location || event.location || 'TBA' }}</dd>
                                </div>
                            </dl>
                            <p class="mt-4 text-sm text-slate-600">
                                A confirmation email with these details has been sent to
                                <span class="font-semibold text-slate-900">{{ confirmedRegistration.registrant_email }}</span>.
                            </p>
                        </div>

                        <div v-if="!registrationOpen" class="mt-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                            Registration is currently closed.
                        </div>
                        <div v-else-if="isFull" class="mt-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                            This event is full.
                        </div>
                        <form v-else-if="!confirmedRegistration" class="mt-6 space-y-4" @submit.prevent="submitRegistration">
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
                                    <option value="non_member">Non-member</option>
                                    <option value="student">Student</option>
                                    <option value="institution">Institution</option>
                                </select>
                            </div>

                            <EventMembershipNumberPanel
                                v-if="isMemberTicket"
                                v-model="form.membership_number"
                                :auto-verified="autoVerifiedMembership"
                                :verified-member-name="verifiedMemberName"
                                :verified-tier-name="verifiedTierName"
                                @verified="onMembershipVerified"
                                @invalid="onMembershipInvalid"
                            />

                            <button
                                type="submit"
                                class="btn-institutional w-full"
                                :disabled="submitting || !membershipReady"
                            >
                                {{ submitting ? 'Processing…' : (isPaid ? 'Pay & Register' : 'Register') }}
                            </button>
                            <p v-if="event.visibility === 'members_only' && !isMemberAuthenticated" class="text-center text-sm text-text-secondary">
                                <RouterLink to="/member/login" class="font-semibold text-institutional hover:underline">Sign in</RouterLink>
                                to register as a member.
                            </p>
                        </form>
                    </aside>
                </div>
            </div>
        </section>
    </div>
</template>
