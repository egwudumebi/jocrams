<script setup>
import { computed, ref, onMounted } from 'vue';
import {
    ArrowDownTrayIcon,
    ArrowTopRightOnSquareIcon,
    ClipboardDocumentIcon,
    DocumentCheckIcon,
    EnvelopeIcon,
    IdentificationIcon,
    LinkIcon,
} from '@heroicons/vue/24/outline';
import AdminAlert from '../../components/admin/AdminAlert.vue';
import AdminEmptyState from '../../components/admin/AdminEmptyState.vue';
import AdminPageIntro from '../../components/admin/AdminPageIntro.vue';
import AdminPanel from '../../components/admin/AdminPanel.vue';
import MembershipIdCard from '../../components/membership/MembershipIdCard.vue';
import { useAuth } from '../../composables/useAuth';
import { useSiteBranding } from '../../composables/useSiteBranding';
import { extractApiError } from '../../utils/apiError';

const { getMemberClient, memberUser, fetchMemberProfile } = useAuth();
const { branding, loadBranding } = useSiteBranding();

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
const certificates = computed(() => credentials.value.filter((item) => item.type === 'certificate' && !item.revoked_at));
const displayCredentials = computed(() => {
    const order = { membership_card: 0, certificate: 1, welcome_letter: 2 };
    return credentials.value
        .filter((item) => !item.revoked_at)
        .slice()
        .sort((a, b) => (order[a.type] ?? 9) - (order[b.type] ?? 9));
});

const packComplete = computed(() => (
    membershipCards.value.length > 0
    && certificates.value.length > 0
    && credentials.value.some((item) => item.type === 'welcome_letter' && !item.revoked_at)
));

const cardPreviewMember = computed(() => ({
    name: memberUser.value?.name,
    email: memberUser.value?.email,
    membership_number: membershipCard.value?.membership_number || memberUser.value?.member?.membership_number,
    expires_at: membershipCard.value?.expires_at || memberUser.value?.member?.expires_at,
    organization: membershipCard.value?.organization
        || (String(memberUser.value?.member?.membership_number || '').startsWith('SICAMA') ? 'sicama' : 'jocrams'),
    card_role: membershipCard.value?.card_role || memberUser.value?.member?.tier?.name || 'Member',
    tier: memberUser.value?.member?.tier || { name: membershipCard.value?.tier },
    profile_image: memberUser.value?.profile?.profile_image || memberUser.value?.profile_image || null,
}));

const logoUrl = computed(() => branding.value?.parent_org?.logo_url || '/images/sicama-logo.png');

