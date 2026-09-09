<script setup>
import { ref, onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import { useAuth } from '../../../composables/useAuth';
import JournalStatusBadge from '../../../components/journal/JournalStatusBadge.vue';
import { formatJournalDate } from '../../../utils/journal';

const { getJournalClient, fetchMemberProfile, canJournalReview } = useAuth();
const queue = ref([]);

onMounted(async () => {
    await fetchMemberProfile();
    const { data } = await getJournalClient().get('/queue');
    queue.value = data.data || [];
});
</script>

<template>
    <div>
        <RouterLink to="/member/journal" class="text-sm text-brand-600">← Journal home</RouterLink>
        <h1 class="mt-2 text-2xl font-bold text-slate-900">Reviewer Queue</h1>
        <p class="text-sm text-slate-600">Manuscripts assigned to you for editorial review.</p>

        <div v-if="!canJournalReview()" class="mt-6 card p-6 text-sm text-slate-600">
            You do not have reviewer access.
        </div>

        <div v-else-if="queue.length === 0" class="mt-8 card p-8 text-center text-slate-500">No manuscripts in your queue.</div>

        <div v-else class="mt-6 space-y-4">
            <RouterLink
                v-for="item in queue"
                :key="item.id"
                :to="`/member/journal/review/${item.slug || item.id}`"
                class="card block p-5 transition hover:border-brand-200"
            >
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="font-semibold text-slate-900">{{ item.title }}</h2>
                        <p class="mt-1 text-sm text-slate-500">{{ item.author_name }} · Priority: {{ item.priority || 'normal' }}</p>
                        <p v-if="item.due_at" class="mt-1 text-xs text-slate-400">Due {{ formatJournalDate(item.due_at) }}</p>
                    </div>
                    <JournalStatusBadge :status="item.status" />
                </div>
            </RouterLink>
        </div>
    </div>
</template>
