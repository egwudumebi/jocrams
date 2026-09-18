import { computed, ref } from 'vue';
import { publicApi } from '../api/client';

const orgInfo = ref(null);
const loading = ref(false);
const loaded = ref(false);

export function useOrgInfo() {
    async function loadOrgInfo(force = false) {
        if (loaded.value && !force) {
            return orgInfo.value;
        }

        loading.value = true;

        try {
            const { data } = await publicApi().get('/org-info');
            orgInfo.value = data.data;
            loaded.value = true;
        } catch {
            orgInfo.value = null;
        } finally {
            loading.value = false;
        }

        return orgInfo.value;
    }

    const bank = computed(() => orgInfo.value?.bank || null);
    const social = computed(() => orgInfo.value?.social || {});
    const contact = computed(() => orgInfo.value?.contact || null);
    const fees = computed(() => orgInfo.value?.fees || null);
    const journal = computed(() => orgInfo.value?.journal || null);

    const hasSocialLinks = computed(() =>
        Object.values(social.value || {}).some((url) => typeof url === 'string' && url.trim() !== ''),
    );

    return {
        orgInfo,
        loading,
        bank,
        social,
        contact,
        fees,
        journal,
        hasSocialLinks,
        loadOrgInfo,
    };
}
