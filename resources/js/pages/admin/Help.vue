<script setup>
import { ref, onMounted, computed } from 'vue';
import { RouterLink } from 'vue-router';
import { EnvelopeIcon, LifebuoyIcon, PhoneIcon } from '@heroicons/vue/24/outline';
import AdminPageIntro from '../../components/admin/AdminPageIntro.vue';
import { useAuth } from '../../composables/useAuth';

const { getAdminClient } = useAuth();
const messages = ref([]);
const stats = ref({ new: 0, responded: 0, resolved: 0, total: 0 });
const loading = ref(true);
const saving = ref(false);
const message = ref('');
const error = ref('');
const statusFilter = ref('');
const search = ref('');
const selected = ref(null);
const responseText = ref('');

const supportItems = [
    {
        title: 'Member application issues',
        description: 'Review pending applications or contact the applicant from the approvals queue.',
        to: '/admin/approvals',
        action: 'Open Applications',
    },
    {
        title: 'Payment reconciliation',
        description: 'Mark pending dues as successful or export transactions for finance review.',
        to: '/admin/payments',
        action: 'Open Payments',
    },
    {
        title: 'Content publishing',
        description: 'Draft and publish news articles or review library uploads.',
        to: '/admin/content',
        action: 'Open Content',
    },
];

const statusTabs = [
    { value: '', label: 'All' },
    { value: 'new', label: 'New' },
    { value: 'responded', label: 'Responded' },
    { value: 'resolved', label: 'Resolved' },
];

const selectedMessage = computed(() => selected.value);

onMounted(load);

async function load() {
    loading.value = true;
    try {
        const { data } = await getAdminClient().get('/support/messages', {
            params: {
                status: statusFilter.value || undefined,
                search: search.value || undefined,
            },
        });
        messages.value = data.data || [];
        stats.value = data.stats || stats.value;

        if (selected.value) {
            selected.value = messages.value.find((item) => item.uuid === selected.value.uuid) || null;
        }
    } finally {
        loading.value = false;
    }
}

function selectItem(item) {
    selected.value = item;
    responseText.value = item.last_response || '';
    error.value = '';
    message.value = '';
}

function formatDate(value) {
    return value ? new Date(value).toLocaleString() : '—';
}

function sourceLabel(source) {
    const normalized = source?.value || source;
    return normalized === 'member' ? 'Member ticket' : 'Contact form';
}

async function sendResponse() {
    if (!selected.value || !responseText.value.trim()) {
        error.value = 'Enter a response before sending.';
        return;
    }

    saving.value = true;
    error.value = '';
    message.value = '';

    try {
        const { data } = await getAdminClient().post(`/support/messages/${selected.value.uuid}/respond`, {
            response: responseText.value,
        });
        message.value = data.message;
        selected.value = data.data;
        await load();
    } catch (err) {
        error.value = err.response?.data?.message || 'Unable to send response.';
    } finally {
        saving.value = false;
    }
}