onMounted(async () => {
    await Promise.all([fetchMemberProfile(), loadBranding()]);
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

function shortLabel(type) {
    const map = {
        membership_card: 'ID card',
        certificate: 'Certificate',
        welcome_letter: 'Welcome letter',
    };

    return map[type] || type.replaceAll('_', ' ');
}

function typeIcon(type) {
    if (type === 'welcome_letter') {
        return EnvelopeIcon;
    }

    if (type === 'certificate') {
        return DocumentCheckIcon;
    }

    return IdentificationIcon;
}

function typeBlurb(type) {
    const map = {
        membership_card: 'Official membership ID with photo and QR verification.',
        certificate: 'Landscape membership certificate matching the official SICAMA design.',
        welcome_letter: 'Signed welcome letter from the association President.',
    };

    return map[type] || 'Official membership document.';
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

function truncatedToken(credential) {
    const token = credential?.verification_token || '';
    if (token.length < 12) {
        return token || '—';
    }

    return `${token.slice(0, 6)}…${token.slice(-4)}`;
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

async function copyVerifyLink(credential) {
    const url = publicVerifyUrl(credential);

    if (!url) {
        return;
    }

    try {
        await navigator.clipboard.writeText(url);
        copiedUuid.value = credential.uuid;
        message.value = 'Verification link copied.';
        setTimeout(() => {
            if (copiedUuid.value === credential.uuid) {
                copiedUuid.value = '';
            }
        }, 2000);
    } catch {
        error.value = 'Unable to copy link. Open “Verify online” and copy from the address bar.';
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
        message.value = `${shortLabel(credential.type)} download started.`;
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
    <div class="space-y-6">
        <AdminPageIntro description="Your SICAMA ID card, certificate, and welcome letter appear here after membership payment. Journal submission does not require these documents.">
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
                        {{ regenerating ? 'Refreshing…' : 'Refresh ID card' }}
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

        <div
            v-if="!loading && displayCredentials.length"
            class="flex flex-wrap items-center gap-3 rounded-2xl border border-slate-200/80 bg-white px-4 py-3 shadow-sm"
        >
            <span
                class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold"
                :class="packComplete ? 'bg-emerald-50 text-emerald-800' : 'bg-amber-50 text-amber-800'"
            >
                <span class="size-1.5 rounded-full" :class="packComplete ? 'bg-emerald-500' : 'bg-amber-500'" />
                {{ packComplete ? 'Credential pack complete' : 'Pack incomplete' }}
            </span>
            <p class="text-sm text-slate-600">
                {{ displayCredentials.length }} document{{ displayCredentials.length === 1 ? '' : 's' }}
                <span v-if="cardPreviewMember.membership_number" class="text-slate-400"> · </span>
                <span v-if="cardPreviewMember.membership_number" class="font-medium text-slate-800">
                    {{ cardPreviewMember.membership_number }}
                </span>
            </p>
        </div>

        <AdminPanel
            v-if="membershipCard || memberUser?.member"
            title="ID card preview"
            description="What your card looks like. Download the signed PDF from the pack below."
        >
            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(16rem,20rem)] lg:items-center">
                <div class="flex justify-center rounded-2xl bg-gradient-to-br from-[#e8f3f7] via-slate-50 to-white p-4 sm:p-6">
                    <MembershipIdCard
                        :member="cardPreviewMember"
                        :logo-url="logoUrl"
                        :verify-url="membershipCards[0] ? publicVerifyUrl(membershipCards[0]) : `${window.location.origin}/verify`"
                    />
                </div>

                <div class="space-y-3">
                    <div class="rounded-xl border border-slate-100 bg-slate-50/80 px-4 py-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Membership number</p>
                        <p class="mt-1 font-semibold text-slate-900">{{ cardPreviewMember.membership_number || '—' }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-100 bg-slate-50/80 px-4 py-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Valid until</p>
                        <p class="mt-1 font-semibold text-slate-900">{{ formatDate(cardPreviewMember.expires_at) }}</p>
                    </div>
                    <button
                        v-if="membershipCards[0]"
                        type="button"
                        class="btn-institutional inline-flex w-full items-center justify-center gap-2 !rounded-xl"
                        :disabled="downloadingUuid === membershipCards[0].uuid"
                        @click="downloadCredential(membershipCards[0])"
                    >
                        <ArrowDownTrayIcon class="size-4" />
                        {{ downloadingUuid === membershipCards[0].uuid ? 'Preparing…' : 'Download ID card PDF' }}
                    </button>
                </div>
            </div>
        </AdminPanel>

        <AdminPanel title="Your credential pack" description="Download each PDF. Use verify actions to share authenticity without exposing the full link.">
            <div v-if="loading" class="flex justify-center py-16">
                <div class="size-10 animate-spin rounded-full border-2 border-institutional/20 border-t-institutional" />
            </div>

            <AdminEmptyState
                v-else-if="displayCredentials.length === 0"
                title="No credentials yet"
                description="Pay the ₦25,000 SICAMA membership fee to receive your ID card, certificate, and welcome letter automatically."
            >
                <template #icon>
                    <ClipboardDocumentIcon class="size-6" />
                </template>
                <RouterLink to="/member/payments" class="btn-institutional mt-4 inline-flex items-center gap-2">
                    Go to payments
                </RouterLink>
            </AdminEmptyState>

            <div v-else class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <article
                    v-for="credential in displayCredentials"
                    :key="credential.uuid"
                    class="flex h-full flex-col overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-sm transition hover:border-institutional/25 hover:shadow-md"
                >
                    <div class="flex items-start justify-between gap-3 border-b border-slate-100 px-5 pt-5 pb-4">
                        <div class="flex min-w-0 items-start gap-3">
                            <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-institutional/10 text-institutional">
                                <component :is="typeIcon(credential.type)" class="size-5" />
                            </span>
                            <div class="min-w-0">
                                <p class="text-xs font-semibold uppercase tracking-wide text-institutional">
                                    {{ shortLabel(credential.type) }}
                                </p>
                                <h3 class="mt-0.5 font-display text-base font-bold leading-snug text-slate-900">
                                    {{ credential.title }}
                                </h3>
                            </div>
                        </div>
                        <span
                            class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold"
                            :class="isValid(credential) ? 'bg-emerald-50 text-emerald-800' : 'bg-red-50 text-red-700'"
                        >
                            {{ isValid(credential) ? 'Valid' : 'Expired' }}
                        </span>
                    </div>

                    <div class="flex flex-1 flex-col gap-4 px-5 py-4">
                        <p class="text-sm leading-relaxed text-slate-600">
                            {{ typeBlurb(credential.type) }}
                        </p>

                        <dl class="grid grid-cols-2 gap-3 text-sm">
                            <div class="rounded-xl bg-slate-50 px-3 py-2.5">
                                <dt class="text-xs text-slate-500">Issued</dt>
                                <dd class="mt-0.5 font-medium text-slate-900">{{ formatDate(credential.issued_at) }}</dd>
                            </div>
                            <div class="rounded-xl bg-slate-50 px-3 py-2.5">
                                <dt class="text-xs text-slate-500">Expires</dt>
                                <dd class="mt-0.5 font-medium text-slate-900">{{ formatDate(credential.expires_at) }}</dd>
                            </div>
                        </dl>

                        <div class="mt-auto space-y-3 border-t border-slate-100 pt-4">
                            <div class="flex items-center justify-between gap-2 text-xs text-slate-500">
                                <span class="inline-flex items-center gap-1.5">
                                    <LinkIcon class="size-3.5 shrink-0" />
                                    Verify code
                                </span>
                                <code class="rounded-md bg-slate-100 px-2 py-0.5 font-mono text-[11px] text-slate-700">
                                    {{ truncatedToken(credential) }}
                                </code>
                            </div>

                            <button
                                type="button"
                                class="btn-institutional inline-flex w-full items-center justify-center gap-2 !rounded-xl"
                                :disabled="downloadingUuid === credential.uuid"
                                @click="downloadCredential(credential)"
                            >
                                <ArrowDownTrayIcon class="size-4" />
                                {{ downloadingUuid === credential.uuid ? 'Preparing…' : 'Download PDF' }}
                            </button>

                            <div class="grid grid-cols-2 gap-2">
                                <button
                                    type="button"
                                    class="btn-secondary inline-flex items-center justify-center gap-1.5 !rounded-xl !px-2 !text-sm"
                                    @click="copyVerifyLink(credential)"
                                >
                                    <LinkIcon class="size-3.5" />
                                    {{ copiedUuid === credential.uuid ? 'Copied' : 'Copy link' }}
                                </button>
                                <a
                                    :href="publicVerifyUrl(credential)"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="btn-secondary inline-flex items-center justify-center gap-1.5 !rounded-xl !px-2 !text-sm"
                                >
                                    <ArrowTopRightOnSquareIcon class="size-3.5" />
                                    Verify
                                </a>
                            </div>
                        </div>
                    </div>
                </article>
            </div>
        </AdminPanel>
    </div>
</template>
