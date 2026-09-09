<script setup>
import { ref, onMounted, watch } from 'vue';
import { RouterLink } from 'vue-router';
import { Cog6ToothIcon } from '@heroicons/vue/24/outline';
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
    fee_student_ngn: 'Student fee (NGN)',
    fee_professional_ngn: 'Professional fee (NGN)',
    fee_institutional_ngn: 'Institutional fee (NGN)',
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

onMounted(async () => {
    await Promise.all([loadGroup('general'), loadTemplates()]);
});

watch(activeTab, async (tab) => {
    if (tab !== 'users') {
        await loadGroup(tab);
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
                        <h3 class="text-sm font-semibold text-slate-900">Site-wide notification banner</h3>
                        <p class="mt-1 text-sm text-slate-500">
                            Appears at the very top of every public, member, and admin screen when enabled.
                        </p>
                        <label class="mt-4 flex items-center gap-2 text-sm text-slate-700">
                            <input v-model="values.site_banner_enabled" type="checkbox" class="rounded border-slate-300" />
                            Show banner
                        </label>
                        <div class="mt-4 space-y-4">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700">Banner message</label>
                                <textarea
                                    v-model="values.site_banner_message"
                                    rows="3"
                                    class="input !rounded-xl"
                                    placeholder="Annual conference registration is now open."
                                />
                            </div>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-slate-700">Link (optional)</label>
                                    <input v-model="values.site_banner_link" class="input !rounded-xl" placeholder="/events or https://..." />
                                </div>
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-slate-700">Link label</label>
                                    <input v-model="values.site_banner_link_label" class="input !rounded-xl" placeholder="Learn more" />
                                </div>
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700">Style</label>
                                <select v-model="values.site_banner_style" class="input !rounded-xl">
                                    <option value="info">Info (brand blue)</option>
                                    <option value="warning">Warning (amber)</option>
                                    <option value="success">Success (green)</option>
                                    <option value="announcement">Announcement (dark)</option>
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
