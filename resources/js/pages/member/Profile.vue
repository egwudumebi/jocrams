<script setup>
import { computed, ref, onMounted } from 'vue';
import {
    AcademicCapIcon,
    CameraIcon,
    EnvelopeIcon,
    IdentificationIcon,
    LinkIcon,
    LockClosedIcon,
    MapPinIcon,
    PhoneIcon,
    PlusIcon,
    SparklesIcon,
    TrashIcon,
    UserCircleIcon,
} from '@heroicons/vue/24/outline';
import AdminAlert from '../../components/admin/AdminAlert.vue';
import AdminPageIntro from '../../components/admin/AdminPageIntro.vue';
import AdminPanel from '../../components/admin/AdminPanel.vue';
import FormFieldLabel from '../../components/admin/FormFieldLabel.vue';
import PasswordInput from '../../components/forms/PasswordInput.vue';
import { memberApi } from '../../api/client';
import { useAuth } from '../../composables/useAuth';
import { extractApiError } from '../../utils/apiError';

const { memberUser, memberToken, fetchMemberProfile, getMemberClient } = useAuth();

const profileForm = ref({
    position: '',
    professional_bio: '',
    orcid: '',
    country: '',
    state: '',
    city: '',
    street: '',
    social_links: [''],
    achievements: [''],
});

const passwordForm = ref({
    email: '',
    current_password: '',
    new_password: '',
    new_password_confirmation: '',
});

const profileMessage = ref('');
const profileError = ref('');
const passwordMessage = ref('');
const passwordError = ref('');
const savingProfile = ref(false);
const uploadingImage = ref(false);
const removingImage = ref(false);
const imageInput = ref(null);

const hasProfileImage = computed(() => Boolean(memberUser.value?.profile?.profile_image));

const hasActiveMembership = computed(() => memberUser.value?.member?.status === 'active');
const orcidPreviewUrl = computed(() => {
    const value = profileForm.value.orcid.trim();
    if (!value) {
        return null;
    }

    if (/^https?:\/\//i.test(value)) {
        return value;
    }

    const compact = value.replace(/[^0-9X]/gi, '');
    if (compact.length === 16) {
        const formatted = `${compact.slice(0, 4)}-${compact.slice(4, 8)}-${compact.slice(8, 12)}-${compact.slice(12, 16)}`.toUpperCase();

        return `https://orcid.org/${formatted}`;
    }

    return null;
});

onMounted(async () => {
    await fetchMemberProfile();
    passwordForm.value.email = memberUser.value?.email || '';
    hydrateProfileForm();
});

function hydrateProfileForm() {
    const profile = memberUser.value?.profile;
    if (!profile) {
        return;
    }

    profileForm.value.position = profile.position || '';
    profileForm.value.professional_bio = profile.professional_bio || '';
    profileForm.value.orcid = profile.orcid || '';
    profileForm.value.country = profile.address?.country || '';
    profileForm.value.state = profile.address?.state || '';
    profileForm.value.city = profile.address?.city || '';
    profileForm.value.street = profile.address?.street || '';
    profileForm.value.social_links = profile.social_links?.length ? [...profile.social_links] : [''];
    profileForm.value.achievements = profile.achievements?.length ? [...profile.achievements] : [''];
}

function addSocialLink() {
    profileForm.value.social_links.push('');
}

function removeSocialLink(index) {
    profileForm.value.social_links.splice(index, 1);
    if (profileForm.value.social_links.length === 0) {
        profileForm.value.social_links.push('');
    }
}

function addAchievement() {
    profileForm.value.achievements.push('');
}

function removeAchievement(index) {
    profileForm.value.achievements.splice(index, 1);
    if (profileForm.value.achievements.length === 0) {
        profileForm.value.achievements.push('');
    }
}

function cleanList(items) {
    return items.map((item) => item.trim()).filter(Boolean);
}

function statusClass(status) {
    const map = {
        active: 'bg-emerald-100 text-emerald-800',
        pending: 'bg-amber-100 text-amber-800',
        suspended: 'bg-red-100 text-red-800',
        expired: 'bg-slate-100 text-slate-600',
    };

    return map[status] || 'bg-slate-100 text-slate-700';
}

async function saveProfile() {
    profileMessage.value = '';
    profileError.value = '';
    savingProfile.value = true;

    try {
        const { data } = await getMemberClient().put('/auth/profile', {
            position: profileForm.value.position || null,
            professional_bio: profileForm.value.professional_bio || null,
            orcid: profileForm.value.orcid || null,
            country: profileForm.value.country || null,
            state: profileForm.value.state || null,
            city: profileForm.value.city || null,
            street: profileForm.value.street || null,
            social_links: cleanList(profileForm.value.social_links),
            achievements: cleanList(profileForm.value.achievements),
        });
        profileMessage.value = data.message;
        await fetchMemberProfile();
        hydrateProfileForm();
    } catch (e) {
        profileError.value = extractApiError(e, 'Unable to update profile.');
    } finally {
        savingProfile.value = false;
    }
}

