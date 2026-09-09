<script setup>
import { ref, onMounted } from 'vue';
import { ArrowPathIcon } from '@heroicons/vue/24/outline';
import AdminEmptyState from '../../components/admin/AdminEmptyState.vue';
import AdminPageIntro from '../../components/admin/AdminPageIntro.vue';
import AdminToolbar from '../../components/admin/AdminToolbar.vue';
import { useAuth } from '../../composables/useAuth';

const { getAdminClient } = useAuth();
const members = ref([]);
const loading = ref(true);

onMounted(load);

async function load() {
    loading.value = true;
    try {
        const { data } = await getAdminClient().get('/members', { params: { expiring_within_days: 60 } });
        members.value = data.data || [];
    } finally {
        loading.value = false;
    }
}

function formatDate(value) {
    return value ? new Date(value).toLocaleDateString() : '—';
}
</script>

<template>
    <div>
        <AdminPageIntro description="Monitor memberships approaching renewal and follow up before they lapse. Showing members expiring within the next 60 days." />

        <AdminToolbar>
            <template #actions>
                <button type="button" class="btn-secondary w-full !rounded-xl sm:w-auto" @click="load">Refresh</button>
            </template>
        </AdminToolbar>

        <div v-if="loading" class="empty-state bg-white">Loading renewals...</div>

        <AdminEmptyState
            v-else-if="!members.length"
            title="No renewals due soon"
            description="Active members with upcoming expiry dates will appear here."
        >
            <template #icon><ArrowPathIcon class="size-6" /></template>
        </AdminEmptyState>

        <template v-else>
            <div class="divide-y divide-slate-100 md:hidden admin-panel">
                <article v-for="member in members" :key="member.uuid" class="space-y-2 p-4">
                    <p class="font-medium text-slate-900">{{ member.user?.name }}</p>
                    <p class="text-sm text-slate-600">{{ member.tier?.name || 'No tier' }}</p>
                    <dl class="grid grid-cols-2 gap-2 text-xs text-slate-500">
                        <div><dt class="uppercase tracking-wide">Member #</dt><dd class="mt-0.5 font-mono">{{ member.membership_number || 'Pending' }}</dd></div>
                        <div><dt class="uppercase tracking-wide">Expires</dt><dd class="mt-0.5">{{ formatDate(member.expires_at) }}</dd></div>
                    </dl>
                    <span class="badge capitalize">{{ member.status }}</span>
                </article>
            </div>

            <div class="hidden overflow-x-auto md:block admin-panel">
                <table class="min-w-[640px] w-full text-left text-sm">
                    <thead class="border-b border-slate-100 bg-slate-50/80 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Member #</th>
                            <th class="px-5 py-3 font-semibold">Name</th>
                            <th class="px-5 py-3 font-semibold">Tier</th>
                            <th class="px-5 py-3 font-semibold">Expires</th>
                            <th class="px-5 py-3 font-semibold">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="member in members" :key="member.uuid" class="hover:bg-slate-50/60">
                            <td class="px-5 py-4 font-mono text-xs">{{ member.membership_number || 'Pending' }}</td>
                            <td class="px-5 py-4 font-medium text-slate-900">{{ member.user?.name }}</td>
                            <td class="px-5 py-4 text-slate-600">{{ member.tier?.name || '—' }}</td>
                            <td class="px-5 py-4 text-slate-600">{{ formatDate(member.expires_at) }}</td>
                            <td class="px-5 py-4"><span class="badge capitalize">{{ member.status }}</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>
    </div>
</template>
