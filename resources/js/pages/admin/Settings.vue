<script setup>
import { computed, ref, onMounted, watch } from 'vue';
import { RouterLink } from 'vue-router';
import { Cog6ToothIcon, NewspaperIcon } from '@heroicons/vue/24/outline';
import AdminAlert from '../../components/admin/AdminAlert.vue';
import AdminImageUpload from '../../components/admin/AdminImageUpload.vue';
import AdminPageIntro from '../../components/admin/AdminPageIntro.vue';
import { useAuth } from '../../composables/useAuth';

const { getAdminClient } = useAuth();

const tabs = [
    { id: 'general', label: 'General' },
    { id: 'notifications', label: 'Notifications' },
    { id: 'membership', label: 'Membership' },
    { id: 'payment', label: 'Payment' },
    { id: 'security', label: 'Security' },
    { id: 'signatory', label: 'Signatory' },
    { id: 'users', label: 'Admin Users' },
];

const fieldLabels = {
    site_name: 'Site name',
    site_tagline: 'Site tagline',
    site_banner_enabled: 'Show site banner',
    site_banner_message: 'Banner message',
    site_banner_link: 'Banner link',
    site_banner_link_label: 'Banner link label',
    site_banner_style: 'Banner style',
    contact_email: 'Contact email',
    support_email: 'Support email',
    social_facebook: 'Facebook URL',
    social_linkedin: 'LinkedIn URL',
    social_x: 'X (Twitter) URL',
    social_instagram: 'Instagram URL',
    social_youtube: 'YouTube URL',
    timezone: 'Timezone',
    language: 'Language',
    app_name: 'App name',
    email_notifications: 'Email notifications',
    new_member_alerts: 'New member alerts',
    payment_notifications: 'Payment notifications',
    journal_submission_alerts: 'Journal submission alerts',
    event_registration_alerts: 'Event registration alerts',
    system_updates: 'System update alerts',
    auto_approval: 'Auto-approve applications',
    fee_student_ngn: 'SICAMA membership fee (NGN)',
    fee_professional_ngn: 'Membership fee reference (NGN)',
    fee_institutional_ngn: 'Institutional / special fee (NGN)',
    duration_months: 'Membership duration (months)',
    renewal_reminder_days: 'Renewal reminder (days)',
    currency: 'Currency',
    bank_name: 'Bank name',
    account_name: 'Account name',
    account_number: 'Account number',
    payment_gateway: 'Payment gateway',
    public_key: 'Public key',
    secret_key: 'Secret key',
    password_min_length: 'Password min length',
    password_expiry_days: 'Password expiry (days)',
    minimum_password_length: 'Minimum password length',
    require_special_characters: 'Require special characters',
    require_numbers: 'Require numbers',
    require_uppercase_letters: 'Require uppercase letters',
    session_timeout_minutes: 'Session timeout (minutes)',
    max_login_attempts: 'Max login attempts',
    enable_two_factor_authentication: 'Enable two-factor authentication',
    signatory_name: 'Signatory name',
    signatory_title: 'Signatory title',
    signature_image_path: 'Signature image path',
};

const booleanKeys = new Set([
    'site_banner_enabled',
    'email_notifications',
    'new_member_alerts',
    'payment_notifications',
    'journal_submission_alerts',
    'event_registration_alerts',
    'system_updates',
    'auto_approval',
    'require_special_characters',
    'require_numbers',
    'require_uppercase_letters',
    'enable_two_factor_authentication',
]);

const hiddenKeys = new Set([
    'site_logo_path',
    'site_banner_enabled',
    'site_banner_message',
    'site_banner_link',
    'site_banner_link_label',
    'site_banner_style',
]);

const activeTab = ref('general');
const values = ref({});
const templates = ref([]);
const loading = ref(false);
const saving = ref(false);
const logoUploading = ref(false);
const siteLogoUrl = ref('');
const message = ref('');
const error = ref('');

const roleForm = ref({ name: '', email: '', role_slug: 'super-admin' });
const roleMessage = ref('');
const roleError = ref('');

