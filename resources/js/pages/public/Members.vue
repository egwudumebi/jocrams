<script setup>
import { computed, ref, onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import {
    ArrowRightIcon,
    MagnifyingGlassIcon,
    UserPlusIcon,
    UsersIcon,
} from '@heroicons/vue/24/outline';
import { publicApi } from '../../api/client';

const members = ref([]);
const search = ref('');
const loading = ref(true);
const totalMembers = ref(0);
const failedImages = ref(new Set());

function onProfileImageError(memberUuid) {
    failedImages.value = new Set([...failedImages.value, memberUuid]);
}

function showProfileImage(member) {
    return Boolean(member.profile_image) && !failedImages.value.has(member.uuid);
}

const filteredMembers = computed(() => {
    const query = search.value.trim().toLowerCase();

    if (!query) {
        return members.value;
    }

    return members.value.filter((member) => {
        const haystack = [
            member.name,
            member.tier?.name,
            member.position,
            member.membership_number,
            member.city,
            member.state,
        ]
            .filter(Boolean)
            .join(' ')
            .toLowerCase();

        return haystack.includes(query);
    });
});

function formatJoinedDate(dateString) {
    if (!dateString) {
        return '';
    }

    return new Date(dateString).toLocaleDateString('en-US', {
        month: 'short',
        year: 'numeric',
    });
}

function memberInitials(name) {
    if (!name) {
        return '?';
    }

    return name
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part.charAt(0))
        .join('')
        .toUpperCase();
}

