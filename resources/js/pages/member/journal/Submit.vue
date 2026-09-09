<script setup>
import { computed, ref, onMounted, watch } from 'vue';
import { useRouter, RouterLink } from 'vue-router';
import {
    ArrowLeftIcon,
    BanknotesIcon,
    CalendarDaysIcon,
    CheckCircleIcon,
    DocumentArrowUpIcon,
    DocumentTextIcon,
    MegaphoneIcon,
    UserGroupIcon,
    XMarkIcon,
} from '@heroicons/vue/24/outline';
import AdminAlert from '../../../components/admin/AdminAlert.vue';
import AdminPanel from '../../../components/admin/AdminPanel.vue';
import { useAuth } from '../../../composables/useAuth';
import { extractApiError } from '../../../utils/apiError';
import { renderRichTextHtml, RICH_TEXT_PROSE_CLASSES } from '../../../utils/html';

const router = useRouter();
const { getJournalClient, fetchMemberProfile, canJournalSubmit, memberUser } = useAuth();

const openCalls = ref([]);
const categories = ref([]);
const loadingCategories = ref(true);
const selectedCallUuid = ref('');
const loadingCalls = ref(true);
const dragActive = ref(false);
const form = ref({
    title: '',
    author_name: '',
    author_email: '',
    abstract: '',
    category: '',
    keywords: '',
    mins_read: '',
});
const file = ref(null);
const fileInput = ref(null);
const submitting = ref(false);
const error = ref('');

const selectedCall = computed(() =>
    openCalls.value.find((call) => call.uuid === selectedCallUuid.value) ?? null,
);

const selectedCallBodyHtml = computed(() => renderRichTextHtml(selectedCall.value?.body));

const hasOpenCalls = computed(() => openCalls.value.length > 0);

const hasCategoryOptions = computed(() => categories.value.length > 0);

const feeSummary = computed(() => {
    if (!selectedCall.value) {
        return null;
    }

    return {
        submission: Number(selectedCall.value.submission_fee || 0),
        publication: Number(selectedCall.value.publication_fee || 0),
        currency: selectedCall.value.currency || 'NGN',
    };
});

const totalDueNow = computed(() => feeSummary.value?.submission ?? 0);

const abstractLength = computed(() => form.value.abstract.length);

const formProgress = computed(() => {
    let filled = 0;
    const checks = [
        Boolean(selectedCallUuid.value),
        Boolean(form.value.title.trim()),
        Boolean(form.value.author_name.trim()),
        Boolean(form.value.author_email.trim()),
        Boolean(form.value.category.trim()),
        Boolean(form.value.abstract.trim()),
        Boolean(file.value),
    ];

    checks.forEach((ok) => {
        if (ok) {
            filled += 1;
        }
    });

    return Math.round((filled / checks.length) * 100);
});

const canSubmit = computed(() =>
    hasOpenCalls.value && formProgress.value === 100 && canJournalSubmit() && !submitting.value,
);

const pageDescription = computed(() => {
    if (loadingCalls.value) {
        return 'Loading open calls for papers…';
    }

    if (!hasOpenCalls.value) {
        return 'Manuscript submissions open only during an active call for papers published by the journal team.';
    }

    return 'Select a call for papers, complete your manuscript details, and pay any required submission fee.';
});

function formatMoney(amount, currency = 'NGN') {
    return new Intl.NumberFormat('en-NG', { style: 'currency', currency }).format(amount || 0);
}

function formatDate(value) {
    if (!value) {
        return null;
    }

    return new Date(value).toLocaleDateString(undefined, { dateStyle: 'medium' });
}

