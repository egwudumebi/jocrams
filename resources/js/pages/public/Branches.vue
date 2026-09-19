<script setup>
import { computed, onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { MapPinIcon } from '@heroicons/vue/24/outline';
import { publicApi } from '../../api/client';
import { useOrgInfo } from '../../composables/useOrgInfo';

const branches = ref([]);
const { contact, loadOrgInfo } = useOrgInfo();

const officeAddress = computed(() => {
    const address = contact.value?.address || {};
    const lines = [
        address.line1,
        address.line2,
        address.line3,
        address.line4,
    ].filter(Boolean);

    return lines.length
        ? lines
        : [
            'Room 1, Department of Mass Communication',
            'Faculty of Communication and Media Studies',
            'University of Calabar',
            'Calabar, Nigeria',
        ];
});

onMounted(async () => {
    await loadOrgInfo();
    try {
        const { data } = await publicApi().get('/branches');
        branches.value = data.data || [];
    } catch {
        branches.value = [];
    }
});
</script>

<template>
    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
        <h1 class="text-3xl font-bold text-slate-900">Editorial Office</h1>
        <p class="mt-1 text-slate-600">JOCRAMS operates from a single editorial office. There are no branch offices.</p>

        <div class="mt-8 max-w-xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex gap-3">
                <MapPinIcon class="mt-0.5 size-5 shrink-0 text-institutional" aria-hidden="true" />
                <div class="text-sm leading-relaxed text-slate-600">
                    <p v-for="line in officeAddress" :key="line">{{ line }}</p>
                    <p class="mt-3">
                        <a
                            :href="`mailto:${contact?.email || 'jocrams2026@gmail.com'}`"
                            class="text-institutional hover:underline"
                        >
                            {{ contact?.email || 'jocrams2026@gmail.com' }}
                        </a>
                    </p>
                </div>
            </div>
            <RouterLink to="/contact" class="btn-institutional mt-6 inline-flex text-sm">
                Contact us
            </RouterLink>
        </div>

        <div v-if="branches.length" class="mt-10 grid gap-6 sm:grid-cols-2">
            <div v-for="branch in branches" :key="branch.uuid" class="card p-6">
                <h2 class="text-lg font-semibold text-slate-900">{{ branch.name }}</h2>
                <p class="mt-2 text-sm text-slate-600">{{ branch.address }}, {{ branch.city }}, {{ branch.state }}</p>
            </div>
        </div>
    </div>
</template>
