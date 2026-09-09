<script setup>
import { computed, ref, onMounted } from 'vue';
import {
    ChatBubbleLeftRightIcon,
    CheckCircleIcon,
    ClockIcon,
    EnvelopeIcon,
    LifebuoyIcon,
    MagnifyingGlassIcon,
    PaperAirplaneIcon,
    PhoneIcon,
    UserCircleIcon,
} from '@heroicons/vue/24/outline';
import AdminAlert from '../../components/admin/AdminAlert.vue';
import AdminEmptyState from '../../components/admin/AdminEmptyState.vue';
import AdminPanel from '../../components/admin/AdminPanel.vue';
import { useAuth } from '../../composables/useAuth';
import { extractApiError } from '../../utils/apiError';

const { getMemberClient, fetchMemberProfile, memberUser } = useAuth();

const tickets = ref([]);
const loading = ref(true);
const saving = ref(false);
const message = ref('');
const error = ref('');
const selected = ref(null);
const search = ref('');
const loadingDetail = ref(false);

const categoryOptions = [
    'General inquiry',
    'Membership',
    'Payments & billing',
    'Events',
    'Journal & submissions',
    'Credentials',
    'Technical issue',
    'Other',
];

const form = ref({
    name: '',
    email: '',
    phone: '',
    subject: '',
    message: '',
    category: '',
});

const filteredTickets = computed(() => {
    const query = search.value.trim().toLowerCase();

    if (!query) {
        return tickets.value;
    }

    return tickets.value.filter((ticket) =>
        ticket.subject.toLowerCase().includes(query)
        || (ticket.category ?? '').toLowerCase().includes(query)
        || (ticket.message ?? '').toLowerCase().includes(query),
    );
});

const openCount = computed(() =>
    tickets.value.filter((ticket) => statusValue(ticket.status) === 'new').length,
);
const respondedCount = computed(() =>
    tickets.value.filter((ticket) => statusValue(ticket.status) === 'responded').length,
);
const resolvedCount = computed(() =>
    tickets.value.filter((ticket) => statusValue(ticket.status) === 'resolved').length,
);

const messageLength = computed(() => form.value.message.length);

const canSubmit = computed(() =>
    form.value.name.trim()
    && form.value.email.trim()
    && form.value.subject.trim()
    && form.value.message.trim()
    && !saving.value,
);

onMounted(async () => {
    await fetchMemberProfile();
    form.value.name = memberUser.value?.name || '';
    form.value.email = memberUser.value?.email || '';
    await load();
});

function statusValue(status) {
    return status?.value || status || 'new';
}

function statusLabel(status) {
    const value = statusValue(status);

    return {
        new: 'Awaiting response',
        responded: 'Team replied',
        resolved: 'Resolved',
    }[value] || value;
}

function statusClass(status) {
    const value = statusValue(status);

    if (value === 'resolved') {
        return 'bg-emerald-50 text-emerald-700 ring-emerald-600/20';
    }

    if (value === 'responded') {
        return 'bg-brand-50 text-brand-700 ring-brand-600/20';
    }

    return 'bg-amber-50 text-amber-700 ring-amber-600/20';
}

function formatDate(value) {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });
}

async function load() {
    loading.value = true;

    try {
        const { data } = await getMemberClient().get('/support/messages');
        tickets.value = data.data || [];
    } finally {
        loading.value = false;
    }
}

async function submit() {
    message.value = '';
    error.value = '';
    saving.value = true;

    try {
        await getMemberClient().post('/support/messages', form.value);
        message.value = 'Your support ticket was submitted. Our team will respond by email.';
        form.value = {
            ...form.value,
            subject: '',
            message: '',
            category: '',
            phone: '',
        };
        await load();
    } catch (err) {
        error.value = extractApiError(err, 'Unable to submit ticket.');
    } finally {
        saving.value = false;
    }
}

async function openTicket(ticket) {
    if (selected.value?.uuid === ticket.uuid) {
        selected.value = null;
        return;
    }

    loadingDetail.value = true;

    try {
        const { data } = await getMemberClient().get(`/support/messages/${ticket.uuid}`);
        selected.value = data.data;
    } catch {
        selected.value = ticket;
    } finally {
        loadingDetail.value = false;
    }
}
</script>

