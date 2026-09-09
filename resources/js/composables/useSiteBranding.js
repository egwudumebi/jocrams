import { onMounted, ref } from 'vue';
import { publicApi } from '../api/client';

const branding = ref(null);
const sicama = ref(null);
let brandingPromise = null;
let sicamaPromise = null;

const defaults = {
    site_name: 'JOCRAMS',
    site_tagline: 'Journal of Communication Research and Media Studies',
    site_logo_url: '/images/sicama-logo.png',
    journal_full_name: 'Journal of Communication Research and Media Studies',
    parent_org: {
        short_name: 'SICAMA',
        full_name: 'Scholars in Communication and Media Advancement Initiative',
        motto: 'Advancing Communication and Media Scholarship for the good of the society.',
        logo_url: '/images/sicama-logo.png',
    },
};

export function useSiteBranding() {
    async function loadBranding() {
        if (branding.value) {
            return branding.value;
        }

        if (!brandingPromise) {
            brandingPromise = publicApi()
                .get('/site-branding')
                .then(({ data }) => {
                    branding.value = {
                        ...defaults,
                        ...data.data,
                        parent_org: {
                            ...defaults.parent_org,
                            ...(data.data?.parent_org || {}),
                        },
                    };

                    if (!branding.value.site_logo_url) {
                        branding.value.site_logo_url = defaults.site_logo_url;
                    }

                    if (!branding.value.parent_org?.logo_url) {
                        branding.value.parent_org.logo_url = defaults.parent_org.logo_url;
                    }

                    return branding.value;
                })
                .catch(() => {
                    branding.value = defaults;
                    return branding.value;
                });
        }

        return brandingPromise;
    }

    async function loadSicama() {
        if (sicama.value) {
            return sicama.value;
        }

        if (!sicamaPromise) {
            sicamaPromise = publicApi()
                .get('/sicama')
                .then(({ data }) => {
                    sicama.value = data.data;
                    return sicama.value;
                })
                .catch(() => null);
        }

        return sicamaPromise;
    }

    onMounted(() => {
        loadBranding();
    });

    return {
        branding,
        sicama,
        loadBranding,
        loadSicama,
        defaults,
    };
}
