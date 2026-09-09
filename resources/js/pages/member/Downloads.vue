<script setup>
import { computed, ref, onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import {
    ArrowDownTrayIcon,
    BookOpenIcon,
    DocumentTextIcon,
    FolderOpenIcon,
    LockClosedIcon,
    MagnifyingGlassIcon,
    ShieldCheckIcon,
} from '@heroicons/vue/24/outline';
import AdminAlert from '../../components/admin/AdminAlert.vue';
import AdminEmptyState from '../../components/admin/AdminEmptyState.vue';
import AdminPageIntro from '../../components/admin/AdminPageIntro.vue';
import AdminPanel from '../../components/admin/AdminPanel.vue';
import { useAuth } from '../../composables/useAuth';
import { extractApiError } from '../../utils/apiError';

const { getMemberClient, memberUser, fetchMemberProfile } = useAuth();

const downloads = ref([]);
const loading = ref(true);
const search = ref('');
const error = ref('');
const message = ref('');
const downloadingUuid = ref('');

const hasActiveMembership = computed(() => memberUser.value?.member?.status === 'active');

const filteredDownloads = computed(() => {
    const query = search.value.trim().toLowerCase();

    if (!query) {
        return downloads.value;
    }

    return downloads.value.filter((item) =>
        item.title.toLowerCase().includes(query)
        || (item.description ?? '').toLowerCase().includes(query)
        || (item.category?.name ?? '').toLowerCase().includes(query),
    );
});

const accessibleDownloads = computed(() => downloads.value.filter((item) => item.can_access));
const lockedDownloads = computed(() => downloads.value.filter((item) => !item.can_access));
const categoryCount = computed(() => {
    const names = downloads.value
        .map((item) => item.category?.name)
        .filter(Boolean);

    return new Set(names).size;
});

onMounted(async () => {
    await fetchMemberProfile();
    await load();
});

async function load() {
    loading.value = true;
    error.value = '';

    try {
        const { data } = await getMemberClient().get('/downloads');
        downloads.value = data.data || [];
    } catch (e) {
        downloads.value = [];
        error.value = extractApiError(e, 'Unable to load downloads.');
    } finally {
        loading.value = false;
    }
}

function formatPublishedDate(dateString) {
    if (!dateString) {
        return '';
    }

    return new Date(dateString).toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

function formatFileSize(bytes) {
    if (!bytes) {
        return null;
    }

    const units = ['B', 'KB', 'MB', 'GB'];
    let size = bytes;
    let unit = 0;

    while (size >= 1024 && unit < units.length - 1) {
        size /= 1024;
        unit += 1;
    }

    return `${size.toFixed(unit === 0 ? 0 : 1)} ${units[unit]}`;
}

function fileLabel(item) {
    const mime = item.media_file?.mime_type ?? '';

    if (mime.includes('pdf')) {
        return 'PDF';
    }

    if (mime.includes('spreadsheet') || mime.includes('excel')) {
        return 'XLS';
    }

    if (mime.includes('word') || mime.includes('document')) {
        return 'DOC';
    }

    return 'FILE';
}

function visibilityLabel(visibility) {
    const map = {
        public: 'Public',
        members_only: 'Members only',
        tier_specific: 'Tier specific',
    };

    return map[visibility] || String(visibility || 'Resource').replaceAll('_', ' ');
}

function visibilityClass(visibility) {
    const map = {
        public: 'bg-sky-100 text-sky-800',
        members_only: 'bg-indigo-100 text-indigo-800',
        tier_specific: 'bg-violet-100 text-violet-800',
    };

    return map[visibility] || 'bg-slate-100 text-slate-700';
}

async function downloadItem(item) {
    if (!item.can_access || downloadingUuid.value) {
        return;
    }

    downloadingUuid.value = item.uuid;
    message.value = '';
    error.value = '';

    try {
        const { data } = await getMemberClient().get(`/downloads/${item.uuid}/signed-url`);
        window.open(data.signed_url, '_blank');
        message.value = `Download started for "${item.title}".`;
    } catch (e) {
        error.value = extractApiError(e, 'Unable to download this resource.');
    } finally {
        downloadingUuid.value = '';
    }
}
</script>

<template>
    <div>
        <AdminPageIntro description="Access members-only publications, reports, and tier-specific resources from the association library.">
            <template #actions>
                <div class="relative w-full sm:w-72">
                    <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                    <input
                        v-model="search"
                        type="search"
                        placeholder="Search resources…"
                        class="input !py-2.5 !pl-10"
                    />
                </div>
            </template>
        </AdminPageIntro>

        <AdminAlert v-if="message" type="success">{{ message }}</AdminAlert>
        <AdminAlert v-if="error" type="error">{{ error }}</AdminAlert>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="card-modern p-5">
                <div class="flex items-center gap-4">
                    <span class="metric-icon !size-12 !rounded-xl">
                        <BookOpenIcon class="size-6" />
                    </span>
                    <div>
                        <p class="text-sm text-slate-500">Total resources</p>
                        <p class="text-2xl font-bold text-slate-900">{{ loading ? '—' : downloads.length }}</p>
                    </div>
                </div>
            </div>
            <div class="card-modern p-5">
                <div class="flex items-center gap-4">
                    <span class="metric-icon !size-12 !rounded-xl">
                        <ArrowDownTrayIcon class="size-6" />
                    </span>
                    <div>
                        <p class="text-sm text-slate-500">Available to you</p>
                        <p class="text-2xl font-bold text-slate-900">{{ loading ? '—' : accessibleDownloads.length }}</p>
                    </div>
                </div>
            </div>
            <div class="card-modern p-5">
                <div class="flex items-center gap-4">
                    <span class="metric-icon !size-12 !rounded-xl">
                        <LockClosedIcon class="size-6" />
                    </span>
                    <div>
                        <p class="text-sm text-slate-500">Locked</p>
                        <p class="text-2xl font-bold text-slate-900">{{ loading ? '—' : lockedDownloads.length }}</p>
                    </div>
                </div>
            </div>
            <div class="card-modern p-5">
                <div class="flex items-center gap-4">
                    <span class="metric-icon !size-12 !rounded-xl">
                        <FolderOpenIcon class="size-6" />
                    </span>
                    <div>
                        <p class="text-sm text-slate-500">Categories</p>
                        <p class="text-2xl font-bold text-slate-900">{{ loading ? '—' : categoryCount }}</p>
                    </div>
                </div>
            </div>
        </div>

        <AdminPanel
            class="mt-6"
            title="Resource library"
            :description="hasActiveMembership
                ? `Resources available to your ${memberUser?.member?.tier?.name || 'membership'} tier and general member materials.`
                : 'Some resources require an active membership to download.'"
        >
            <div v-if="loading" class="flex justify-center py-16">
                <div class="size-10 animate-spin rounded-full border-2 border-institutional/20 border-t-institutional" />
            </div>

            <AdminEmptyState
                v-else-if="downloads.length === 0"
                title="No downloads available yet"
                description="When the association publishes member resources, handbooks, or tier-specific materials, they will appear here for download."
            >
                <template #icon>
                    <DocumentTextIcon class="size-6" />
                </template>
                <template #action>
                    <RouterLink to="/downloads" class="btn-secondary">Browse public library</RouterLink>
                </template>
            </AdminEmptyState>

            <AdminEmptyState
                v-else-if="filteredDownloads.length === 0"
                title="No matching resources"
                description="Try a different search term or clear the filter to browse all available downloads."
            >
                <template #icon>
                    <MagnifyingGlassIcon class="size-6" />
                </template>
                <template #action>
                    <button type="button" class="btn-secondary" @click="search = ''">Clear search</button>
                </template>
            </AdminEmptyState>

            <div v-else class="grid gap-4 lg:grid-cols-2">
                <article
                    v-for="item in filteredDownloads"
                    :key="item.uuid"
                    class="overflow-hidden rounded-2xl border border-slate-100 bg-white transition hover:border-institutional/20 hover:shadow-sm"
                    :class="item.can_access ? '' : 'opacity-90'"
                >
                    <div class="flex items-start gap-4 border-b border-slate-100 bg-gradient-to-r from-institutional/5 to-transparent p-5">
                        <span class="flex size-14 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-red-50 to-orange-50 text-sm font-bold text-red-600 ring-1 ring-red-100">
                            {{ fileLabel(item) }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span v-if="item.category?.name" class="text-xs font-semibold uppercase tracking-wide text-institutional">
                                    {{ item.category.name }}
                                </span>
                                <span class="badge capitalize" :class="visibilityClass(item.visibility)">
                                    {{ visibilityLabel(item.visibility) }}
                                </span>
                            </div>
                            <h3 class="mt-1 line-clamp-2 text-lg font-semibold text-slate-900">{{ item.title }}</h3>
                        </div>
                    </div>

                    <div class="p-5">
                        <p class="line-clamp-3 text-sm leading-relaxed text-slate-600">
                            {{ item.description || 'Association resource available for download.' }}
                        </p>

                        <dl class="mt-4 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500">
                            <div v-if="formatPublishedDate(item.published_at)">
                                <dt class="sr-only">Published</dt>
                                <dd>Published {{ formatPublishedDate(item.published_at) }}</dd>
                            </div>
                            <div v-if="formatFileSize(item.media_file?.size)">
                                <dt class="sr-only">File size</dt>
                                <dd>{{ formatFileSize(item.media_file?.size) }}</dd>
                            </div>
                            <div v-if="item.download_count">
                                <dt class="sr-only">Downloads</dt>
                                <dd>{{ Number(item.download_count).toLocaleString() }} downloads</dd>
                            </div>
                        </dl>

                        <div class="mt-5 flex flex-wrap items-center gap-3">
                            <button
                                v-if="item.can_access"
                                type="button"
                                class="btn-institutional inline-flex items-center gap-2"
                                :disabled="downloadingUuid === item.uuid"
                                @click="downloadItem(item)"
                            >
                                <ArrowDownTrayIcon class="size-4" />
                                {{ downloadingUuid === item.uuid ? 'Preparing…' : 'Download' }}
                            </button>
                            <span
                                v-else
                                class="inline-flex items-center gap-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-2 text-sm font-medium text-amber-800"
                            >
                                <LockClosedIcon class="size-4" />
                                Not available for your tier
                            </span>
                        </div>
                    </div>
                </article>
            </div>
        </AdminPanel>

        <div
            v-if="!loading && !hasActiveMembership"
            class="mt-6 rounded-2xl border border-amber-200 bg-gradient-to-br from-amber-50 via-white to-white p-6"
        >
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-start gap-3">
                    <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-700">
                        <ShieldCheckIcon class="size-5" />
                    </span>
                    <div>
                        <p class="font-semibold text-amber-900">Unlock more resources</p>
                        <p class="mt-1 text-sm text-amber-800">
                            Active members can access tier-specific publications and exclusive library materials.
                        </p>
                    </div>
                </div>
                <RouterLink to="/member/applications" class="btn-institutional shrink-0">View membership</RouterLink>
            </div>
        </div>
    </div>
</template>
