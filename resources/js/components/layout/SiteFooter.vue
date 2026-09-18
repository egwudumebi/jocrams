<script setup>
import { computed, onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import { useSiteBranding } from '../../composables/useSiteBranding';
import { useOrgInfo } from '../../composables/useOrgInfo';

const { branding, loadBranding } = useSiteBranding();
const { social, contact, bank, hasSocialLinks, loadOrgInfo } = useOrgInfo();

const quickLinks = [
    { to: '/', label: 'About' },
    { to: '/sicama', label: 'SICAMA' },
    { to: '/news', label: 'News' },
    { to: '/events', label: 'Events' },
    { to: '/downloads', label: 'Library' },
    { to: '/journal', label: 'Journal' },
    { to: '/journal/author-guidelines', label: 'Author Guidelines' },
    { to: '/contact', label: 'Contact' },
];

const memberLinks = [
    { to: '/member/login', label: 'Member Login' },
    { to: '/member/register', label: 'Join / Register' },
    { to: '/verify', label: 'Verify Credential' },
];

const socialItems = computed(() => {
    const links = social.value || {};

    return [
        { key: 'facebook', label: 'Facebook', href: links.facebook },
        { key: 'linkedin', label: 'LinkedIn', href: links.linkedin },
        { key: 'x', label: 'X', href: links.x },
        { key: 'instagram', label: 'Instagram', href: links.instagram },
        { key: 'youtube', label: 'YouTube', href: links.youtube },
    ].filter((item) => typeof item.href === 'string' && item.href.trim() !== '');
});

onMounted(() => {
    loadBranding();
    loadOrgInfo();
});
</script>

<template>
    <footer class="border-t border-white/10 bg-institutional-dark text-slate-400">
        <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
            <div class="grid gap-10 sm:grid-cols-2 lg:grid-cols-4">
                <div class="sm:col-span-2 lg:col-span-1">
                    <RouterLink to="/" class="inline-flex items-center gap-3">
                        <span class="flex size-11 items-center justify-center overflow-hidden rounded-lg bg-white">
                            <img
                                :src="branding?.parent_org?.logo_url || '/images/sicama-logo.png'"
                                :alt="`${branding?.parent_org?.short_name || 'SICAMA'} logo`"
                                class="size-10 object-contain p-0.5"
                                @error="$event.target.src = '/images/sicama-logo.png'"
                            />
                        </span>
                        <span class="min-w-0">
                            <span class="block font-display text-lg font-bold text-white">{{ branding?.site_name || 'JOCRAMS' }}</span>
                            <span class="block text-xs text-slate-400">{{ branding?.parent_org?.short_name || 'SICAMA' }}</span>
                        </span>
                    </RouterLink>
                    <p class="mt-4 max-w-xs text-sm leading-relaxed italic text-slate-300">
                        “{{ branding?.parent_org?.motto }}”
                    </p>
                    <p class="mt-3 max-w-xs text-sm leading-relaxed">
                        {{ branding?.journal_full_name || branding?.site_tagline }}
                    </p>
                    <div v-if="hasSocialLinks" class="mt-5 flex flex-wrap gap-2">
                        <a
                            v-for="item in socialItems"
                            :key="item.key"
                            :href="item.href"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="rounded-full border border-white/15 px-3 py-1.5 text-xs font-semibold text-white/80 transition hover:border-accent-gold hover:text-accent-gold"
                        >
                            {{ item.label }}
                        </a>
                    </div>
                    <p v-else class="mt-4 text-xs text-slate-500">
                        Social profiles can be added in Admin → Settings.
                    </p>
                </div>

                <div>
                    <h3 class="label-caps !text-slate-300">Quick Links</h3>
                    <ul class="mt-4 space-y-2.5">
                        <li v-for="link in quickLinks" :key="link.to">
                            <RouterLink :to="link.to" class="text-sm transition hover:text-white">
                                {{ link.label }}
                            </RouterLink>
                        </li>
                    </ul>
                </div>

                <div>
                    <h3 class="label-caps !text-slate-300">Members</h3>
                    <ul class="mt-4 space-y-2.5">
                        <li v-for="link in memberLinks" :key="link.to">
                            <RouterLink :to="link.to" class="text-sm transition hover:text-white">
                                {{ link.label }}
                            </RouterLink>
                        </li>
                    </ul>
                </div>

                <div>
                    <h3 class="label-caps !text-slate-300">Contact & Payments</h3>
                    <ul class="mt-4 space-y-2.5 text-sm">
                        <li>Nigeria</li>
                        <li>
                            <a
                                :href="`mailto:${contact?.email || 'jocrams2026@gmail.com'}`"
                                class="transition hover:text-white"
                            >
                                {{ contact?.email || 'jocrams2026@gmail.com' }}
                            </a>
                        </li>
                        <li v-if="bank?.account_number" class="text-slate-500">
                            UBA · {{ bank.account_number }}
                        </li>
                        <li>
                            <RouterLink to="/contact" class="transition hover:text-white">Send a message →</RouterLink>
                        </li>
                        <li>
                            <RouterLink to="/journal/editorial-board" class="transition hover:text-white">Editorial team →</RouterLink>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="mt-12 flex flex-col gap-4 border-t border-white/10 pt-8 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm">
                    &copy; {{ new Date().getFullYear() }}
                    {{ branding?.parent_org?.short_name || 'SICAMA' }} · {{ branding?.site_name || 'JOCRAMS' }}.
                    All rights reserved.
                </p>
                <div class="flex flex-wrap gap-x-6 gap-y-2 text-sm">
                    <RouterLink to="/sicama" class="transition hover:text-white">Governance</RouterLink>
                    <RouterLink to="/journal/author-guidelines" class="transition hover:text-white">Author Guidelines</RouterLink>
                    <RouterLink to="/contact" class="transition hover:text-white">Help & Support</RouterLink>
                </div>
            </div>
        </div>
    </footer>
</template>
