<script setup>
import { ref, onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import { DocumentTextIcon } from '@heroicons/vue/24/outline';
import AdminEmptyState from '../../../components/admin/AdminEmptyState.vue';
import AdminPageIntro from '../../../components/admin/AdminPageIntro.vue';
import JournalStatusBadge from '../../../components/journal/JournalStatusBadge.vue';
import { formatJournalDate } from '../../../utils/journal';
import { useAuth } from '../../../composables/useAuth';

const { getJournalClient, fetchAdminProfile, canJournalAssign } = useAuth();
const submissions = ref([]);
const loading = ref(true);

onMounted(async () => {
    loading.value = true;
    await fetchAdminProfile();
    const { data } = await getJournalClient().get('/submissions/review');
    submissions.value = data.data || [];
    loading.value = false;
});
</script>

<template>
    <div>
        <AdminPageIntro description="Manage editorial workflow, reviewers, and publication visibility.">
            <template #actions>
                <RouterLink v-if="canJournalAssign()" to="/admin/journal/volumes-issues" class="btn-secondary w-full !rounded-xl text-center sm:w-auto">
                    Volumes & issues
                </RouterLink>
                <RouterLink v-if="canJournalAssign()" to="/admin/journal/calls-for-papers" class="btn-secondary w-full !rounded-xl text-center sm:w-auto">
                    Calls for papers
                </RouterLink>
                <RouterLink v-if="canJournalAssign()" to="/admin/journal/editorial-board" class="btn-secondary w-full !rounded-xl text-center sm:w-auto">
                    Editorial board
                </RouterLink>
                <RouterLink v-if="canJournalAssign()" to="/admin/journal/categories" class="btn-secondary w-full !rounded-xl text-center sm:w-auto">
                    Categories
                </RouterLink>
                <RouterLink v-if="canJournalAssign()" to="/admin/journal/reviewers" class="btn-secondary w-full !rounded-xl text-center sm:w-auto">
                    Manage reviewers
                </RouterLink>
            </template>
        </AdminPageIntro>

        <div v-if="loading" class="empty-state bg-white">Loading submissions...</div>

        <AdminEmptyState
            v-else-if="!submissions.length"
            title="No submissions yet"
            description="Journal submissions from members will appear here for review."
        >
            <template #icon><DocumentTextIcon class="size-6" /></template>
        </AdminEmptyState>

        <template v-else>
            <div class="divide-y divide-slate-100 md:hidden admin-panel">
                <article v-for="item in submissions" :key="item.id" class="space-y-3 p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-medium text-slate-900">{{ item.title }}</p>
                            <p class="mt-1 text-sm text-slate-600">{{ item.author_name }}</p>
                        </div>
                        <JournalStatusBadge :status="item.status" />
                    </div>
                    <p class="text-xs text-slate-500">Submitted {{ formatJournalDate(item.date_submitted) }}</p>
                    <RouterLink :to="`/admin/journal/${item.slug || item.id}`" class="text-sm font-semibold text-institutional hover:underline">
                        Manage submission
                    </RouterLink>
                </article>
            </div>

            <div class="hidden overflow-x-auto md:block admin-panel">
                <table class="min-w-[640px] w-full text-sm">
                    <thead class="border-b border-slate-100 bg-slate-50 text-left text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Title</th>
                            <th class="px-4 py-3">Author</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Submitted</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in submissions" :key="item.id" class="border-b border-slate-50 hover:bg-slate-50/60">
                            <td class="px-4 py-3 font-medium text-slate-900">{{ item.title }}</td>
                            <td class="px-4 py-3">{{ item.author_name }}</td>
                            <td class="px-4 py-3"><JournalStatusBadge :status="item.status" /></td>
                            <td class="px-4 py-3">{{ formatJournalDate(item.date_submitted) }}</td>
                            <td class="px-4 py-3 text-right">
                                <RouterLink :to="`/admin/journal/${item.slug || item.id}`" class="font-medium text-brand-600 hover:underline">Manage</RouterLink>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>
    </div>
</template>