async function markResolved() {
    if (!selected.value) return;

    saving.value = true;
    error.value = '';
    message.value = '';

    try {
        const { data } = await getAdminClient().post(`/support/messages/${selected.value.uuid}/resolve`);
        message.value = data.message;
        selected.value = data.data;
        await load();
    } catch (err) {
        error.value = err.response?.data?.message || 'Unable to resolve message.';
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <div>
        <AdminPageIntro description="Review contact form submissions and member support tickets. Respond and resolve inquiries from one inbox." />

        <p v-if="message" class="mb-4 text-sm text-green-600">{{ message }}</p>
        <p v-if="error" class="mb-4 text-sm text-red-600">{{ error }}</p>

        <div class="mb-6 grid gap-3 sm:grid-cols-4">
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-sm text-slate-500">New</p>
                <p class="mt-1 text-2xl font-bold text-slate-900">{{ stats.new }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-sm text-slate-500">Responded</p>
                <p class="mt-1 text-2xl font-bold text-slate-900">{{ stats.responded }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-sm text-slate-500">Resolved</p>
                <p class="mt-1 text-2xl font-bold text-slate-900">{{ stats.resolved }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-sm text-slate-500">Total</p>
                <p class="mt-1 text-2xl font-bold text-slate-900">{{ stats.total }}</p>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-5">
            <section class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm xl:col-span-2">
                <div class="border-b border-slate-100 px-5 py-4">
                    <h2 class="font-display text-lg font-bold text-slate-900">Support Inbox</h2>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button
                            v-for="tab in statusTabs"
                            :key="tab.value"
                            type="button"
                            class="rounded-lg px-3 py-1.5 text-sm"
                            :class="statusFilter === tab.value ? 'bg-brand-50 font-semibold text-institutional' : 'text-slate-500 hover:bg-slate-50'"
                            @click="statusFilter = tab.value; load()"
                        >
                            {{ tab.label }}
                        </button>
                    </div>
                    <input
                        v-model="search"
                        type="search"
                        placeholder="Search inbox..."
                        class="input mt-3 !rounded-xl !py-2"
                        @keyup.enter="load"
                    />
                </div>

                <div v-if="loading" class="px-5 py-10 text-center text-sm text-slate-500">Loading inbox...</div>
                <div v-else-if="!messages.length" class="px-5 py-10 text-center text-sm text-slate-500">No messages yet.</div>
                <ul v-else class="max-h-[32rem] divide-y divide-slate-100 overflow-y-auto">
                    <li
                        v-for="item in messages"
                        :key="item.uuid"
                        class="cursor-pointer px-5 py-4 transition hover:bg-slate-50"
                        :class="selected?.uuid === item.uuid ? 'bg-brand-50/70' : ''"
                        @click="selectItem(item)"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate font-medium text-slate-900">{{ item.subject }}</p>
                                <p class="mt-1 truncate text-sm text-slate-500">{{ item.name }} · {{ item.email }}</p>
                            </div>
                            <span class="shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-xs capitalize text-slate-600">
                                {{ item.status?.value || item.status }}
                            </span>
                        </div>
                        <p class="mt-2 text-xs text-slate-400">{{ sourceLabel(item.source) }} · {{ formatDate(item.created_at) }}</p>
                    </li>
                </ul>
            </section>

            <section class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm xl:col-span-3">
                <div v-if="!selectedMessage" class="flex h-full min-h-[24rem] items-center justify-center px-8 py-14 text-center text-slate-500">
                    Select a message to view details and respond.
                </div>
                <div v-else class="flex h-full flex-col">
                    <div class="border-b border-slate-100 px-5 py-4">
                        <h2 class="font-display text-lg font-bold text-slate-900">{{ selectedMessage.subject }}</h2>
                        <p class="mt-1 text-sm text-slate-500">
                            {{ selectedMessage.name }} · {{ selectedMessage.email }}
                            <span v-if="selectedMessage.phone"> · {{ selectedMessage.phone }}</span>
                        </p>
                    </div>
                    <div class="flex-1 space-y-4 px-5 py-5">
                        <div class="rounded-xl bg-slate-50 p-4 text-sm text-slate-700 whitespace-pre-wrap">{{ selectedMessage.message }}</div>
                        <div v-if="selectedMessage.last_response" class="rounded-xl border border-brand-100 bg-brand-50/50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-institutional">Your response</p>
                            <p class="mt-2 text-sm text-slate-700 whitespace-pre-wrap">{{ selectedMessage.last_response }}</p>
                            <p class="mt-2 text-xs text-slate-500">Sent {{ formatDate(selectedMessage.responded_at) }}</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">Reply</label>
                            <textarea
                                v-model="responseText"
                                rows="5"
                                class="input !rounded-xl"
                                placeholder="Write a response to the sender..."
                            />
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-3 border-t border-slate-100 px-5 py-4">
                        <button type="button" class="btn-primary !rounded-xl" :disabled="saving" @click="sendResponse">
                            {{ saving ? 'Saving...' : 'Send response' }}
                        </button>
                        <button
                            v-if="(selectedMessage.status?.value || selectedMessage.status) !== 'resolved'"
                            type="button"
                            class="btn-secondary !rounded-xl"
                            :disabled="saving"
                            @click="markResolved"
                        >
                            Mark resolved
                        </button>
                    </div>
                </div>
            </section>
        </div>

        <div class="mt-8 grid gap-6 lg:grid-cols-3">
            <section class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm lg:col-span-2">
                <h2 class="font-display text-lg font-bold text-slate-900">Common admin tasks</h2>
                <div class="mt-4 space-y-3">
                    <article v-for="item in supportItems" :key="item.title" class="rounded-xl border border-slate-100 bg-surface-muted/50 p-4">
                        <h3 class="font-semibold text-slate-900">{{ item.title }}</h3>
                        <p class="mt-1 text-sm text-text-secondary">{{ item.description }}</p>
                        <RouterLink :to="item.to" class="mt-3 inline-flex text-sm font-semibold text-institutional hover:underline">
                            {{ item.action }}
                        </RouterLink>
                    </article>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm">
                <div class="flex items-center gap-3">
                    <span class="flex size-11 items-center justify-center rounded-xl bg-brand-50 text-institutional">
                        <LifebuoyIcon class="size-6" />
                    </span>
                    <div>
                        <h2 class="font-display text-lg font-bold text-slate-900">Platform contact</h2>
                        <p class="text-sm text-text-secondary">Escalate platform issues</p>
                    </div>
                </div>
                <ul class="mt-5 space-y-4 text-sm text-slate-600">
                    <li class="flex items-center gap-2">
                        <EnvelopeIcon class="size-5 text-institutional" />
                        info@jocrams.test
                    </li>
                    <li class="flex items-center gap-2">
                        <PhoneIcon class="size-5 text-institutional" />
                        +234 800 JOCRAMS
                    </li>
                </ul>
            </section>
        </div>
    </div>
</template>
