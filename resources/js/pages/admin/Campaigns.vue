<script setup>
import { ref, onMounted } from 'vue';
import { useAuth } from '../../composables/useAuth';

const { getAdminClient } = useAuth();
const campaigns = ref([]);
const form = ref({
    name: '',
    channel: 'email',
    subject: '',
    body: '',
    audience_filter: { all_active_members: true },
});
const message = ref('');

onMounted(load);

async function load() {
    const { data } = await getAdminClient().get('/bulk-campaigns');
    campaigns.value = data.data;
}

async function create() {
    await getAdminClient().post('/bulk-campaigns', form.value);
    message.value = 'Campaign draft created.';
    form.value = { name: '', channel: 'email', subject: '', body: '', audience_filter: { all_active_members: true } };
    await load();
}

async function dispatch(c) {
    await getAdminClient().post(`/bulk-campaigns/${c.uuid}/dispatch`);
    message.value = 'Campaign dispatched.';
    await load();
}
</script>

<template>
    <div>
        <h1 class="text-2xl font-bold text-slate-900">Bulk Campaigns</h1>
        <p class="text-slate-600">Send targeted email or SMS announcements</p>
        <p v-if="message" class="mt-2 text-sm text-green-600">{{ message }}</p>

        <div class="mt-6 card p-6">
            <h2 class="font-semibold">New Campaign</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <input v-model="form.name" placeholder="Campaign name" class="input" />
                <select v-model="form.channel" class="input">
                    <option value="email">Email</option>
                    <option value="sms">SMS</option>
                </select>
                <input v-if="form.channel === 'email'" v-model="form.subject" placeholder="Subject" class="input sm:col-span-2" />
                <textarea v-model="form.body" placeholder="Message body. Use {{name}} for personalization." rows="4" class="input sm:col-span-2" />
            </div>
            <button class="btn-primary mt-4" @click="create">Create Draft</button>
        </div>

        <div class="mt-6 space-y-3">
            <div v-for="c in campaigns" :key="c.uuid" class="card flex items-center justify-between p-4">
                <div>
                    <p class="font-medium">{{ c.name }}</p>
                    <p class="text-sm text-slate-500 capitalize">{{ c.channel }} — {{ c.status }} — {{ c.total_recipients }} recipients</p>
                </div>
                <button v-if="c.status === 'draft'" class="btn-secondary text-sm" @click="dispatch(c)">Send Now</button>
            </div>
        </div>
    </div>
</template>