<template>
    <div class="pb-8">
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-gradient-to-br from-institutional/5 via-white to-brand-50/30">
            <div class="flex flex-col gap-5 p-6 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-start gap-4">
                    <span class="flex size-14 shrink-0 items-center justify-center rounded-2xl bg-institutional/10 text-institutional shadow-sm">
                        <LifebuoyIcon class="size-7" />
                    </span>
                    <div>
                        <h1 class="text-xl font-bold text-slate-900 sm:text-2xl">Support center</h1>
                        <p class="mt-1 max-w-2xl text-sm leading-relaxed text-slate-600">
                            Submit a ticket for membership, payments, events, or technical help. Track replies from the association team here.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="card-modern p-5">
                <div class="flex items-center gap-4">
                    <span class="metric-icon !size-12 !rounded-xl">
                        <ChatBubbleLeftRightIcon class="size-6" />
                    </span>
                    <div>
                        <p class="text-sm text-slate-500">Total tickets</p>
                        <p class="text-2xl font-bold text-slate-900">{{ loading ? '—' : tickets.length }}</p>
                    </div>
                </div>
            </div>
            <div class="card-modern p-5">
                <div class="flex items-center gap-4">
                    <span class="metric-icon !size-12 !rounded-xl">
                        <ClockIcon class="size-6" />
                    </span>
                    <div>
                        <p class="text-sm text-slate-500">Awaiting response</p>
                        <p class="text-2xl font-bold text-slate-900">{{ loading ? '—' : openCount }}</p>
                    </div>
                </div>
            </div>
            <div class="card-modern p-5">
                <div class="flex items-center gap-4">
                    <span class="metric-icon !size-12 !rounded-xl">
                        <EnvelopeIcon class="size-6" />
                    </span>
                    <div>
                        <p class="text-sm text-slate-500">Team replied</p>
                        <p class="text-2xl font-bold text-slate-900">{{ loading ? '—' : respondedCount }}</p>
                    </div>
                </div>
            </div>
            <div class="card-modern p-5">
                <div class="flex items-center gap-4">
                    <span class="metric-icon !size-12 !rounded-xl">
                        <CheckCircleIcon class="size-6" />
                    </span>
                    <div>
                        <p class="text-sm text-slate-500">Resolved</p>
                        <p class="text-2xl font-bold text-slate-900">{{ loading ? '—' : resolvedCount }}</p>
                    </div>
                </div>
            </div>
        </div>

        <AdminAlert v-if="message" type="success" class="mt-6">{{ message }}</AdminAlert>
        <AdminAlert v-if="error" type="error" class="mt-6">{{ error }}</AdminAlert>

        <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_380px] xl:items-start">
            <AdminPanel
                title="New support ticket"
                description="Tell us what you need help with. Include as much detail as possible so we can assist you faster."
            >
                <form class="space-y-6" @submit.prevent="submit">
                    <div>
                        <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Your contact details</p>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="label">Full name</label>
                                <div class="relative">
                                    <UserCircleIcon class="pointer-events-none absolute left-3 top-1/2 size-5 -translate-y-1/2 text-slate-400" />
                                    <input v-model="form.name" required class="input !rounded-xl !pl-10" />
                                </div>
                            </div>
                            <div>
                                <label class="label">Email address</label>
                                <div class="relative">
                                    <EnvelopeIcon class="pointer-events-none absolute left-3 top-1/2 size-5 -translate-y-1/2 text-slate-400" />
                                    <input v-model="form.email" type="email" required class="input !rounded-xl !pl-10" />
                                </div>
                            </div>
                            <div class="sm:col-span-2 sm:max-w-md">
                                <label class="label">Phone <span class="font-normal text-slate-400">(optional)</span></label>
                                <div class="relative">
                                    <PhoneIcon class="pointer-events-none absolute left-3 top-1/2 size-5 -translate-y-1/2 text-slate-400" />
                                    <input v-model="form.phone" class="input !rounded-xl !pl-10" placeholder="+234 …" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Ticket details</p>
                        <div class="space-y-4">
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="label">Category</label>
                                    <select v-model="form.category" class="input !rounded-xl">
                                        <option value="">Select a topic</option>
                                        <option v-for="option in categoryOptions" :key="option" :value="option">
                                            {{ option }}
                                        </option>
                                    </select>
                                </div>
                                <div>
                                    <label class="label">Subject</label>
                                    <input v-model="form.subject" required class="input !rounded-xl" placeholder="Brief summary of your issue" />
                                </div>
                            </div>
                            <div>
                                <label class="label">Description</label>
                                <textarea
                                    v-model="form.message"
                                    required
                                    rows="6"
                                    maxlength="5000"
                                    class="input !rounded-xl"
                                    placeholder="Describe what happened, any error messages, and what you were trying to do…"
                                />
                                <p class="mt-1.5 text-right text-xs tabular-nums text-slate-400">{{ messageLength }} / 5000</p>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn-institutional inline-flex items-center gap-2 !rounded-xl" :disabled="!canSubmit">
                        <PaperAirplaneIcon class="size-4" />
                        {{ saving ? 'Submitting…' : 'Submit ticket' }}
                    </button>
                </form>
            </AdminPanel>

            <aside class="space-y-4 xl:sticky xl:top-6">
                <AdminPanel title="My tickets" description="Select a ticket to read the full thread and team responses.">
                    <div class="mb-4">
                        <div class="relative">
                            <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                            <input
                                v-model="search"
                                type="search"
                                placeholder="Search tickets…"
                                class="input !rounded-xl !py-2.5 !pl-10"
                                :disabled="loading || !tickets.length"
                            />
                        </div>
                    </div>

                    <div v-if="loading" class="flex justify-center py-12">
                        <div class="size-8 animate-spin rounded-full border-2 border-institutional/20 border-t-institutional" />
                    </div>

                    <AdminEmptyState
                        v-else-if="!tickets.length"
                        title="No support tickets yet"
                        description="When you submit a ticket, it will appear here with status updates and team replies."
                    >
                        <template #icon>
                            <LifebuoyIcon class="size-6" />
                        </template>
                    </AdminEmptyState>

                    <AdminEmptyState
                        v-else-if="!filteredTickets.length"
                        title="No matching tickets"
                        description="Try a different search term."
                    >
                        <template #icon>
                            <MagnifyingGlassIcon class="size-6" />
                        </template>
                        <template #action>
                            <button type="button" class="btn-secondary !rounded-xl" @click="search = ''">Clear search</button>
                        </template>
                    </AdminEmptyState>

                    <ul v-else class="-mx-4 divide-y divide-slate-100 sm:-mx-5">
                        <li v-for="ticket in filteredTickets" :key="ticket.uuid">
                            <button
                                type="button"
                                class="block w-full px-4 py-4 text-left transition hover:bg-slate-50 sm:px-5"
                                :class="selected?.uuid === ticket.uuid ? 'bg-brand-50/60' : ''"
                                @click="openTicket(ticket)"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="truncate font-medium text-slate-900">{{ ticket.subject }}</p>
                                        <p v-if="ticket.category" class="mt-0.5 text-xs text-slate-500">{{ ticket.category }}</p>
                                        <p class="mt-1 text-xs text-slate-400">{{ formatDate(ticket.created_at) }}</p>
                                    </div>
                                    <span
                                        class="inline-flex shrink-0 rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide ring-1 ring-inset"
                                        :class="statusClass(ticket.status)"
                                    >
                                        {{ statusLabel(ticket.status) }}
                                    </span>
                                </div>
                            </button>
                        </li>
                    </ul>
                </AdminPanel>

                <div v-if="selected" class="card-modern overflow-hidden">
                    <div class="border-b border-slate-100 bg-slate-50/80 px-5 py-3">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="font-semibold text-slate-900">{{ selected.subject }}</p>
                                <p class="mt-0.5 text-xs text-slate-500">{{ formatDate(selected.created_at) }}</p>
                            </div>
                            <span
                                class="inline-flex shrink-0 rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide ring-1 ring-inset"
                                :class="statusClass(selected.status)"
                            >
                                {{ statusLabel(selected.status) }}
                            </span>
                        </div>
                    </div>

                    <div v-if="loadingDetail" class="flex justify-center py-8">
                        <div class="size-6 animate-spin rounded-full border-2 border-institutional/20 border-t-institutional" />
                    </div>

                    <div v-else class="space-y-4 p-5">
                        <div class="rounded-xl bg-slate-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Your message</p>
                            <p class="mt-2 whitespace-pre-wrap text-sm leading-relaxed text-slate-700">{{ selected.message }}</p>
                        </div>

                        <div v-if="selected.last_response" class="rounded-xl border border-brand-200 bg-brand-50/50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-institutional">Team response</p>
                            <p class="mt-2 whitespace-pre-wrap text-sm leading-relaxed text-slate-700">{{ selected.last_response }}</p>
                            <p class="mt-3 text-xs text-slate-500">Replied {{ formatDate(selected.responded_at) }}</p>
                        </div>

                        <p v-else class="text-sm text-slate-500">
                            Our team has not replied yet. You will receive an email when there is an update.
                        </p>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</template>