const newsArticles = ref([]);
const newsLoading = ref(false);
const bannerLinkMode = ref('none'); // none | news | custom
const selectedNewsUuid = ref('');

const newsPathPattern = /^\/news\/([0-9a-f-]{36})$/i;

const selectedNewsArticle = computed(() => (
    newsArticles.value.find((article) => article.uuid === selectedNewsUuid.value) || null
));

const bannerLinkPreview = computed(() => {
    const link = String(values.value.site_banner_link || '').trim();
    if (!link) {
        return 'No “Read more” link — banner text only.';
    }
    if (selectedNewsArticle.value) {
        return `Opens news article: ${selectedNewsArticle.value.title}`;
    }
    return `Opens: ${link}`;
});

onMounted(async () => {
    await Promise.all([loadGroup('general'), loadTemplates(), loadNewsArticles()]);
});

watch(activeTab, async (tab) => {
    if (tab !== 'users') {
        await loadGroup(tab);
    }
    if (tab === 'general') {
        await loadNewsArticles();
    }
});

async function loadTemplates() {
    try {
        const { data } = await getAdminClient().get('/notification-templates');
        templates.value = data.data || [];
    } catch {
        templates.value = [];
    }
}

async function loadNewsArticles() {
    newsLoading.value = true;
    try {
        const { data } = await getAdminClient().get('/news-articles', {
            params: { status: 'published', per_page: 100 },
        });
        newsArticles.value = data.data || [];
        syncBannerLinkUi();
    } catch {
        newsArticles.value = [];
    } finally {
        newsLoading.value = false;
    }
}

function syncBannerLinkUi() {
    const link = String(values.value.site_banner_link || '').trim();
    if (!link) {
        bannerLinkMode.value = 'none';
        selectedNewsUuid.value = '';
        return;
    }

    const match = link.match(newsPathPattern);
    if (match) {
        bannerLinkMode.value = 'news';
        selectedNewsUuid.value = match[1];
        return;
    }

    bannerLinkMode.value = 'custom';
    selectedNewsUuid.value = '';
}

function onBannerLinkModeChange() {
    if (bannerLinkMode.value === 'none') {
        values.value.site_banner_link = '';
        selectedNewsUuid.value = '';
        return;
    }

    if (bannerLinkMode.value === 'news') {
        if (selectedNewsUuid.value) {
            applySelectedNews(selectedNewsUuid.value);
            return;
        }
        const first = newsArticles.value[0];
        if (first) {
            applySelectedNews(first.uuid);
        } else {
            values.value.site_banner_link = '';
        }
        return;
    }

    // custom — keep current value if it isn't a news path, otherwise clear for typing
    if (newsPathPattern.test(String(values.value.site_banner_link || ''))) {
        values.value.site_banner_link = '';
    }
    selectedNewsUuid.value = '';
}

function applySelectedNews(uuid) {
    selectedNewsUuid.value = uuid;
    values.value.site_banner_link = uuid ? `/news/${uuid}` : '';
    if (!String(values.value.site_banner_link_label || '').trim()) {
        values.value.site_banner_link_label = 'Read full announcement';
    }
}

async function loadGroup(group) {
    loading.value = true;
    error.value = '';
    try {
        const { data } = await getAdminClient().get(`/settings/${group}`);
        const next = {};
        for (const item of data.data || []) {
            next[item.key] = booleanKeys.has(item.key)
                ? item.value === '1' || item.value === true
                : item.value ?? '';
        }
        values.value = next;
        if (group === 'general') {
            siteLogoUrl.value = logoUrlFromPath(next.site_logo_path);
            syncBannerLinkUi();
        }
    } catch (err) {
        error.value = err.response?.data?.message || 'Unable to load settings.';
    } finally {
        loading.value = false;
    }
}

async function saveGroup() {
    if (activeTab.value === 'users') return;

    saving.value = true;
    message.value = '';
    error.value = '';

    try {
        await getAdminClient().post(`/settings/${activeTab.value}`, { values: values.value });
        message.value = 'Settings saved.';
        await loadGroup(activeTab.value);
    } catch (err) {
        error.value = err.response?.data?.message || 'Unable to save settings.';
    } finally {
        saving.value = false;
    }
}