function formatFileSize(bytes) {
    if (!bytes) {
        return '';
    }

    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

onMounted(async () => {
    await fetchMemberProfile();
    form.value.author_name = memberUser.value?.name || '';
    form.value.author_email = memberUser.value?.email || '';
    await Promise.all([loadOpenCalls(), loadCategories()]);
});

watch(openCalls, (calls) => {
    if (calls.length === 1) {
        selectedCallUuid.value = calls[0].uuid;
    }
});

async function loadOpenCalls() {
    loadingCalls.value = true;

    try {
        const { data } = await getJournalClient().get('/calls/open');
        openCalls.value = data.data || [];
    } catch {
        openCalls.value = [];
    } finally {
        loadingCalls.value = false;
    }
}

async function loadCategories() {
    loadingCategories.value = true;

    try {
        const { data } = await getJournalClient().get('/categories');
        categories.value = data.data || [];
    } catch {
        categories.value = [];
    } finally {
        loadingCategories.value = false;
    }
}

function onFileChange(event) {
    file.value = event.target.files?.[0] || null;
}

function clearFile() {
    file.value = null;
    if (fileInput.value) {
        fileInput.value.value = '';
    }
}

function onDragOver(event) {
    event.preventDefault();
    dragActive.value = true;
}

function onDragLeave() {
    dragActive.value = false;
}

function onDrop(event) {
    event.preventDefault();
    dragActive.value = false;
    const dropped = event.dataTransfer?.files?.[0];

    if (dropped && /\.docx$/i.test(dropped.name)) {
        file.value = dropped;
    } else if (dropped) {
        error.value = 'Only DOCX manuscripts are accepted.';
    }
}

function paymentIdempotencyKey(submissionId) {
    return `journal-submission-${submissionId}-${Date.now()}`;
}

async function initiatePayment(submissionId) {
    const { data } = await getJournalClient().post(`/submissions/${submissionId}/payments/initialize`, {
        gateway: 'paystack',
        idempotency_key: paymentIdempotencyKey(submissionId),
    });

    const redirectUrl = data.authorization_url || data.data?.authorization_url;

    if (redirectUrl) {
        window.location.href = redirectUrl;
        return;
    }

    router.push(`/member/journal/${submissionId}`);
}

async function submitManuscript() {
    if (!hasOpenCalls.value) {
        error.value = 'Submissions are closed until the journal publishes an open call for papers.';
        return;
    }

    if (!selectedCallUuid.value) {
        error.value = 'Please select a call for papers.';
        return;
    }

    if (!file.value) {
        error.value = 'Please attach a DOCX manuscript.';
        return;
    }

    if (!/\.docx$/i.test(file.value.name)) {
        error.value = 'Only DOCX manuscripts are accepted.';
        return;
    }

    submitting.value = true;
    error.value = '';

    const fd = new FormData();
    Object.entries(form.value).forEach(([key, value]) => {
        if (value !== '' && value !== null) {
            fd.append(key, value);
        }
    });

    if (selectedCallUuid.value) {
        fd.append('call_for_papers_uuid', selectedCallUuid.value);
    }

    fd.append('document', file.value);

    try {
        const { data } = await getJournalClient().post('/submissions', fd, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });

        if (data.requires_payment && data.submission_fee > 0) {
            await initiatePayment(data.submission_id);
            return;
        }

        router.push(`/member/journal/${data.submission_id}`);
    } catch (e) {
        error.value = extractApiError(e, 'Submission failed. Check all required fields.');
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <div class="pb-24 lg:pb-8">
        <RouterLink
            to="/member/journal"
            class="inline-flex items-center gap-1.5 text-sm font-medium text-brand-600 transition hover:text-brand-700"
        >
            <ArrowLeftIcon class="size-4" />
            Back to submissions
        </RouterLink>

        <div class="mt-4 overflow-hidden rounded-2xl border border-slate-200 bg-gradient-to-br from-institutional/5 via-white to-brand-50/30">
            <div class="flex flex-col gap-5 p-6 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-start gap-4">
                    <span class="flex size-14 shrink-0 items-center justify-center rounded-2xl bg-institutional/10 text-institutional shadow-sm">
                        <DocumentTextIcon class="size-7" />
                    </span>
                    <div>
                        <h1 class="text-xl font-bold text-slate-900 sm:text-2xl">Submit manuscript</h1>
                        <p class="mt-1 max-w-2xl text-sm leading-relaxed text-slate-600">{{ pageDescription }}</p>
                    </div>
                </div>
                <div v-if="canJournalSubmit() && hasOpenCalls && !loadingCalls" class="shrink-0 sm:text-right">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Form progress</p>
                    <div class="mt-2 flex items-center gap-3 sm:justify-end">
                        <div class="h-2 w-36 overflow-hidden rounded-full bg-slate-200">
                            <div
                                class="h-full rounded-full bg-institutional transition-all duration-300"
                                :style="{ width: `${formProgress}%` }"
                            />
                        </div>
                        <span class="text-sm font-bold tabular-nums text-institutional">{{ formProgress }}%</span>
                    </div>
                </div>
            </div>
        </div>

        <div
            v-if="!canJournalSubmit()"
            class="mt-6 rounded-2xl border border-amber-200 bg-gradient-to-br from-amber-50 via-white to-white p-6"
        >
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="font-semibold text-amber-900">Active membership required</p>
                    <p class="mt-1 text-sm text-amber-800">
                        Complete your membership application before submitting manuscripts to the journal.
                    </p>
                </div>
                <RouterLink to="/member/applications" class="btn-institutional shrink-0">Go to applications</RouterLink>
            </div>
        </div>

        <template v-else>
            <div v-if="loadingCalls" class="mt-6 card-modern p-6">
                <div class="animate-pulse space-y-4">
                    <div class="h-4 w-1/3 rounded bg-slate-200" />
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="h-24 rounded-xl bg-slate-100" />
                        <div class="h-24 rounded-xl bg-slate-100" />
                    </div>
                </div>
            </div>

            <div
                v-else-if="!hasOpenCalls"
                class="mt-6 rounded-2xl border border-slate-200 bg-gradient-to-br from-slate-50 via-white to-white p-8 text-center"
            >
                <span class="mx-auto flex size-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-500">
                    <MegaphoneIcon class="size-7" />
                </span>
                <h2 class="mt-4 text-lg font-semibold text-slate-900">Submissions are currently closed</h2>
                <p class="mx-auto mt-2 max-w-lg text-sm leading-relaxed text-slate-600">
                    You can submit a manuscript only when the journal team publishes an open call for papers.
                    Check back later or contact support if you believe a call should be available.
                </p>
                <RouterLink to="/member/journal" class="btn-secondary mt-6 inline-flex !rounded-xl">
                    Back to my submissions
                </RouterLink>
            </div>

            <div v-else class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_320px] xl:items-start">
                <div class="space-y-6">
                    <section class="card-modern overflow-hidden">
                        <div class="border-b border-slate-100 bg-slate-50/80 px-5 py-4">
                            <div class="flex items-center gap-2">
                                <MegaphoneIcon class="size-5 text-institutional" />
                                <div>
                                    <h2 class="font-semibold text-slate-900">Call for papers</h2>
                                    <p class="text-sm text-slate-500">Choose the announcement you are responding to.</p>
                                </div>
                            </div>
                        </div>
                        <div class="p-5">
                            <div class="grid gap-3 sm:grid-cols-2">
                                <label
                                    v-for="call in openCalls"
                                    :key="call.uuid"
                                    class="relative cursor-pointer rounded-xl border p-4 transition"
                                    :class="selectedCallUuid === call.uuid
                                        ? 'border-institutional bg-institutional/5 ring-2 ring-institutional/20'
                                        : 'border-slate-200 hover:border-slate-300 hover:bg-slate-50/50'"
                                >
                                    <input v-model="selectedCallUuid" type="radio" class="sr-only" :value="call.uuid" />
                                    <span
                                        v-if="selectedCallUuid === call.uuid"
                                        class="absolute right-3 top-3 flex size-5 items-center justify-center rounded-full bg-institutional text-white"
                                    >
                                        <CheckCircleIcon class="size-4" />
                                    </span>
                                    <p class="pr-8 font-semibold text-slate-900">{{ call.title }}</p>
                                    <p v-if="call.issue?.label" class="mt-1 text-xs font-medium text-institutional/80">{{ call.issue.label }}</p>
                                    <p v-if="call.excerpt" class="mt-1 line-clamp-2 text-sm text-slate-600">{{ call.excerpt }}</p>
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        <span
                                            v-if="call.closes_at"
                                            class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600"
                                        >
                                            <CalendarDaysIcon class="size-3.5" />
                                            Closes {{ formatDate(call.closes_at) }}
                                        </span>
                                        <span
                                            v-if="call.submission_fee > 0"
                                            class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700"
                                        >
                                            <BanknotesIcon class="size-3.5" />
                                            {{ formatMoney(call.submission_fee, call.currency) }} submission
                                        </span>
                                    </div>
                                </label>
                            </div>

                            <div
                                v-if="selectedCall?.body"
                                :class="['mt-5 rounded-xl border border-slate-100 bg-slate-50/80 p-4 sm:p-5', RICH_TEXT_PROSE_CLASSES]"
                                v-html="selectedCallBodyHtml"
                            />

                            <div v-if="selectedCall?.editorial_board?.length" class="mt-5">
                                <h3 class="flex items-center gap-2 text-sm font-semibold text-slate-900">
                                    <UserGroupIcon class="size-4 text-institutional" />
                                    Editorial board
                                </h3>
                                <ul class="mt-3 grid gap-2 sm:grid-cols-2">
                                    <li
                                        v-for="member in selectedCall.editorial_board"
                                        :key="member.uuid"
                                        class="rounded-xl border border-slate-100 bg-white px-3 py-2.5 text-sm"
                                    >
                                        <p class="font-medium text-slate-900">{{ member.name }}</p>
                                        <p class="text-slate-500">{{ member.role_title }}</p>
                                        <p v-if="member.affiliation" class="text-xs text-slate-400">{{ member.affiliation }}</p>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </section>

                    <form class="space-y-6" @submit.prevent="submitManuscript">
                        <AdminAlert v-if="error" type="error">{{ error }}</AdminAlert>

                        <AdminPanel title="Manuscript details" description="Required fields are marked. Upload your manuscript as a DOCX file.">
                            <div class="space-y-6">
                                <div>
                                    <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Core details</p>
                                    <label class="label">Title</label>
                                    <input
                                        v-model="form.title"
                                        class="input !rounded-xl"
                                        placeholder="Full title of your manuscript"
                                        required
                                    />
                                </div>

                                <div>
                                    <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Author information</p>
                                    <div class="grid gap-4 sm:grid-cols-2">
                                        <div>
                                            <label class="label">Author name</label>
                                            <input v-model="form.author_name" class="input !rounded-xl" required />
                                        </div>
                                        <div>
                                            <label class="label">Author email</label>
                                            <input v-model="form.author_email" type="email" class="input !rounded-xl" required />
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Classification</p>
                                    <div class="grid gap-4 sm:grid-cols-2">
                                        <div>
                                            <label class="label">Category</label>
                                            <select
                                                v-if="hasCategoryOptions"
                                                v-model="form.category"
                                                class="input !rounded-xl"
                                                required
                                            >
                                                <option value="" disabled>Select a category</option>
                                                <option v-for="category in categories" :key="category.uuid" :value="category.name">
                                                    {{ category.name }}
                                                </option>
                                            </select>
                                            <input
                                                v-else
                                                v-model="form.category"
                                                class="input !rounded-xl"
                                                placeholder="e.g. research, education"
                                                required
                                            />
                                            <p v-if="hasCategoryOptions" class="mt-1 text-xs text-slate-500">
                                                Categories are managed by the journal editorial team.
                                            </p>
                                        </div>
                                        <div>
                                            <label class="label">Keywords</label>
                                            <input
                                                v-model="form.keywords"
                                                class="input !rounded-xl"
                                                placeholder="comma-separated"
                                            />
                                        </div>
                                        <div class="sm:col-span-2 sm:max-w-xs">
                                            <label class="label">Estimated read time (minutes)</label>
                                            <input v-model="form.mins_read" type="number" min="1" class="input !rounded-xl" placeholder="Optional" />
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Abstract</p>
                                    <textarea
                                        v-model="form.abstract"
                                        rows="6"
                                        class="input !rounded-xl"
                                        placeholder="Summarize your research question, methods, and findings…"
                                        maxlength="5000"
                                        required
                                    />
                                    <p class="mt-1.5 text-right text-xs tabular-nums text-slate-400">{{ abstractLength }} / 5000</p>
                                </div>

                                <div>
                                    <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Document</p>
                                    <div
                                        class="relative rounded-2xl border-2 border-dashed transition"
                                        :class="dragActive
                                            ? 'border-institutional bg-institutional/5'
                                            : file
                                                ? 'border-emerald-300 bg-emerald-50/40'
                                                : 'border-slate-200 bg-slate-50/50 hover:border-institutional/40 hover:bg-institutional/5'"
                                        @dragover="onDragOver"
                                        @dragleave="onDragLeave"
                                        @drop="onDrop"
                                    >
                                        <label class="flex cursor-pointer flex-col items-center px-4 py-10 text-center">
                                            <span
                                                class="flex size-14 items-center justify-center rounded-2xl transition"
                                                :class="file ? 'bg-emerald-100 text-emerald-600' : 'bg-white text-slate-400 shadow-sm'"
                                            >
                                                <DocumentArrowUpIcon class="size-7" />
                                            </span>
                                            <span class="mt-4 text-sm font-semibold text-slate-900">
                                                {{ file ? file.name : 'Drop your manuscript here' }}
                                            </span>
                                            <span class="mt-1 text-xs text-slate-500">
                                                {{ file ? formatFileSize(file.size) : 'or click to browse · DOCX only · max 25 MB' }}
                                            </span>
                                            <span v-if="!file" class="btn-secondary mt-4 !rounded-xl text-sm">Choose file</span>
                                            <input
                                                ref="fileInput"
                                                type="file"
                                                accept=".docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                                                required
                                                class="sr-only"
                                                @change="onFileChange"
                                            />
                                        </label>
                                        <button
                                            v-if="file"
                                            type="button"
                                            class="absolute right-3 top-3 rounded-lg p-1.5 text-slate-400 transition hover:bg-white hover:text-slate-700"
                                            aria-label="Remove file"
                                            @click.stop="clearFile"
                                        >
                                            <XMarkIcon class="size-5" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </AdminPanel>

                        <div class="hidden xl:block">
                            <button
                                class="btn-institutional w-full !rounded-xl py-3 text-base"
                                type="submit"
                                :disabled="!canSubmit"
                            >
                                {{
                                    submitting
                                        ? (totalDueNow > 0 ? 'Submitting & redirecting to payment…' : 'Submitting…')
                                        : (totalDueNow > 0 ? 'Submit & pay submission fee' : 'Submit manuscript')
                                }}
                            </button>
                        </div>
                    </form>
                </div>

                <aside class="space-y-4 xl:sticky xl:top-6">
                    <div class="card-modern p-5">
                        <h3 class="text-sm font-semibold text-slate-900">Submission checklist</h3>
                        <ul class="mt-4 space-y-3">
                            <li class="flex items-start gap-2.5 text-sm">
                                <CheckCircleIcon
                                    class="size-5 shrink-0"
                                    :class="selectedCallUuid ? 'text-emerald-500' : 'text-slate-300'"
                                />
                                <span :class="selectedCallUuid ? 'text-slate-700' : 'text-slate-400'">Call for papers selected</span>
                            </li>
                            <li class="flex items-start gap-2.5 text-sm">
                                <CheckCircleIcon
                                    class="size-5 shrink-0"
                                    :class="form.category.trim() ? 'text-emerald-500' : 'text-slate-300'"
                                />
                                <span :class="form.category.trim() ? 'text-slate-700' : 'text-slate-400'">Category selected</span>
                            </li>
                            <li class="flex items-start gap-2.5 text-sm">
                                <CheckCircleIcon
                                    class="size-5 shrink-0"
                                    :class="form.title.trim() ? 'text-emerald-500' : 'text-slate-300'"
                                />
                                <span :class="form.title.trim() ? 'text-slate-700' : 'text-slate-400'">Title provided</span>
                            </li>
                            <li class="flex items-start gap-2.5 text-sm">
                                <CheckCircleIcon
                                    class="size-5 shrink-0"
                                    :class="form.abstract.trim() ? 'text-emerald-500' : 'text-slate-300'"
                                />
                                <span :class="form.abstract.trim() ? 'text-slate-700' : 'text-slate-400'">Abstract written</span>
                            </li>
                            <li class="flex items-start gap-2.5 text-sm">
                                <CheckCircleIcon
                                    class="size-5 shrink-0"
                                    :class="file ? 'text-emerald-500' : 'text-slate-300'"
                                />
                                <span :class="file ? 'text-slate-700' : 'text-slate-400'">Manuscript attached</span>
                            </li>
                        </ul>
                    </div>

                    <div
                        v-if="feeSummary && (feeSummary.submission > 0 || feeSummary.publication > 0)"
                        class="card-modern overflow-hidden"
                    >
                        <div class="border-b border-slate-100 bg-slate-50/80 px-5 py-3">
                            <h3 class="flex items-center gap-2 text-sm font-semibold text-slate-900">
                                <BanknotesIcon class="size-4 text-institutional" />
                                Fee summary
                            </h3>
                        </div>
                        <dl class="space-y-0 divide-y divide-slate-100 px-5 py-1">
                            <div class="flex items-center justify-between py-3 text-sm">
                                <dt class="text-slate-500">Submission fee</dt>
                                <dd class="font-semibold text-slate-900">{{ formatMoney(feeSummary.submission, feeSummary.currency) }}</dd>
                            </div>
                            <div class="flex items-center justify-between py-3 text-sm">
                                <dt class="text-slate-500">Publication fee</dt>
                                <dd class="font-semibold text-slate-900">{{ formatMoney(feeSummary.publication, feeSummary.currency) }}</dd>
                            </div>
                            <div class="flex items-center justify-between py-3">
                                <dt class="text-sm font-medium text-institutional">Due now</dt>
                                <dd class="text-lg font-bold text-institutional">{{ formatMoney(totalDueNow, feeSummary.currency) }}</dd>
                            </div>
                        </dl>
                        <p class="border-t border-slate-100 px-5 py-3 text-xs text-slate-500">
                            Publication fee is charged only after your manuscript is accepted.
                        </p>
                    </div>

                    <div v-else-if="selectedCall" class="card-modern p-5 text-sm text-slate-600">
                        <p class="font-medium text-slate-900">No fees for this call</p>
                        <p class="mt-1">Submitting this manuscript is free. You will not be redirected to payment.</p>
                    </div>

                    <div class="hidden rounded-2xl border border-slate-200 bg-slate-50/60 p-4 text-xs leading-relaxed text-slate-500 xl:block">
                        By submitting, you confirm the manuscript is original work and that all co-authors have been notified.
                    </div>
                </aside>
            </div>

            <div
                v-if="hasOpenCalls"
                class="fixed inset-x-0 bottom-0 z-20 border-t border-slate-200 bg-white/95 px-4 py-3 backdrop-blur xl:hidden"
            >
                <div class="mx-auto flex max-w-3xl items-center gap-3">
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-xs text-slate-500">{{ formProgress }}% complete</p>
                        <p v-if="totalDueNow > 0" class="truncate text-sm font-semibold text-institutional">
                            {{ formatMoney(totalDueNow, feeSummary?.currency) }} due now
                        </p>
                    </div>
                    <button
                        class="btn-institutional shrink-0 !rounded-xl px-5"
                        type="button"
                        :disabled="!canSubmit"
                        @click="submitManuscript"
                    >
                        {{ submitting ? 'Submitting…' : 'Submit' }}
                    </button>
                </div>
            </div>
        </template>
    </div>
</template>
