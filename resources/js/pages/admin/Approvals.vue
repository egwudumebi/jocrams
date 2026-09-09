<script setup>
import { ref, onMounted } from 'vue';
import { ClipboardDocumentCheckIcon } from '@heroicons/vue/24/outline';
import AdminAlert from '../../components/admin/AdminAlert.vue';
import AdminEmptyState from '../../components/admin/AdminEmptyState.vue';
import AdminPageIntro from '../../components/admin/AdminPageIntro.vue';
import { useAuth } from '../../composables/useAuth';

const { getAdminClient } = useAuth();
const approvals = ref([]);
const message = ref('');

onMounted(load);

async function load() {
    const { data } = await getAdminClient().get('/approvals');
    approvals.value = data.data || [];
}

async function approve(app) {
    await getAdminClient().post(`/applications/${app.approvable.uuid}/approve`, { notes: 'Approved via admin panel' });
    message.value = 'Application approved.';
    await load();
}

async function reject(app) {
    const reason = prompt('Rejection reason:');
    if (!reason) return;
    await getAdminClient().post(`/applications/${app.approvable.uuid}/reject`, { reason });
    message.value = 'Application rejected.';
    await load();
}
</script>

<template>
    <div>
        <AdminPageIntro description="Review and action membership applications awaiting admin approval." />

        <AdminAlert v-if="message" type="success">{{ message }}</AdminAlert>

        <AdminEmptyState
            v-if="approvals.length === 0"
            title="No pending approvals"
            description="New applications will appear here when members submit membership requests."
        >
            <template #icon><ClipboardDocumentCheckIcon class="size-6" /></template>
        </AdminEmptyState>

        <div v-else class="space-y-4">
            <article
                v-for="item in approvals"
                :key="item.uuid"
                class="admin-panel"
            >
                <div class="admin-list-item">
                    <div class="min-w-0">
                        <p class="font-medium text-slate-900">{{ item.approvable?.applicant_name }}</p>
                        <p class="mt-1 text-sm text-slate-600">{{ item.approvable?.applicant_email }}</p>
                        <p class="mt-2 text-xs text-slate-400">{{ item.type }} · {{ new Date(item.created_at).toLocaleDateString() }}</p>
                    </div>
                    <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row">
                        <button class="btn-primary w-full !rounded-xl sm:w-auto" @click="approve(item)">Approve</button>
                        <button class="btn-danger w-full !rounded-xl sm:w-auto" @click="reject(item)">Reject</button>
                    </div>
                </div>
            </article>
        </div>
    </div>
</template>