async function createUserRole() {
    roleMessage.value = '';
    roleError.value = '';
    saving.value = true;

    try {
        const { data } = await getAdminClient().post('/settings/user-roles', roleForm.value);
        roleMessage.value = `${data.message} Temporary password: ${data.temporary_password}`;
        roleForm.value = { name: '', email: '', role_slug: 'super-admin' };
    } catch (err) {
        roleError.value = err.response?.data?.message || 'Unable to create user.';
    } finally {
        saving.value = false;
    }
}

function labelFor(key) {
    return fieldLabels[key] || key.replaceAll('_', ' ');
}

function isBoolean(key) {
    return booleanKeys.has(key);
}

function isHidden(key) {
    return hiddenKeys.has(key);
}

function logoUrlFromPath(path) {
    if (!path) {
        return '';
    }

    return `/storage/${String(path).replace(/^storage\//, '')}`;
}

async function uploadSiteLogo(file) {
    if (!file) {
        return;
    }

    logoUploading.value = true;
    error.value = '';
    message.value = '';

    try {
        const formData = new FormData();
        formData.append('logo', file);
        const { data } = await getAdminClient().post('/settings/general/logo', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        values.value.site_logo_path = data.path;
        siteLogoUrl.value = data.url || logoUrlFromPath(data.path);
        message.value = data.message || 'Site logo uploaded.';
    } catch (err) {
        error.value = err.response?.data?.message || 'Unable to upload site logo.';
    } finally {
        logoUploading.value = false;
    }
}

async function removeSiteLogo() {
    logoUploading.value = true;
    error.value = '';
    message.value = '';

    try {
        const { data } = await getAdminClient().delete('/settings/general/logo');
        values.value.site_logo_path = '';
        siteLogoUrl.value = '';
        message.value = data.message || 'Site logo removed.';
    } catch (err) {
        error.value = err.response?.data?.message || 'Unable to remove site logo.';
    } finally {
        logoUploading.value = false;
    }
}
</script>

<template>
    <div>
        <AdminPageIntro description="Configure grouped system settings for general site info, notifications, membership, payments, security, and certificate signatory details." />

        <AdminAlert v-if="message" type="success">{{ message }}</AdminAlert>
        <AdminAlert v-if="error" type="error">{{ error }}</AdminAlert>

        <div class="admin-tabs">
            <button
                v-for="tab in tabs"
                :key="tab.id"
                type="button"
                class="admin-tab"
                :class="activeTab === tab.id ? 'admin-tab-active' : 'admin-tab-inactive'"
                @click="activeTab = tab.id"
            >
                {{ tab.label }}
            </button>
        </div>

        <div v-if="activeTab === 'users'" class="grid gap-6 xl:grid-cols-2">
            <section class="admin-panel">
                <div class="admin-panel-header !border-b">
                    <div>
                        <h2 class="admin-panel-title">Create admin user</h2>
                        <p class="admin-panel-description">Creates a new user and assigns a role with a temporary password.</p>
                    </div>
                </div>
                <div class="admin-panel-body">
                <AdminAlert v-if="roleMessage" type="success">{{ roleMessage }}</AdminAlert>
                <AdminAlert v-if="roleError" type="error">{{ roleError }}</AdminAlert>
                <form class="space-y-4" @submit.prevent="createUserRole">
                    <input v-model="roleForm.name" required placeholder="Full name" class="input !rounded-xl" />
                    <input v-model="roleForm.email" type="email" required placeholder="Email" class="input !rounded-xl" />
                    <select v-model="roleForm.role_slug" class="input !rounded-xl">
                        <option value="super-admin">Super Admin</option>
                        <option value="reviewer">Journal Reviewer</option>
                    </select>
                    <button type="submit" class="btn-primary !rounded-xl" :disabled="saving">
                        {{ saving ? 'Creating...' : 'Create user' }}
                    </button>
                </form>
                </div>
            </section>
        </div>

        <div v-else class="admin-panel">
            <div class="admin-panel-header !border-b">
                <h2 class="admin-panel-title capitalize">{{ activeTab }} settings</h2>
                <div class="admin-panel-actions">
                    <button type="button" class="btn-primary w-full !rounded-xl sm:w-auto" :disabled="saving || loading" @click="saveGroup">
                        {{ saving ? 'Saving...' : 'Save changes' }}
                    </button>
                </div>
            </div>

            <div class="admin-panel-body">
            <div v-if="loading" class="text-sm text-slate-500">Loading settings...</div>
            <form v-else class="space-y-6" @submit.prevent="saveGroup">
                <div v-if="activeTab === 'general'" class="admin-form-grid">
                    <div class="sm:col-span-2 rounded-2xl border border-slate-200 bg-slate-50/70 p-5">
                        <h3 class="text-sm font-semibold text-slate-900">Breaking news banner</h3>
                        <p class="mt-1 text-sm text-slate-500">
                            Scrolls across the top of every public, member, and admin screen when enabled.
                            Use a short punchy line here; put the full story in News &amp; Blog and link to it.
                        </p>
                        <label class="mt-4 flex items-center gap-2 text-sm text-slate-700">
                            <input v-model="values.site_banner_enabled" type="checkbox" class="rounded border-slate-300" />
                            Show breaking news at top of site
                        </label>
                        <div class="mt-4 space-y-4">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700">Scrolling message</label>
                                <textarea
                                    v-model="values.site_banner_message"
                                    rows="3"
                                    class="input !rounded-xl"
                                    placeholder="SICAMA secures Crossref DOI prefix 10.68641 for JOCRAMS · Next goal: Google Scholar indexing"
                                />
                                <p class="mt-1 text-xs text-slate-500">This text loops left-to-right in the top bar.</p>
                            </div>
                            <div class="rounded-xl border border-slate-200 bg-white p-4">
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-slate-700">“Read more” destination</label>
                                        <p class="text-xs text-slate-500">Pick a published news article — no need to paste URLs.</p>
                                    </div>
                                    <RouterLink
                                        to="/admin/news"
                                        class="inline-flex items-center gap-1 text-xs font-semibold text-brand-700 hover:underline"
                                    >
                                        <NewspaperIcon class="size-3.5" />
                                        Manage news
                                    </RouterLink>
                                </div>

                                <div class="mt-3 grid gap-2 sm:grid-cols-3">
                                    <label
                                        v-for="option in [
                                            { id: 'none', label: 'No link' },
                                            { id: 'news', label: 'News article' },
                                            { id: 'custom', label: 'Custom URL' },
                                        ]"
                                        :key="option.id"
                                        class="flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2.5 text-sm transition"
                                        :class="bannerLinkMode === option.id
                                            ? 'border-brand-400 bg-brand-50 text-brand-900'
                                            : 'border-slate-200 bg-slate-50 text-slate-700 hover:bg-white'"
                                    >
                                        <input
                                            v-model="bannerLinkMode"
                                            type="radio"
                                            class="text-brand-600"
                                            :value="option.id"
                                            @change="onBannerLinkModeChange"
                                        />
                                        {{ option.label }}
                                    </label>
                                </div>

                                <div v-if="bannerLinkMode === 'news'" class="mt-4 space-y-2">
                                    <label class="mb-1 block text-sm font-medium text-slate-700">Published article</label>
                                    <p v-if="newsLoading" class="text-xs text-slate-500">Loading articles…</p>
                                    <template v-else-if="newsArticles.length">
                                        <select
                                            class="input !rounded-xl"
                                            :value="selectedNewsUuid"
                                            @change="applySelectedNews($event.target.value)"
                                        >
                                            <option value="">Choose an article…</option>
                                            <option
                                                v-for="article in newsArticles"
                                                :key="article.uuid"
                                                :value="article.uuid"
                                            >
                                                {{ article.title }}
                                            </option>
                                        </select>
                                    </template>
                                    <div
                                        v-else
                                        class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900 ring-1 ring-amber-200/70"
                                    >
                                        No published news articles yet.
                                        <RouterLink to="/admin/news" class="font-semibold underline">Publish one in News &amp; Blog</RouterLink>
                                        first, then come back here.
                                    </div>
                                </div>

                                <div v-else-if="bannerLinkMode === 'custom'" class="mt-4">
                                    <label class="mb-1 block text-sm font-medium text-slate-700">Custom link</label>
                                    <input
                                        v-model="values.site_banner_link"
                                        class="input !rounded-xl"
                                        placeholder="/journal, /events, or https://…"
                                    />
                                    <p class="mt-1 text-xs text-slate-500">Use a site path (starts with /) or a full https:// URL.</p>
                                </div>

                                <div v-if="bannerLinkMode !== 'none'" class="mt-4">
                                    <label class="mb-1 block text-sm font-medium text-slate-700">Button text on the banner</label>
                                    <input
                                        v-model="values.site_banner_link_label"
                                        class="input !rounded-xl"
                                        placeholder="Read full announcement"
                                    />
                                </div>

                                <p class="mt-3 text-xs leading-relaxed text-slate-500">{{ bannerLinkPreview }}</p>
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700">Banner style</label>
                                <select v-model="values.site_banner_style" class="input !rounded-xl">
                                    <option value="announcement">Breaking (dark)</option>
                                    <option value="info">Info (brand blue)</option>
                                    <option value="warning">Warning (amber)</option>
                                    <option value="success">Success (green)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="sm:col-span-2">
                        <AdminImageUpload
                            label="Site logo"
                            help="Shown at the top of system emails when provided. The default Laravel logo is never used."
                            :existing-url="siteLogoUrl"
                            aspect-class="aspect-[3/1]"
                            @update:file="uploadSiteLogo"
                            @clear="removeSiteLogo"
                        />
                        <p v-if="logoUploading" class="mt-2 text-sm text-slate-500">Updating logo...</p>
                    </div>
                </div>

                <div class="admin-form-grid">
                <div v-for="(value, key) in values" :key="key">
                    <template v-if="!isHidden(key)">
                    <label class="mb-1 block text-sm font-medium capitalize text-slate-700">{{ labelFor(key) }}</label>
                    <label v-if="isBoolean(key)" class="flex items-center gap-2 text-sm text-slate-600">
                        <input v-model="values[key]" type="checkbox" class="rounded border-slate-300" />
                        Enabled
                    </label>
                    <input
                        v-else
                        v-model="values[key]"
                        :type="key.includes('secret') || key.includes('password') ? 'password' : 'text'"
                        class="input !rounded-xl"
                    />
                    </template>
                </div>
                </div>
            </form>
            </div>
        </div>

        <section class="mt-8 admin-panel">
            <div class="admin-panel-header !border-b">
                <div class="flex items-center gap-3">
                    <span class="flex size-11 items-center justify-center rounded-xl bg-slate-100 text-slate-600">
                        <Cog6ToothIcon class="size-6" />
                    </span>
                    <div>
                        <h2 class="admin-panel-title">Notification templates</h2>
                        <p class="admin-panel-description">Manage templates under Communications.</p>
                    </div>
                </div>
            </div>
            <div class="admin-panel-body">
            <ul class="divide-y divide-slate-100 rounded-xl border border-slate-100">
                <li v-for="template in templates" :key="template.uuid" class="flex items-center justify-between px-4 py-3 text-sm">
                    <div>
                        <p class="font-medium text-slate-900">{{ template.name }}</p>
                        <p class="text-xs capitalize text-slate-500">{{ template.channel }} · {{ template.slug }}</p>
                    </div>
                    <span class="badge" :class="template.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600'">
                        {{ template.is_active ? 'Active' : 'Inactive' }}
                    </span>
                </li>
                <li v-if="!templates.length" class="px-4 py-8 text-center text-sm text-slate-500">No templates configured.</li>
            </ul>
            <RouterLink to="/admin/campaigns" class="mt-4 inline-flex text-sm font-semibold text-institutional hover:underline">
                Open bulk campaigns
            </RouterLink>
            </div>
        </section>
    </div>
</template>
