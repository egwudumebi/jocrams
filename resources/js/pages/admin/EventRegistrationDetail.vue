<script setup>
import { computed, onMounted, ref } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import {
    ArrowDownTrayIcon,
    ArrowLeftIcon,
    CalendarDaysIcon,
    ClockIcon,
    CurrencyDollarIcon,
    GlobeAltIcon,
    MapPinIcon,
    PencilSquareIcon,
    TicketIcon,
    UserGroupIcon,
    VideoCameraIcon,
} from '@heroicons/vue/24/outline';
import AdminAlert from '../../components/admin/AdminAlert.vue';
import AdminEmptyState from '../../components/admin/AdminEmptyState.vue';
import AdminPanel from '../../components/admin/AdminPanel.vue';
import EventBannerThumb from '../../components/admin/EventBannerThumb.vue';
import { useAuth } from '../../composables/useAuth';
import { downloadBlobResponse } from '../../utils/download';

const route = useRoute();
const { getAdminClient } = useAuth();

const event = ref(null);
const registrations = ref([]);
const stats = ref(null);
const loading = ref(true);
const exporting = ref(false);
const exportError = ref('');
const loadError = ref('');
const message = ref('');
const error = ref('');
const verifyingUuid = ref('');

const eventUuid = computed(() => route.params.uuid);

onMounted(load);

async function load() {
    loading.value = true;
    loadError.value = '';

    try {
        const [eventResponse, registrationsResponse] = await Promise.all([
            getAdminClient().get(`/events/${eventUuid.value}`),
            getAdminClient().get(`/events/${eventUuid.value}/registrations`),
        ]);

        event.value = eventResponse.data.data;
        registrations.value = registrationsResponse.data.data?.data || [];
        stats.value = registrationsResponse.data.stats || null;
    } catch (e) {
        loadError.value = e.response?.data?.message || 'Unable to load event details.';
    } finally {
        loading.value = false;
    }
}

async function exportCsv() {
    exportError.value = '';
    exporting.value = true;

    try {
        const response = await getAdminClient().get(`/events/${eventUuid.value}/registrations/export`, {
            responseType: 'blob',
        });

        const slug = event.value?.title
            ? event.value.title.replace(/[^a-z0-9]+/gi, '-').replace(/^-|-$/g, '').toLowerCase()
            : eventUuid.value;

        await downloadBlobResponse(response, `${slug}-registrations.csv`);
    } catch (e) {
        if (e.response?.data instanceof Blob) {
            try {
                const payload = JSON.parse(await e.response.data.text());
                exportError.value = payload.message || 'Unable to export registrations.';
                return;
            } catch {
                // Fall through.
            }
        }

        exportError.value = e.message || 'Unable to export registrations.';
    } finally {
        exporting.value = false;
    }
}

function formatDate(value) {
    return value ? new Date(value).toLocaleDateString(undefined, {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    }) : '—';
}

function formatDateTime(value) {
    return value ? new Date(value).toLocaleString(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    }) : '—';
}

function formatCurrency(value) {
    return `NGN ${Number(value || 0).toLocaleString()}`;
}

function statusClass(status) {
    if (status === 'published') return 'bg-emerald-100 text-emerald-800';
    if (status === 'draft') return 'bg-amber-100 text-amber-800';
    return 'bg-slate-100 text-slate-700';
}

function registrationStatusClass(status) {
    if (status === 'confirmed') return 'bg-emerald-100 text-emerald-800';
    if (status === 'pending') return 'bg-amber-100 text-amber-800';
    if (status === 'cancelled') return 'bg-red-100 text-red-800';
    return 'bg-slate-100 text-slate-700';
}

function paymentForRegistration(registration) {
    return registration.payment || registration.latest_payment || null;
}

function paymentStatusClass(status) {
    if (status === 'successful') return 'bg-emerald-100 text-emerald-800';
    if (status === 'processing' || status === 'pending') return 'bg-amber-100 text-amber-800';
    if (status === 'failed') return 'bg-red-100 text-red-800';
    return 'bg-slate-100 text-slate-700';
}

function canVerifyRegistration(registration) {
    const payment = paymentForRegistration(registration);

    return registration.status === 'pending'
        && payment
        && payment.status !== 'successful';
}