function triggerImageUpload() {
    imageInput.value?.click();
}

async function onImageSelected(event) {
    const file = event.target.files?.[0];
    if (!file) {
        return;
    }

    profileMessage.value = '';
    profileError.value = '';
    uploadingImage.value = true;

    const formData = new FormData();
    formData.append('image', file);

    try {
        const client = memberApi(memberToken.value);
        client.defaults.headers['Content-Type'] = 'multipart/form-data';
        const { data } = await client.post('/auth/profile/image', formData);
        profileMessage.value = data.message;
        await fetchMemberProfile();
    } catch (e) {
        profileError.value = extractApiError(e, 'Unable to upload profile image.');
    } finally {
        uploadingImage.value = false;
        event.target.value = '';
    }
}

async function removeProfileImage() {
    if (!hasProfileImage.value || removingImage.value) {
        return;
    }

    profileMessage.value = '';
    profileError.value = '';
    removingImage.value = true;

    try {
        const { data } = await getMemberClient().delete('/auth/profile/image');
        profileMessage.value = data.message;
        await fetchMemberProfile();
    } catch (e) {
        profileError.value = extractApiError(e, 'Unable to remove profile image.');
    } finally {
        removingImage.value = false;
    }
}

async function changePassword() {
    passwordMessage.value = '';
    passwordError.value = '';

    try {
        const { data } = await memberApi(memberToken.value).post('/auth/password/change', {
            email: passwordForm.value.email,
            current_password: passwordForm.value.current_password,
            new_password: passwordForm.value.new_password,
            new_password_confirmation: passwordForm.value.new_password_confirmation,
        });
        passwordMessage.value = data.message;
        passwordForm.value.current_password = '';
        passwordForm.value.new_password = '';
        passwordForm.value.new_password_confirmation = '';
    } catch (e) {
        passwordError.value = extractApiError(e, 'Unable to update password.');
    }
}
</script>

