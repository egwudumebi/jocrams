<script setup>
import { computed, ref, onMounted } from 'vue';
import {
    ArrowDownTrayIcon,
    CheckBadgeIcon,
    ClipboardDocumentIcon,
    DocumentCheckIcon,
    IdentificationIcon,
    LinkIcon,
    ShieldCheckIcon,
} from '@heroicons/vue/24/outline';
import AdminAlert from '../../components/admin/AdminAlert.vue';
import AdminEmptyState from '../../components/admin/AdminEmptyState.vue';
import AdminPageIntro from '../../components/admin/AdminPageIntro.vue';
import AdminPanel from '../../components/admin/AdminPanel.vue';
import { useAuth } from '../../composables/useAuth';
import { extractApiError } from '../../utils/apiError';

const { getMemberClient, memberUser, fetchMemberProfile } = useAuth();

const credentials = ref([]);
const membershipCard = ref(null);
const loading = ref(true);
const requesting = ref(false);
const regenerating = ref(false);
const downloadingUuid = ref('');
const copiedUuid = ref('');
const message = ref('');
const error = ref('');

const hasActiveMembership = computed(() => memberUser.value?.member?.status === 'active');
const membershipCards = computed(() => credentials.value.filter((item) => item.type === 'membership_card' && !item.revoked_at));
const certificates = computed(() => credentials.value.filter((item) => item.type === 'certificate'));
const validCredentials = computed(() => credentials.value.filter((item) => !item.revoked_at));
const displayCredentials = computed(() => credentials.value.filter((item) => !item.revoked_at));

onMounted(async () => {
    await fetchMemberProfile();
    await load();
});

async function load() {
    loading.value = true;
    error.value = '';

    try {
        const requests = [getMemberClient().get('/credentials')];

        if (hasActiveMembership.value) {
            requests.push(getMemberClient().get('/membership-card'));
        }

        const [credentialsRes, cardRes] = await Promise.all(requests);
        credentials.value = credentialsRes.data.data || [];
        membershipCard.value = cardRes?.data || null;
    } catch (e) {
        error.value = extractApiError(e, 'Unable to load credentials.');
        credentials.value = [];
    } finally {
        loading.value = false;
    }
}

function typeLabel(type) {
    const map = {
        membership_card: 'Membership card',
        certificate: 'Certificate',
    };

    return map[type] || type.replaceAll('_', ' ');
}

function typeIcon(type) {
    return type === 'certificate' ? DocumentCheckIcon : IdentificationIcon;
}

function formatDate(value) {
    return value ? new Date(value).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' }) : '—';
}

function publicVerifyUrl(credential) {
    if (!credential?.verification_token) {
        return credential?.metadata?.verify_url || '';
    }

    return `${window.location.origin}/verify/${credential.verification_token}`;
}

function isValid(credential) {
    if (credential.revoked_at) {
        return false;
    }

    if (credential.expires_at) {
        return new Date(credential.expires_at) >= new Date();
    }

    return true;
}

function statusClass(credential) {
    return isValid(credential)
        ? 'bg-emerald-100 text-emerald-800'
        : 'bg-red-100 text-red-800';
}

async function copyVerifyLink(credential) {
    const url = publicVerifyUrl(credential);

    if (!url) {
        return;
    }

    try {
        await navigator.clipboard.writeText(url);
        copiedUuid.value = credential.uuid;
        message.value = 'Verification link copied to clipboard.';
        setTimeout(() => {
            if (copiedUuid.value === credential.uuid) {
                copiedUuid.value = '';
            }
        }, 2000);
    } catch {
        error.value = 'Unable to copy link. Please copy it manually from the verification page.';
    }
}

async function downloadCredential(credential) {
    downloadingUuid.value = credential.uuid;
    error.value = '';

    try {
        const response = await getMemberClient().get(`/credentials/${credential.uuid}/file`, {
            responseType: 'blob',
            headers: { Accept: 'application/pdf' },
        });

        const blob = new Blob([response.data], { type: 'application/pdf' });
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `${credential.title || 'credential'}.pdf`;
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(url);
        message.value = 'Download started.';
    } catch (e) {
        error.value = extractApiError(e, 'Unable to download credential.');
    } finally {
        downloadingUuid.value = '';
    }
}

async function requestCertificate() {
    requesting.value = true;
    message.value = '';
    error.value = '';

    try {
        await getMemberClient().post('/credentials/certificate', { title: 'Certificate of Membership' });
        message.value = 'Certificate requested successfully.';
        await load();
    } catch (e) {
        error.value = extractApiError(e, 'Unable to request certificate.');
    } finally {
        requesting.value = false;
    }
}

