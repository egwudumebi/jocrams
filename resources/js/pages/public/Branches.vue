<script setup>
import { ref, onMounted } from 'vue';
import { publicApi } from '../../api/client';

const branches = ref([]);

onMounted(async () => {
    const { data } = await publicApi().get('/branches');
    branches.value = data.data;
});
</script>

<template>
    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
        <h1 class="text-3xl font-bold text-slate-900">Branch Directory</h1>
        <p class="mt-1 text-slate-600">Find a branch near you</p>

        <div class="mt-8 grid gap-6 sm:grid-cols-2">
            <div v-for="branch in branches" :key="branch.uuid" class="card p-6">
                <h2 class="text-lg font-semibold text-slate-900">{{ branch.name }}</h2>
                <p class="mt-2 text-sm text-slate-600">{{ branch.address }}, {{ branch.city }}, {{ branch.state }}</p>
                <dl class="mt-4 space-y-1 text-sm">
                    <div v-if="branch.phone"><dt class="inline font-medium">Phone: </dt><dd class="inline text-slate-600">{{ branch.phone }}</dd></div>
                    <div v-if="branch.email"><dt class="inline font-medium">Email: </dt><dd class="inline text-slate-600">{{ branch.email }}</dd></div>
                    <div v-if="branch.contact_person"><dt class="inline font-medium">Contact: </dt><dd class="inline text-slate-600">{{ branch.contact_person }}</dd></div>
                </dl>
            </div>
        </div>
    </div>
</template>