async function verifyRegistrationPayment(registration) {
    verifyingUuid.value = registration.uuid;
    message.value = '';
    error.value = '';

    try {
        const { data } = await getAdminClient().post(
            `/events/${eventUuid.value}/registrations/${registration.uuid}/verify-payment`,
        );
        message.value = data.message || 'Payment verified.';
        await load();
    } catch (e) {
        error.value = e.response?.data?.message || 'Unable to verify payment.';
    } finally {
        verifyingUuid.value = '';
    }
}

function visibilityLabel(value) {
    if (value === 'members_only') return 'Members only';
    return 'Public';
}

const totalRegistrants = computed(() => stats.value?.total ?? registrations.value.length);
</script>

<template>
    <div>
        <RouterLink
            to="/admin/event-registrations"
            class="inline-flex items-center gap-2 text-sm font-semibold text-institutional hover:underline"
        >
            <ArrowLeftIcon class="size-4" />
            Back to registrations
        </RouterLink>

        <div v-if="loading" class="empty-state mt-6 bg-white">Loading event details...</div>

        <AdminEmptyState
            v-else-if="loadError || !event"
            class="mt-6"
            title="Event not found"
            :description="loadError || 'This event may have been removed or the link is invalid.'"
        >
            <template #action>
                <RouterLink to="/admin/event-registrations" class="btn-primary !rounded-xl">
                    Return to list
                </RouterLink>
            </template>
        </AdminEmptyState>

        <template v-else>
            <AdminAlert v-if="exportError" type="error" class="mt-6">{{ exportError }}</AdminAlert>
            <AdminAlert v-if="message" type="success" class="mt-6">{{ message }}</AdminAlert>
            <AdminAlert v-if="error" type="error" class="mt-6">{{ error }}</AdminAlert>

            <section class="admin-content-stretch mt-6">
                <div class="overflow-hidden rounded-none border-y border-slate-200/80 bg-white md:rounded-2xl md:border">
                    <EventBannerThumb :url="event.banner_url" :title="event.title" variant="hero" />

                    <div class="space-y-5 p-5 sm:p-6 lg:p-8">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="badge capitalize" :class="statusClass(event.status)">{{ event.status }}</span>
                                    <span class="badge bg-slate-100 capitalize text-slate-700">{{ visibilityLabel(event.visibility?.value || event.visibility) }}</span>
                                </div>
                                <h1 class="mt-3 font-display text-2xl font-bold text-slate-900 sm:text-3xl">{{ event.title }}</h1>
                                <div
                                    v-if="event.description"
                                    class="prose prose-sm mt-3 max-w-3xl text-slate-600 [&_a]:text-brand-600 [&_a]:underline [&_b]:font-semibold [&_h3]:font-semibold [&_i]:italic [&_ol]:mb-2 [&_ol]:ml-5 [&_ol]:list-decimal [&_p]:mb-2 [&_strong]:font-semibold [&_u]:underline [&_ul]:mb-2 [&_ul]:ml-5 [&_ul]:list-disc"
                                    v-html="event.description"
                                />
                            </div>

                            <div class="flex shrink-0 flex-col gap-2 sm:flex-row lg:flex-col xl:flex-row">
                                <RouterLink
                                    :to="{ name: 'admin.events', query: { edit: event.uuid } }"
                                    class="btn-secondary inline-flex items-center justify-center gap-2 !rounded-xl"
                                >
                                    <PencilSquareIcon class="size-4" />
                                    Edit event
                                </RouterLink>
                                <button
                                    type="button"
                                    class="btn-institutional inline-flex items-center justify-center gap-2 !rounded-xl"
                                    :disabled="exporting"
                                    @click="exportCsv"
                                >
                                    <ArrowDownTrayIcon class="size-4" />
                                    {{ exporting ? 'Exporting…' : 'Export CSV' }}
                                </button>
                            </div>
                        </div>

                        <div class="admin-stat-grid">
                            <div class="admin-stat-card">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total registrants</p>
                                <p class="mt-2 flex items-center gap-2 font-display text-2xl font-bold text-slate-900">
                                    <UserGroupIcon class="size-6 text-institutional" />
                                    {{ totalRegistrants }}
                                </p>
                            </div>
                            <div class="admin-stat-card">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Confirmed</p>
                                <p class="mt-2 font-display text-2xl font-bold text-emerald-700">{{ stats?.confirmed ?? 0 }}</p>
                            </div>
                            <div class="admin-stat-card">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Pending</p>
                                <p class="mt-2 font-display text-2xl font-bold text-amber-700">{{ stats?.pending ?? 0 }}</p>
                            </div>
                            <div class="admin-stat-card">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Revenue</p>
                                <p class="mt-2 font-display text-2xl font-bold text-institutional">{{ formatCurrency(stats?.revenue) }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <div class="mt-6 grid gap-6 xl:grid-cols-[1.4fr_1fr]">
                <div class="space-y-6">
                    <AdminPanel v-if="event.body" title="About this event">
                        <div
                            class="prose prose-sm max-w-none text-slate-700 [&_a]:text-brand-600 [&_a]:underline [&_b]:font-semibold [&_h3]:font-semibold [&_i]:italic [&_ol]:mb-2 [&_ol]:ml-5 [&_ol]:list-decimal [&_p]:mb-2 [&_strong]:font-semibold [&_u]:underline [&_ul]:mb-2 [&_ul]:ml-5 [&_ul]:list-disc"
                            v-html="event.body"
                        />
                    </AdminPanel>

                    <AdminPanel v-if="event.sessions?.length" title="Sessions">
                        <ol class="space-y-3">
                            <li
                                v-for="session in event.sessions"
                                :key="session.id"
                                class="flex gap-4 rounded-xl border border-slate-100 bg-slate-50/60 p-4"
                            >
                                <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-white text-institutional ring-1 ring-slate-200">
                                    <ClockIcon class="size-5" />
                                </div>
                                <div class="min-w-0">
                                    <p class="font-medium text-slate-900">{{ session.title }}</p>
                                    <p class="mt-1 text-sm text-slate-500">
                                        {{ formatDateTime(session.starts_at) }}
                                        <span v-if="session.ends_at"> · {{ formatDateTime(session.ends_at) }}</span>
                                    </p>
                                </div>
                            </li>
                        </ol>
                    </AdminPanel>

                    <AdminPanel title="Registrants">
                        <template #actions>
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-50 px-3 py-1 text-sm font-semibold text-institutional ring-1 ring-brand-100">
                                <TicketIcon class="size-4" />
                                {{ registrations.length }} shown
                            </span>
                        </template>

                        <AdminEmptyState
                            v-if="!registrations.length"
                            title="No registrants yet"
                            description="Registrations will appear here once people sign up for this event."
                        />

                        <div v-else class="overflow-x-auto -mx-4 px-4 sm:mx-0 sm:px-0">
                            <table class="admin-table min-w-[960px]">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Type</th>
                                        <th>Status</th>
                                        <th>Payment</th>
                                        <th>Reference</th>
                                        <th>Registration #</th>
                                        <th class="text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="registration in registrations" :key="registration.uuid">
                                        <td class="font-medium text-slate-900">{{ registration.registrant_name }}</td>
                                        <td class="text-slate-600">{{ registration.registrant_email }}</td>
                                        <td class="capitalize text-slate-600">{{ registration.member_type || '—' }}</td>
                                        <td>
                                            <span class="badge capitalize" :class="registrationStatusClass(registration.status)">
                                                {{ registration.status }}
                                            </span>
                                        </td>
                                        <td>
                                            <template v-if="paymentForRegistration(registration)">
                                                <span class="badge capitalize" :class="paymentStatusClass(paymentForRegistration(registration).status)">
                                                    {{ paymentForRegistration(registration).status }}
                                                </span>
                                                <p class="mt-1 text-xs text-slate-500">
                                                    NGN {{ Number(paymentForRegistration(registration).amount || 0).toLocaleString() }}
                                                </p>
                                            </template>
                                            <span v-else class="text-xs text-slate-400">No payment</span>
                                        </td>
                                        <td class="font-mono text-xs text-slate-600">
                                            {{ paymentForRegistration(registration)?.reference || '—' }}
                                        </td>
                                        <td class="font-mono text-xs text-slate-600">{{ registration.registration_number }}</td>
                                        <td class="text-right">
                                            <button
                                                v-if="canVerifyRegistration(registration)"
                                                type="button"
                                                class="btn-secondary !rounded-lg !px-3 !py-1.5 text-xs"
                                                :disabled="verifyingUuid === registration.uuid"
                                                @click="verifyRegistrationPayment(registration)"
                                            >
                                                {{ verifyingUuid === registration.uuid ? 'Verifying…' : 'Confirm payment' }}
                                            </button>
                                            <span v-else-if="registration.status === 'confirmed'" class="text-xs text-emerald-700">Confirmed</span>
                                            <span v-else class="text-xs text-slate-400">—</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </AdminPanel>
                </div>

                <aside class="space-y-6">
                    <AdminPanel title="Schedule & location">
                        <dl class="space-y-4 text-sm">
                            <div class="flex gap-3">
                                <CalendarDaysIcon class="mt-0.5 size-5 shrink-0 text-slate-400" />
                                <div>
                                    <dt class="font-medium text-slate-900">Starts</dt>
                                    <dd class="mt-0.5 text-slate-600">{{ formatDateTime(event.starts_at) }}</dd>
                                </div>
                            </div>
                            <div v-if="event.ends_at" class="flex gap-3">
                                <ClockIcon class="mt-0.5 size-5 shrink-0 text-slate-400" />
                                <div>
                                    <dt class="font-medium text-slate-900">Ends</dt>
                                    <dd class="mt-0.5 text-slate-600">{{ formatDateTime(event.ends_at) }}</dd>
                                </div>
                            </div>
                            <div v-if="event.location" class="flex gap-3">
                                <MapPinIcon class="mt-0.5 size-5 shrink-0 text-slate-400" />
                                <div>
                                    <dt class="font-medium text-slate-900">Location</dt>
                                    <dd class="mt-0.5 text-slate-600">{{ event.location }}</dd>
                                </div>
                            </div>
                            <div v-if="event.virtual_url" class="flex gap-3">
                                <VideoCameraIcon class="mt-0.5 size-5 shrink-0 text-slate-400" />
                                <div class="min-w-0">
                                    <dt class="font-medium text-slate-900">Virtual link</dt>
                                    <dd class="mt-0.5 truncate">
                                        <a :href="event.virtual_url" target="_blank" rel="noopener noreferrer" class="text-institutional hover:underline">
                                            {{ event.virtual_url }}
                                        </a>
                                    </dd>
                                </div>
                            </div>
                        </dl>
                    </AdminPanel>

                    <AdminPanel title="Registration window">
                        <dl class="space-y-3 text-sm">
                            <div>
                                <dt class="font-medium text-slate-900">Opens</dt>
                                <dd class="mt-0.5 text-slate-600">{{ formatDateTime(event.registration_opens_at) }}</dd>
                            </div>
                            <div>
                                <dt class="font-medium text-slate-900">Closes</dt>
                                <dd class="mt-0.5 text-slate-600">{{ formatDateTime(event.registration_closes_at) }}</dd>
                            </div>
                        </dl>
                    </AdminPanel>

                    <AdminPanel title="Pricing & capacity">
                        <dl class="space-y-3 text-sm">
                            <div class="flex gap-3">
                                <CurrencyDollarIcon class="mt-0.5 size-5 shrink-0 text-slate-400" />
                                <div>
                                    <dt class="font-medium text-slate-900">Base fee</dt>
                                    <dd class="mt-0.5 text-slate-600">{{ formatCurrency(event.fee) }}</dd>
                                </div>
                            </div>
                            <div class="flex gap-3">
                                <UserGroupIcon class="mt-0.5 size-5 shrink-0 text-slate-400" />
                                <div>
                                    <dt class="font-medium text-slate-900">Capacity</dt>
                                    <dd class="mt-0.5 text-slate-600">{{ event.max_attendees || 'Unlimited' }}</dd>
                                </div>
                            </div>
                            <div class="flex gap-3">
                                <GlobeAltIcon class="mt-0.5 size-5 shrink-0 text-slate-400" />
                                <div>
                                    <dt class="font-medium text-slate-900">Visibility</dt>
                                    <dd class="mt-0.5 text-slate-600">{{ visibilityLabel(event.visibility?.value || event.visibility) }}</dd>
                                </div>
                            </div>
                        </dl>

                        <div v-if="event.pricing_tiers?.length" class="mt-5 border-t border-slate-100 pt-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Pricing tiers</p>
                            <ul class="mt-3 space-y-2">
                                <li
                                    v-for="tier in event.pricing_tiers"
                                    :key="tier.id"
                                    class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2 text-sm"
                                >
                                    <span class="capitalize text-slate-700">{{ tier.category.replace('_', ' ') }}</span>
                                    <span class="font-semibold text-slate-900">{{ formatCurrency(tier.fee) }}</span>
                                </li>
                            </ul>
                        </div>
                    </AdminPanel>
                </aside>
            </div>
        </template>
    </div>
</template>