async function regenerateMembershipCard() {
    regenerating.value = true;
    message.value = '';
    error.value = '';

    try {
        await getMemberClient().post('/credentials/membership-card/regenerate');
        message.value = 'Membership card regenerated with the latest design.';
        await load();
    } catch (e) {
        error.value = extractApiError(e, 'Unable to regenerate membership card.');
    } finally {
        regenerating.value = false;
    }
}
</script>

<template>
    <div>
        <AdminPageIntro description="View, download, and share your official membership credentials and certificates.">
            <template #actions>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-if="hasActiveMembership && membershipCards.length"
                        type="button"
                        class="btn-secondary inline-flex items-center gap-2"
                        :disabled="regenerating"
                        @click="regenerateMembershipCard"
                    >
                        <IdentificationIcon class="size-4" />
                        {{ regenerating ? 'Regenerating…' : 'Refresh membership card' }}
                    </button>
                    <button
                        v-if="hasActiveMembership && !certificates.length"
                        type="button"
                        class="btn-institutional inline-flex items-center gap-2"
                        :disabled="requesting"
                        @click="requestCertificate"
                    >
                        <DocumentCheckIcon class="size-4" />
                        {{ requesting ? 'Requesting…' : 'Request certificate' }}
                    </button>
                </div>
            </template>
        </AdminPageIntro>

        <AdminAlert v-if="message" type="success">{{ message }}</AdminAlert>
        <AdminAlert v-if="error" type="error">{{ error }}</AdminAlert>

        <div class="grid gap-4 sm:grid-cols-3">
            <div class="card-modern p-5">
                <div class="flex items-center gap-4">
                    <span class="metric-icon !size-12 !rounded-xl">
                        <ShieldCheckIcon class="size-6" />
                    </span>
                    <div>
                        <p class="text-sm text-slate-500">Total credentials</p>
                        <p class="text-2xl font-bold text-slate-900">{{ loading ? '—' : credentials.length }}</p>
                    </div>
                </div>
            </div>
            <div class="card-modern p-5">
                <div class="flex items-center gap-4">
                    <span class="metric-icon !size-12 !rounded-xl">
                        <IdentificationIcon class="size-6" />
                    </span>
                    <div>
                        <p class="text-sm text-slate-500">Membership cards</p>
                        <p class="text-2xl font-bold text-slate-900">{{ loading ? '—' : membershipCards.length }}</p>
                    </div>
                </div>
            </div>
            <div class="card-modern p-5">
                <div class="flex items-center gap-4">
                    <span class="metric-icon !size-12 !rounded-xl">
                        <CheckBadgeIcon class="size-6" />
                    </span>
                    <div>
                        <p class="text-sm text-slate-500">Valid credentials</p>
                        <p class="text-2xl font-bold text-slate-900">{{ loading ? '—' : validCredentials.length }}</p>
                    </div>
                </div>
            </div>
        </div>

        <AdminPanel
            v-if="membershipCard || memberUser?.member"
            class="mt-6"
            title="Membership overview"
            description="Your active membership details and primary verification link."
        >
            <div class="grid gap-4 lg:grid-cols-[1.2fr_0.8fr]">
                <dl class="grid gap-3 sm:grid-cols-2">
                    <div class="rounded-xl border border-slate-100 bg-surface-muted/40 p-4">
                        <dt class="text-sm text-slate-500">Member name</dt>
                        <dd class="mt-1 font-semibold text-slate-900">{{ memberUser?.name || '—' }}</dd>
                    </div>
                    <div class="rounded-xl border border-slate-100 bg-surface-muted/40 p-4">
                        <dt class="text-sm text-slate-500">Membership number</dt>
                        <dd class="mt-1 font-semibold text-slate-900">{{ membershipCard?.membership_number || memberUser?.member?.membership_number || '—' }}</dd>
                    </div>
                    <div class="rounded-xl border border-slate-100 bg-surface-muted/40 p-4">
                        <dt class="text-sm text-slate-500">Tier</dt>
                        <dd class="mt-1 font-semibold text-slate-900">{{ membershipCard?.tier || memberUser?.member?.tier?.name || '—' }}</dd>
                    </div>
                    <div class="rounded-xl border border-slate-100 bg-surface-muted/40 p-4">
                        <dt class="text-sm text-slate-500">Expires</dt>
                        <dd class="mt-1 font-semibold text-slate-900">
                            {{ formatDate(membershipCard?.expires_at || memberUser?.member?.expires_at) }}
                        </dd>
                    </div>
                </dl>

                <div class="rounded-2xl border border-institutional/15 bg-gradient-to-br from-institutional/8 via-white to-white p-5">
                    <p class="text-sm font-semibold text-institutional">Digital membership card</p>
                    <p class="mt-2 text-sm text-slate-600">
                        Share your verification link so others can confirm your membership status online.
                    </p>
                    <div v-if="membershipCards[0]" class="mt-4 flex flex-wrap gap-2">
                        <button
                            type="button"
                            class="btn-secondary inline-flex items-center gap-2 !rounded-xl"
                            @click="copyVerifyLink(membershipCards[0])"
                        >
                            <LinkIcon class="size-4" />
                            {{ copiedUuid === membershipCards[0].uuid ? 'Copied!' : 'Copy verify link' }}
                        </button>
                        <a
                            :href="publicVerifyUrl(membershipCards[0])"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="btn-institutional inline-flex items-center gap-2 !rounded-xl"
                        >
                            Open verify page
                        </a>
                    </div>
                </div>
            </div>
        </AdminPanel>

        <AdminPanel class="mt-6" title="Your credentials" description="Download PDF copies and share verification links for each credential.">
            <div v-if="loading" class="flex justify-center py-16">
                <div class="size-10 animate-spin rounded-full border-2 border-institutional/20 border-t-institutional" />
            </div>

            <AdminEmptyState
                v-else-if="credentials.length === 0"
                title="No credentials issued yet"
                description="Credentials are generated when your membership is approved. If you recently joined, they may appear shortly."
            >
                <template #icon>
                    <ClipboardDocumentIcon class="size-6" />
                </template>
            </AdminEmptyState>

            <div v-else class="grid gap-5 lg:grid-cols-2">
                <article
                    v-for="credential in displayCredentials"
                    :key="credential.uuid"
                    class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm transition hover:border-institutional/20 hover:shadow-md"
                >
                    <div class="border-b border-slate-100 bg-gradient-to-r from-institutional/8 via-white to-white px-5 py-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <span class="flex size-11 items-center justify-center rounded-xl bg-institutional/10 text-institutional">
                                    <component :is="typeIcon(credential.type)" class="size-5" />
                                </span>
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wide text-institutional">
                                        {{ typeLabel(credential.type) }}
                                    </p>
                                    <h3 class="font-display text-lg font-bold text-slate-900">{{ credential.title }}</h3>
                                </div>
                            </div>
                            <span class="badge capitalize" :class="statusClass(credential)">
                                {{ isValid(credential) ? 'Valid' : 'Expired' }}
                            </span>
                        </div>
                    </div>

                    <div class="space-y-4 p-5">
                        <dl class="grid gap-3 text-sm sm:grid-cols-2">
                            <div>
                                <dt class="text-slate-500">Issued</dt>
                                <dd class="mt-1 font-medium text-slate-900">{{ formatDate(credential.issued_at) }}</dd>
                            </div>
                            <div>
                                <dt class="text-slate-500">Expires</dt>
                                <dd class="mt-1 font-medium text-slate-900">{{ formatDate(credential.expires_at) }}</dd>
                            </div>
                        </dl>

                        <div class="rounded-xl border border-slate-100 bg-surface-muted/50 px-4 py-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Verification link</p>
                            <p class="mt-1 break-all text-sm text-slate-700">{{ publicVerifyUrl(credential) }}</p>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <button
                                type="button"
                                class="btn-secondary inline-flex items-center gap-2 !rounded-xl"
                                @click="copyVerifyLink(credential)"
                            >
                                <LinkIcon class="size-4" />
                                {{ copiedUuid === credential.uuid ? 'Copied!' : 'Copy link' }}
                            </button>
                            <a
                                :href="publicVerifyUrl(credential)"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="btn-secondary inline-flex items-center gap-2 !rounded-xl"
                            >
                                Verify online
                            </a>
                            <button
                                type="button"
                                class="btn-institutional inline-flex items-center gap-2 !rounded-xl"
                                :disabled="downloadingUuid === credential.uuid"
                                @click="downloadCredential(credential)"
                            >
                                <ArrowDownTrayIcon class="size-4" />
                                {{ downloadingUuid === credential.uuid ? 'Preparing…' : 'Download PDF' }}
                            </button>
                        </div>
                    </div>
                </article>
            </div>
        </AdminPanel>
    </div>
</template>