onMounted(async () => {
    try {
        const { data } = await publicApi().get('/members');
        members.value = data.data || [];
        totalMembers.value = data.meta?.total ?? members.value.length;
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <div>
        <section class="border-b border-slate-200/80 bg-gradient-to-br from-surface-muted via-white to-institutional/5">
            <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 sm:py-16 lg:px-8">
                <div class="flex flex-col gap-8 lg:flex-row lg:items-end lg:justify-between">
                    <div class="max-w-2xl">
                        <p class="label-caps text-institutional">Community</p>
                        <h1 class="mt-2 font-display text-4xl font-bold tracking-tight text-institutional-dark sm:text-5xl">
                            Member Directory
                        </h1>
                        <p class="mt-4 text-lg leading-relaxed text-text-secondary">
                            Discover active members of our association — their roles, tiers, and public profiles.
                            New registrations appear here after membership payment is verified and an administrator activates the account.
                        </p>
                    </div>

                    <div class="relative w-full max-w-md shrink-0">
                        <MagnifyingGlassIcon class="pointer-events-none absolute left-4 top-1/2 size-5 -translate-y-1/2 text-text-secondary" aria-hidden="true" />
                        <input
                            v-model="search"
                            type="search"
                            placeholder="Search by name, tier, or ID..."
                            class="input !rounded-xl !border-slate-200/80 !py-3 !pl-12 !shadow-sm focus:!border-institutional focus:!ring-institutional/20"
                        />
                    </div>
                </div>

                <div v-if="!loading && members.length > 0" class="mt-10 flex flex-wrap gap-3">
                    <span class="inline-flex items-center gap-2 rounded-full bg-white px-4 py-2 text-sm font-medium text-institutional-dark shadow-sm ring-1 ring-slate-200/80">
                        <UsersIcon class="size-4 text-institutional" aria-hidden="true" />
                        {{ totalMembers }} active {{ totalMembers === 1 ? 'member' : 'members' }}
                    </span>
                    <span v-if="search.trim()" class="inline-flex items-center rounded-full bg-institutional/8 px-4 py-2 text-sm font-medium text-institutional">
                        {{ filteredMembers.length }} matching
                    </span>
                </div>
            </div>
        </section>

        <section class="section-padding bg-white">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div v-if="loading" class="flex justify-center py-16">
                    <div class="size-10 animate-spin rounded-full border-2 border-institutional/20 border-t-institutional" />
                </div>

                <div v-else-if="!members.length" class="empty-state mx-auto max-w-2xl">
                    <UsersIcon class="mx-auto size-12 text-institutional/25" aria-hidden="true" />
                    <h2 class="mt-5 font-display text-2xl font-bold text-institutional-dark">No members listed yet</h2>
                    <p class="mx-auto mt-3 max-w-md text-base leading-relaxed text-text-secondary">
                        Active members will appear here once their profiles are published.
                    </p>
                    <RouterLink to="/register" class="btn-institutional mt-8 inline-flex">
                        Join the association
                    </RouterLink>
                </div>

                <div v-else-if="!filteredMembers.length" class="empty-state mx-auto max-w-xl">
                    <MagnifyingGlassIcon class="mx-auto size-10 text-institutional/25" aria-hidden="true" />
                    <h2 class="mt-4 font-display text-xl font-bold text-institutional-dark">No members match your search</h2>
                    <p class="mt-2 text-sm text-text-secondary">Try a different name, tier, or membership number.</p>
                    <button type="button" class="link-arrow mt-4" @click="search = ''">
                        Clear search
                    </button>
                </div>

                <div v-else class="grid auto-rows-fr gap-6 sm:grid-cols-2 xl:grid-cols-3">
                    <RouterLink
                        v-for="member in filteredMembers"
                        :key="member.uuid"
                        :to="`/members/${member.uuid}`"
                        class="group hover-lift card-modern flex h-full flex-col overflow-hidden"
                    >
                        <div class="bg-gradient-to-br from-institutional/90 to-brand-700 px-6 py-5 text-white">
                            <div class="flex items-center gap-4">
                                <img
                                    v-if="showProfileImage(member)"
                                    :src="member.profile_image"
                                    :alt="member.name"
                                    class="size-14 rounded-full border-2 border-white/30 object-cover"
                                    @error="onProfileImageError(member.uuid)"
                                />
                                <div
                                    v-else
                                    class="flex size-14 items-center justify-center rounded-full bg-white/15 text-lg font-bold"
                                >
                                    {{ memberInitials(member.name) }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <h2 class="truncate font-display text-lg font-semibold">{{ member.name }}</h2>
                                    <p class="mt-0.5 text-sm text-white/80">{{ member.tier?.name }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-1 flex-col p-5 sm:p-6">
                            <p v-if="member.position" class="line-clamp-2 text-sm leading-relaxed text-text-secondary">
                                {{ member.position }}
                            </p>
                            <p v-if="member.city || member.state" class="mt-2 text-xs text-text-secondary/80">
                                {{ [member.city, member.state].filter(Boolean).join(', ') }}
                            </p>
                            <div class="mt-auto flex items-center justify-between gap-3 pt-5">
                                <p class="font-mono text-xs text-slate-400">{{ member.membership_number }}</p>
                                <span class="link-arrow shrink-0 text-xs">
                                    Profile
                                    <ArrowRightIcon class="size-3.5 transition group-hover:translate-x-0.5" aria-hidden="true" />
                                </span>
                            </div>
                            <p v-if="member.joined_at" class="mt-2 text-xs text-slate-400">
                                Member since {{ formatJoinedDate(member.joined_at) }}
                            </p>
                        </div>
                    </RouterLink>
                </div>
            </div>
        </section>

        <section class="border-t border-slate-200/80 bg-surface-muted py-14 sm:py-16">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="card-modern flex flex-col items-center gap-6 p-8 text-center sm:p-10 lg:flex-row lg:text-left">
                    <div class="flex size-16 shrink-0 items-center justify-center rounded-2xl bg-institutional/10 text-institutional">
                        <UserPlusIcon class="size-8" aria-hidden="true" />
                    </div>
                    <div class="flex-1">
                        <h2 class="font-display text-2xl font-bold text-institutional-dark">Become a member</h2>
                        <p class="mt-2 max-w-2xl text-text-secondary">
                            Join our association to access events, credentials, journal submissions, and member resources.
                        </p>
                    </div>
                    <RouterLink to="/register" class="btn-institutional shrink-0">
                        Join us
                    </RouterLink>
                </div>
            </div>
        </section>
    </div>
</template>