<template>
    <div>
        <AdminPageIntro description="Manage your account details, professional profile, and security settings." />

        <div v-if="memberUser" class="mt-6 grid gap-6 xl:grid-cols-[320px_1fr]">
            <div class="space-y-6">
                <AdminPanel title="Account overview" description="Your sign-in details and membership summary.">
                    <div class="flex flex-col items-center text-center">
                        <div class="relative">
                            <div class="flex size-32 items-center justify-center overflow-hidden rounded-full border-4 border-white bg-slate-100 text-4xl font-bold text-slate-400 shadow-md ring-2 ring-institutional/10">
                                <img
                                    v-if="memberUser.profile?.profile_image"
                                    :src="memberUser.profile.profile_image"
                                    alt="Profile photo"
                                    class="size-full object-cover"
                                />
                                <span v-else>{{ memberUser.name?.charAt(0) }}</span>
                            </div>
                            <button
                                type="button"
                                class="absolute bottom-1 right-1 flex size-9 items-center justify-center rounded-full bg-institutional text-white shadow-lg hover:bg-institutional-dark"
                                :disabled="uploadingImage || removingImage"
                                :title="uploadingImage ? 'Uploading…' : hasProfileImage ? 'Change photo' : 'Upload photo'"
                                @click="triggerImageUpload"
                            >
                                <CameraIcon class="size-4" />
                            </button>
                            <button
                                v-if="hasProfileImage"
                                type="button"
                                class="absolute bottom-1 left-1 flex size-9 items-center justify-center rounded-full bg-white text-red-600 shadow-lg ring-1 ring-slate-200 hover:bg-red-50"
                                :disabled="uploadingImage || removingImage"
                                :title="removingImage ? 'Removing…' : 'Remove photo'"
                                @click="removeProfileImage"
                            >
                                <TrashIcon class="size-4" />
                            </button>
                        </div>

                        <input ref="imageInput" type="file" accept="image/jpeg,image/png,image/webp" class="hidden" @change="onImageSelected" />

                        <h2 class="mt-4 text-lg font-semibold text-slate-900">{{ memberUser.name }}</h2>
                        <p v-if="memberUser.profile?.position" class="mt-1 text-sm text-slate-600">{{ memberUser.profile.position }}</p>
                        <p class="mt-1 text-xs text-slate-500">
                            {{
                                uploadingImage
                                    ? 'Uploading photo…'
                                    : removingImage
                                      ? 'Removing photo…'
                                      : 'JPG, PNG, or WebP up to 5 MB'
                            }}
                        </p>

                        <div v-if="hasActiveMembership" class="mt-4 w-full rounded-2xl border border-institutional/15 bg-gradient-to-br from-institutional/8 via-white to-white p-4 text-left">
                            <div class="flex items-center gap-3">
                                <span class="flex size-10 items-center justify-center rounded-xl bg-institutional/10 text-institutional">
                                    <IdentificationIcon class="size-5" />
                                </span>
                                <div>
                                    <p class="text-xs uppercase tracking-wide text-slate-500">Membership</p>
                                    <p class="font-semibold text-slate-900">{{ memberUser.member?.tier?.name || 'Member' }}</p>
                                </div>
                            </div>
                            <p v-if="memberUser.member?.membership_number" class="mt-3 font-mono text-xs text-slate-600">
                                {{ memberUser.member.membership_number }}
                            </p>
                        </div>
                    </div>

                    <dl class="mt-6 space-y-3 border-t border-slate-100 pt-6 text-sm">
                        <div class="flex items-start gap-3 rounded-xl bg-surface-muted/50 p-3">
                            <EnvelopeIcon class="mt-0.5 size-4 shrink-0 text-slate-400" />
                            <div>
                                <dt class="text-slate-500">Email</dt>
                                <dd class="font-medium text-slate-900">{{ memberUser.email }}</dd>
                            </div>
                        </div>
                        <div class="flex items-start gap-3 rounded-xl bg-surface-muted/50 p-3">
                            <PhoneIcon class="mt-0.5 size-4 shrink-0 text-slate-400" />
                            <div>
                                <dt class="text-slate-500">Phone</dt>
                                <dd class="font-medium text-slate-900">{{ memberUser.phone || '—' }}</dd>
                            </div>
                        </div>
                        <div class="flex items-start gap-3 rounded-xl bg-surface-muted/50 p-3">
                            <UserCircleIcon class="mt-0.5 size-4 shrink-0 text-slate-400" />
                            <div>
                                <dt class="text-slate-500">Account status</dt>
                                <dd class="mt-1">
                                    <span class="badge capitalize" :class="statusClass(memberUser.status)">
                                        {{ memberUser.status }}
                                    </span>
                                </dd>
                            </div>
                        </div>
                    </dl>
                </AdminPanel>
            </div>

            <div class="space-y-6">
                <AdminAlert v-if="profileMessage" type="success">{{ profileMessage }}</AdminAlert>
                <AdminAlert v-if="profileError" type="error">{{ profileError }}</AdminAlert>

                <AdminPanel
                    title="Professional profile"
                    description="Share your role, research identity, and public-facing details."
                >
                    <form class="space-y-8" @submit.prevent="saveProfile">
                        <section>
                            <div class="mb-4 flex items-center gap-2">
                                <AcademicCapIcon class="size-5 text-institutional" />
                                <h3 class="font-semibold text-slate-900">About you</h3>
                            </div>
                            <div class="grid gap-4">
                                <div>
                                    <FormFieldLabel label="Position / title" help="Your current role or professional title." />
                                    <input v-model="profileForm.position" class="input mt-2" placeholder="e.g. Senior Economist" />
                                </div>
                                <div>
                                    <FormFieldLabel label="Professional bio" help="A short summary shown on your public member profile." />
                                    <textarea
                                        v-model="profileForm.professional_bio"
                                        rows="4"
                                        class="input mt-2"
                                        placeholder="Brief summary of your work, research interests, and expertise."
                                    />
                                </div>
                            </div>
                        </section>

                        <section>
                            <div class="mb-4 flex items-center gap-2">
                                <SparklesIcon class="size-5 text-institutional" />
                                <h3 class="font-semibold text-slate-900">Research identity</h3>
                            </div>
                            <div>
                                <FormFieldLabel
                                    label="ORCID iD"
                                    help="Optional. Enter your 16-digit ORCID or paste your full ORCID profile URL."
                                />
                                <input
                                    v-model="profileForm.orcid"
                                    class="input mt-2 font-mono"
                                    placeholder="0000-0002-1825-0097"
                                    autocomplete="off"
                                />
                                <p v-if="orcidPreviewUrl" class="mt-2 text-sm">
                                    <a :href="orcidPreviewUrl" target="_blank" rel="noopener noreferrer" class="font-medium text-institutional hover:underline">
                                        View ORCID profile
                                    </a>
                                </p>
                            </div>
                        </section>

                        <section>
                            <div class="mb-4 flex items-center gap-2">
                                <MapPinIcon class="size-5 text-institutional" />
                                <h3 class="font-semibold text-slate-900">Location</h3>
                            </div>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <FormFieldLabel label="Country" />
                                    <input v-model="profileForm.country" class="input mt-2" placeholder="Nigeria" />
                                </div>
                                <div>
                                    <FormFieldLabel label="State / region" />
                                    <input v-model="profileForm.state" class="input mt-2" placeholder="Lagos" />
                                </div>
                                <div>
                                    <FormFieldLabel label="City" />
                                    <input v-model="profileForm.city" class="input mt-2" placeholder="Ikeja" />
                                </div>
                                <div>
                                    <FormFieldLabel label="Street address" />
                                    <input v-model="profileForm.street" class="input mt-2" placeholder="12 Example Street" />
                                </div>
                            </div>
                        </section>

                        <section>
                            <div class="mb-4 flex items-center justify-between gap-3">
                                <div class="flex items-center gap-2">
                                    <LinkIcon class="size-5 text-institutional" />
                                    <h3 class="font-semibold text-slate-900">Social links</h3>
                                </div>
                                <button type="button" class="inline-flex items-center gap-1 text-sm font-semibold text-institutional hover:underline" @click="addSocialLink">
                                    <PlusIcon class="size-4" />
                                    Add link
                                </button>
                            </div>
                            <div class="space-y-3">
                                <div
                                    v-for="(link, index) in profileForm.social_links"
                                    :key="`social-${index}`"
                                    class="flex gap-2"
                                >
                                    <input v-model="profileForm.social_links[index]" class="input" placeholder="https://linkedin.com/in/you" />
                                    <button
                                        type="button"
                                        class="inline-flex shrink-0 items-center gap-1 rounded-xl border border-slate-200 px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50"
                                        @click="removeSocialLink(index)"
                                    >
                                        <TrashIcon class="size-4" />
                                        Remove
                                    </button>
                                </div>
                            </div>
                        </section>

                        <section>
                            <div class="mb-4 flex items-center justify-between gap-3">
                                <div class="flex items-center gap-2">
                                    <SparklesIcon class="size-5 text-institutional" />
                                    <h3 class="font-semibold text-slate-900">Achievements</h3>
                                </div>
                                <button type="button" class="inline-flex items-center gap-1 text-sm font-semibold text-institutional hover:underline" @click="addAchievement">
                                    <PlusIcon class="size-4" />
                                    Add achievement
                                </button>
                            </div>
                            <div class="space-y-3">
                                <div
                                    v-for="(achievement, index) in profileForm.achievements"
                                    :key="`achievement-${index}`"
                                    class="flex gap-2"
                                >
                                    <input v-model="profileForm.achievements[index]" class="input" placeholder="Award, publication, or milestone" />
                                    <button
                                        type="button"
                                        class="inline-flex shrink-0 items-center gap-1 rounded-xl border border-slate-200 px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50"
                                        @click="removeAchievement(index)"
                                    >
                                        <TrashIcon class="size-4" />
                                        Remove
                                    </button>
                                </div>
                            </div>
                        </section>

                        <div class="flex justify-end border-t border-slate-100 pt-6">
                            <button type="submit" class="btn-institutional" :disabled="savingProfile">
                                {{ savingProfile ? 'Saving…' : 'Save profile' }}
                            </button>
                        </div>
                    </form>
                </AdminPanel>

                <AdminPanel title="Change password" description="Update your sign-in password.">
                    <div class="mb-4 flex items-center gap-2 text-sm text-slate-600">
                        <LockClosedIcon class="size-5 text-institutional" />
                        <span>Use a strong password you do not reuse elsewhere.</span>
                    </div>

                    <AdminAlert v-if="passwordMessage" type="success" class="mb-4">{{ passwordMessage }}</AdminAlert>
                    <AdminAlert v-if="passwordError" type="error" class="mb-4">{{ passwordError }}</AdminAlert>

                    <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="changePassword">
                        <div class="sm:col-span-2">
                            <FormFieldLabel label="Current password" required />
                            <PasswordInput v-model="passwordForm.current_password" required input-class="input mt-2" />
                        </div>
                        <div>
                            <FormFieldLabel label="New password" required />
                            <PasswordInput v-model="passwordForm.new_password" required input-class="input mt-2" />
                        </div>
                        <div>
                            <FormFieldLabel label="Confirm new password" required />
                            <PasswordInput v-model="passwordForm.new_password_confirmation" required input-class="input mt-2" />
                        </div>
                        <div class="sm:col-span-2 flex justify-end border-t border-slate-100 pt-4">
                            <button type="submit" class="btn-institutional">Update password</button>
                        </div>
                    </form>
                </AdminPanel>
            </div>
        </div>
    </div>
</template>
