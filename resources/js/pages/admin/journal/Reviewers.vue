<script setup>
import { ref, onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import { useAuth } from '../../../composables/useAuth';

const { getJournalClient, fetchAdminProfile } = useAuth();
const reviewers = ref([]);
const form = ref({ name: '', email: '' });
const message = ref('');
const tempPassword = ref('');

onMounted(async () => {
    await fetchAdminProfile();
    await loadReviewers();
});

async function loadReviewers() {
    const { data } = await getJournalClient().get('/admin/reviewers');
    reviewers.value = data.data || [];
}

async function createReviewer() {
    const { data } = await getJournalClient().post('/admin/reviewers', form.value);
    message.value = data.message;
    tempPassword.value = data.temporary_password || '';
    form.value = { name: '', email: '' };
    await loadReviewers();
}
</script>

<template>
    <div>
        <RouterLink to="/admin/journal" class="text-sm text-brand-600">← Journal submissions</RouterLink>
        <h1 class="mt-2 text-2xl font-bold text-slate-900">Journal Reviewers</h1>
        <p class="text-sm text-slate-600">Create reviewers or grant reviewer access to existing users.</p>

        <div class="mt-6 card p-6">
            <h2 class="font-semibold">Add reviewer</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label">Name</label>
                    <input v-model="form.name" class="input" />
                </div>
                <div>
                    <label class="label">Email</label>
                    <input v-model="form.email" type="email" class="input" />
                </div>
            </div>
            <button class="btn-primary mt-4" :disabled="!form.name || !form.email" @click="createReviewer">Create / assign reviewer</button>
            <p v-if="message" class="mt-3 text-sm text-green-600">{{ message }}</p>
            <p v-if="tempPassword" class="mt-2 text-sm text-amber-700">Temporary password: {{ tempPassword }}</p>
        </div>

        <div class="mt-6 card divide-y divide-slate-100">
            <div v-for="reviewer in reviewers" :key="reviewer.id" class="flex items-center justify-between p-4">
                <div>
                    <p class="font-medium">{{ reviewer.name }}</p>
                    <p class="text-sm text-slate-500">{{ reviewer.email }}</p>
                </div>
            </div>
        </div>
    </div>
</template>
