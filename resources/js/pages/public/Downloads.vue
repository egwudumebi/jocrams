<script setup>
import { computed, ref, onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import {
    ArrowDownTrayIcon,
    BookOpenIcon,
    DocumentTextIcon,
    LockClosedIcon,
    MagnifyingGlassIcon,
} from '@heroicons/vue/24/outline';
import { publicApi } from '../../api/client';

const downloads = ref([]);
const loading = ref(true);
const search = ref('');

const filteredDownloads = computed(() => {
    const query = search.value.trim().toLowerCase();

    if (! query) {
        return downloads.value;
    }

    return downloads.value.filter((item) =>
        item.title.toLowerCase().includes(query)
        || (item.description ?? '').toLowerCase().includes(query)
        || (item.category?.name ?? '').toLowerCase().includes(query),
    );
});

function formatPublishedDate(dateString) {
    if (! dateString) {
        return '';
    }

    return new Date(dateString).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

function formatFileSize(bytes) {
    if (! bytes) {
        return null;
    }

    const units = ['B', 'KB', 'MB', 'GB'];
    let size = bytes;
    let unit = 0;

    while (size >= 1024 && unit < units.length - 1) {
        size /= 1024;
        unit++;
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

onMounted(async () => {
    try {
        const { data } = await publicApi().get('/downloads');
        downloads.value = data.data;
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <div>
        <!-- Page header -->
        <section class="border-b border-slate-200/80 bg-gradient-to-br from-surface-muted via-white to-institutional/5">
            <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 sm:py-16 lg:px-8">
                <div class="flex flex-col gap-8 lg:flex-row lg:items-end lg:justify-between">
                    <div class="max-w-2xl">
                        <p class="label-caps text-institutional">Library</p>
                        <h1 class="mt-2 font-display text-4xl font-bold tracking-tight text-institutional-dark sm:text-5xl">
                            Digital Library
                        </h1>
                        <p class="mt-4 text-lg leading-relaxed text-text-secondary">
                            Browse public publications, reports, and guidelines. Members can access additional resources after signing in.
                        </p>
                    </div>

                    <div class="relative w-full max-w-md shrink-0">
                        <MagnifyingGlassIcon class="pointer-events-none absolute left-4 top-1/2 size-5 -translate-y-1/2 text-text-secondary" aria-hidden="true" />
                        <input
                            v-model="search"
                            type="search"
                            placeholder="Search resources..."
                            class="input !rounded-xl !border-slate-200/80 !py-3 !pl-12 !shadow-sm focus:!border-institutional focus:!ring-institutional/20"
                        />
                    </div>
                </div>

                <div v-if="! loading && downloads.length > 0" class="mt-10 flex flex-wrap gap-3">
                    <span class="inline-flex items-center gap-2 rounded-full bg-white px-4 py-2 text-sm font-medium text-institutional-dark shadow-sm ring-1 ring-slate-200/80">
                        <BookOpenIcon class="size-4 text-institutional" aria-hidden="true" />
                        {{ downloads.length }} public {{ downloads.length === 1 ? 'resource' : 'resources' }}
                    </span>
                    <span class="inline-flex items-center rounded-full bg-institutional/8 px-4 py-2 text-sm font-medium text-institutional">
                        Free to download
                    </span>
                </div>
            </div>
        </section>

        <!-- Content -->
        <section class="section-padding bg-white">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div v-if="loading" class="flex justify-center py-16">
                    <div class="size-10 animate-spin rounded-full border-2 border-institutional/20 border-t-institutional" />
                </div>

                <div v-else-if="downloads.length === 0" class="empty-state mx-auto max-w-2xl">
                    <DocumentTextIcon class="mx-auto size-12 text-institutional/25" aria-hidden="true" />
                    <h2 class="mt-5 font-display text-2xl font-bold text-institutional-dark">No public resources yet</h2>
                    <p class="mx-auto mt-3 max-w-md text-base leading-relaxed text-text-secondary">
                        We're preparing publications for the library. Sign in to explore members-only materials in the meantime.
                    </p>
                    <div class="mt-8 flex flex-wrap justify-center gap-4">
                        <RouterLink to="/member/login" class="btn-institutional inline-flex">
                            <LockClosedIcon class="mr-2 size-4" aria-hidden="true" />
                            Member Login
                        </RouterLink>
                        <RouterLink to="/member/register" class="btn-gold inline-flex">
                            Join Us / Register
                        </RouterLink>
                    </div>
                </div>

                <div v-else-if="filteredDownloads.length === 0" class="empty-state mx-auto max-w-xl">
                    <MagnifyingGlassIcon class="mx-auto size-10 text-institutional/25" aria-hidden="true" />
                    <h2 class="mt-4 font-display text-xl font-bold text-institutional-dark">No matches found</h2>
                    <p class="mt-2 text-sm text-text-secondary">Try a different search term or browse all resources.</p>
                    <button type="button" class="link-arrow mt-4" @click="search = ''">
                        Clear search
                    </button>
                </div>

                <div v-else class="grid auto-rows-fr gap-6 sm:grid-cols-2 xl:grid-cols-3">
                    <article
                        v-for="item in filteredDownloads"
                        :key="item.uuid"
                        class="hover-lift card-modern flex h-full flex-col overflow-hidden"
                    >
                        <div class="flex items-start gap-4 border-b border-slate-100 bg-gradient-to-r from-institutional/5 to-transparent p-6">
                            <span class="flex size-14 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-red-50 to-orange-50 text-sm font-bold text-red-600 ring-1 ring-red-100">
                                {{ fileLabel(item) }}
                            </span>
                            <div class="min-w-0 flex-1">
                                <span v-if="item.category?.name" class="label-caps text-institutional">{{ item.category.name }}</span>
                                <h2 class="mt-1 line-clamp-2 font-display text-lg font-semibold text-institutional-dark">
                                    {{ item.title }}
                                </h2>
                            </div>
                        </div>

                        <div class="flex flex-1 flex-col p-6">
                            <p class="line-clamp-3 flex-1 text-sm leading-relaxed text-text-secondary">
                                {{ item.description || 'Association publication available for download.' }}
                            </p>

                            <dl class="mt-5 flex flex-wrap gap-x-4 gap-y-1 text-xs text-text-secondary">
                                <div v-if="formatPublishedDate(item.published_at)">
                                    <dt class="sr-only">Published</dt>
                                    <dd>{{ formatPublishedDate(item.published_at) }}</dd>
                                </div>
                                <div v-if="formatFileSize(item.media_file?.size)">
                                    <dt class="sr-only">File size</dt>
                                    <dd>{{ formatFileSize(item.media_file?.size) }}</dd>
                                </div>
                                <div v-if="item.download_count">
                                    <dt class="sr-only">Downloads</dt>
                                    <dd>{{ item.download_count.toLocaleString() }} downloads</dd>
                                </div>
                            </dl>

                            <a
                                v-if="item.signed_url"
                                :href="item.signed_url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="btn-institutional mt-6 inline-flex w-full justify-center !py-2.5 text-sm sm:w-auto"
                            >
                                <ArrowDownTrayIcon class="mr-2 size-4" aria-hidden="true" />
                                Download
                            </a>
                        </div>
                    </article>
                </div>
            </div>
        </section>

        <!-- Member upsell -->
        <section class="border-t border-slate-200/80 bg-surface-muted py-14 sm:py-16">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="card-modern flex flex-col items-center gap-6 p-8 text-center sm:p-10 lg:flex-row lg:text-left">
                    <div class="flex size-16 shrink-0 items-center justify-center rounded-2xl bg-institutional/10 text-institutional">
                        <LockClosedIcon class="size-8" aria-hidden="true" />
                    </div>
                    <div class="flex-1">
                        <h2 class="font-display text-2xl font-bold text-institutional-dark">Need members-only resources?</h2>
                        <p class="mt-2 max-w-2xl text-text-secondary">
                            Active members get access to tier-specific publications, research papers, and exclusive downloads not listed here.
                        </p>
                    </div>
                    <RouterLink to="/member/login" class="btn-institutional shrink-0">
                        Sign in to unlock
                    </RouterLink>
                </div>
            </div>
        </section>
    </div>
</template>
