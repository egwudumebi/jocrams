<script setup>
import { computed, ref, onMounted } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import {
    ArrowLeftIcon,
    ArrowRightIcon,
    CalendarDaysIcon,
    MapPinIcon,
    PencilSquareIcon,
    PlusIcon,
    TrashIcon,
} from '@heroicons/vue/24/outline';
import AdminAlert from '../../components/admin/AdminAlert.vue';
import AdminImageUpload from '../../components/admin/AdminImageUpload.vue';
import AdminPageIntro from '../../components/admin/AdminPageIntro.vue';
import AdminPanel from '../../components/admin/AdminPanel.vue';
import AdminToolbar from '../../components/admin/AdminToolbar.vue';
import EventBannerThumb from '../../components/admin/EventBannerThumb.vue';
import FormFieldLabel from '../../components/admin/FormFieldLabel.vue';
import RichTextEditor from '../../components/forms/RichTextEditor.vue';
import { useAuth } from '../../composables/useAuth';

const route = useRoute();
const { getAdminClient } = useAuth();
const events = ref([]);
const memberTypes = ref([]);
const search = ref('');
const loading = ref(true);
const saving = ref(false);
const message = ref('');
const error = ref('');
const editingUuid = ref(null);
const existingBannerUrl = ref('');

const emptyForm = () => ({
    title: '',
    description: '',
    body: '',
    location: '',
    virtual_url: '',
    starts_at: '',
    ends_at: '',
    registration_opens_at: '',
    registration_closes_at: '',
    max_attendees: '',
    fee: '',
    currency: 'NGN',
    visibility: 'public',
    status: 'draft',
    sessions: [{ title: '', starts_at: '', ends_at: '' }],
    pricing_tiers: [{ category: 'member', fee: '' }, { category: 'non_member', fee: '' }],
});

const form = ref(emptyForm());
const bannerFile = ref(null);

const formSteps = [
    { id: 'basic', label: 'Basic info' },
    { id: 'banner', label: 'Banner' },
    { id: 'schedule', label: 'Schedule' },
    { id: 'capacity', label: 'Pricing' },
    { id: 'extras', label: 'Sessions & tiers' },
];

const currentStep = ref(0);
const stepError = ref('');

const isFirstStep = computed(() => currentStep.value === 0);
const isLastStep = computed(() => currentStep.value === formSteps.length - 1);
const currentStepMeta = computed(() => formSteps[currentStep.value]);

onMounted(async () => {
    await Promise.all([load(), loadMemberTypes()]);

    const editUuid = route.query.edit;
    if (editUuid && typeof editUuid === 'string') {
        const match = events.value.find((item) => item.uuid === editUuid);
        if (match) {
            startEdit(match);
        }
    }
});

async function loadMemberTypes() {
    try {
        const { data } = await getAdminClient().get('/events/member-types');
        memberTypes.value = data.data || [];
    } catch {
        memberTypes.value = [
            { value: 'member', label: 'Member' },
            { value: 'non_member', label: 'Non-member' },
            { value: 'student', label: 'Student' },
            { value: 'institution', label: 'Institution' },
        ];
    }
}

async function load() {
    loading.value = true;
    try {
        const { data } = await getAdminClient().get('/events', { params: { search: search.value || undefined } });
        events.value = data.data || [];
    } finally {
        loading.value = false;
    }
}

function resetForm() {
    form.value = emptyForm();
    editingUuid.value = null;
    bannerFile.value = null;
    existingBannerUrl.value = '';
    error.value = '';
    stepError.value = '';
    currentStep.value = 0;
}

function validateStep(stepIndex) {
    stepError.value = '';

    if (stepIndex === 0 && !form.value.title.trim()) {
        stepError.value = 'Please enter an event title before continuing.';
        return false;
    }

    if (stepIndex === 2 && !form.value.starts_at) {
        stepError.value = 'Please set when the event starts before continuing.';
        return false;
    }

    return true;
}

function goToStep(index) {
    if (index === currentStep.value) return;

    if (index > currentStep.value) {
        for (let stepIndex = currentStep.value; stepIndex < index; stepIndex += 1) {
            if (!validateStep(stepIndex)) {
                currentStep.value = stepIndex;
                return;
            }
        }
    }

    stepError.value = '';
    currentStep.value = index;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function nextStep() {
    if (!validateStep(currentStep.value)) return;

    if (!isLastStep.value) {
        currentStep.value += 1;
        stepError.value = '';
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
}

function prevStep() {
    if (!isFirstStep.value) {
        currentStep.value -= 1;
        stepError.value = '';
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
}

function toLocalInput(value) {
    if (!value) return '';
    const date = new Date(value);
    const offset = date.getTimezoneOffset();
    return new Date(date.getTime() - offset * 60000).toISOString().slice(0, 16);
}

function startEdit(event) {
    editingUuid.value = event.uuid;
    existingBannerUrl.value = event.banner_url || '';
    form.value = {
        title: event.title || '',
        description: event.description || '',
        body: event.body || '',
        location: event.location || '',
        virtual_url: event.virtual_url || '',
        starts_at: toLocalInput(event.starts_at),
        ends_at: toLocalInput(event.ends_at),
        registration_opens_at: toLocalInput(event.registration_opens_at),
        registration_closes_at: toLocalInput(event.registration_closes_at),
        max_attendees: event.max_attendees ?? '',
        fee: event.fee ?? '',
        currency: event.currency || 'NGN',
        visibility: event.visibility?.value || event.visibility || 'public',
        status: event.status || 'draft',
        sessions: event.sessions?.length
            ? event.sessions.map((session) => ({
                title: session.title || '',
                starts_at: toLocalInput(session.starts_at),
                ends_at: toLocalInput(session.ends_at),
            }))
            : [{ title: '', starts_at: '', ends_at: '' }],
        pricing_tiers: event.pricing_tiers?.length
            ? event.pricing_tiers.map((tier) => ({ category: tier.category, fee: tier.fee ?? '' }))
            : [{ category: 'member', fee: '' }, { category: 'non_member', fee: '' }],
    };
    bannerFile.value = null;
    stepError.value = '';
    currentStep.value = 0;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function addSession() {
    form.value.sessions.push({ title: '', starts_at: '', ends_at: '' });
}

function removeSession(index) {
    form.value.sessions.splice(index, 1);
    if (!form.value.sessions.length) addSession();
}

function addPricingTier() {
    form.value.pricing_tiers.push({ category: 'student', fee: '' });
}

function removePricingTier(index) {
    form.value.pricing_tiers.splice(index, 1);
}

function onBannerUpdate(file) {
    bannerFile.value = file;
}

function onBannerClear() {
    bannerFile.value = null;
    existingBannerUrl.value = '';
}

function buildPayload() {
    const sessions = form.value.sessions
        .filter((session) => session.title && session.starts_at)
        .map((session, index) => ({
            title: session.title,
            starts_at: session.starts_at,
            ends_at: session.ends_at || null,
            sort_order: index,
        }));

    const pricingTiers = form.value.pricing_tiers
        .filter((tier) => tier.category)
        .map((tier) => ({
            category: tier.category,
            fee: tier.fee === '' ? 0 : Number(tier.fee),
        }));

    return {
        title: form.value.title,
        description: form.value.description || null,
        body: form.value.body || null,
        location: form.value.location || null,
        virtual_url: form.value.virtual_url || null,
        starts_at: form.value.starts_at,
        ends_at: form.value.ends_at || null,
        registration_opens_at: form.value.registration_opens_at || null,
        registration_closes_at: form.value.registration_closes_at || null,
        max_attendees: form.value.max_attendees === '' ? null : Number(form.value.max_attendees),
        fee: form.value.fee === '' ? 0 : Number(form.value.fee),
        currency: form.value.currency || 'NGN',
        visibility: form.value.visibility,
        status: form.value.status,
        sessions,
        pricing_tiers: pricingTiers,
    };
}

async function save() {
    message.value = '';
    error.value = '';
    stepError.value = '';

    for (let stepIndex = 0; stepIndex < formSteps.length - 1; stepIndex += 1) {
        if (!validateStep(stepIndex)) {
            currentStep.value = stepIndex;
            return;
        }
    }

    saving.value = true;

    try {
        const payload = buildPayload();
        let response;

        if (bannerFile.value) {
            const formData = new FormData();
            Object.entries(payload).forEach(([key, value]) => {
                if (value === null || value === undefined) return;
                if (Array.isArray(value)) {
                    formData.append(key, JSON.stringify(value));
                } else {
                    formData.append(key, String(value));
                }
            });
            formData.append('banner', bannerFile.value);

            const client = getAdminClient();
            if (editingUuid.value) {
                formData.append('_method', 'PUT');
                response = await client.post(`/events/${editingUuid.value}`, formData, {
                    headers: { 'Content-Type': 'multipart/form-data' },
                });
            } else {
                response = await client.post('/events', formData, {
                    headers: { 'Content-Type': 'multipart/form-data' },
                });
            }
        } else if (editingUuid.value) {
            response = await getAdminClient().put(`/events/${editingUuid.value}`, payload);
        } else {
            response = await getAdminClient().post('/events', payload);
        }

        message.value = editingUuid.value ? 'Event updated.' : 'Event created.';
        resetForm();
        await load();
        return response;
    } catch (e) {
        error.value = e.response?.data?.message
            || Object.values(e.response?.data?.errors || {}).flat().join(' ')
            || 'Unable to save event.';
    } finally {
        saving.value = false;
    }
}

async function removeEvent(event) {
    if (!window.confirm(`Delete "${event.title}"?`)) return;

    await getAdminClient().delete(`/events/${event.uuid}`);
    message.value = 'Event deleted.';
    if (editingUuid.value === event.uuid) resetForm();
    await load();
}

function formatDate(value) {
    return value ? new Date(value).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' }) : '—';
}

function isEventUpcoming(event) {
    const now = Date.now();
    const endsAt = event.ends_at ? new Date(event.ends_at).getTime() : null;
    const startsAt = new Date(event.starts_at).getTime();

    if (endsAt) {
        return endsAt >= now;
    }

    return startsAt >= now;
}

function statusClass(status) {
    return status === 'published' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800';
}
</script>

<template>
    <div>
        <AdminPageIntro description="Plan, publish, and manage association events from one calendar view." />

        <AdminAlert v-if="message" type="success">{{ message }}</AdminAlert>
        <AdminAlert v-if="error" type="error">{{ error }}</AdminAlert>

        <AdminPanel
            :title="editingUuid ? 'Edit event' : 'Create event'"
            :description="editingUuid ? 'Update event details step by step.' : 'Complete each step to schedule a new association event.'"
            class="mb-6"
        >
            <template #actions>
                <button v-if="editingUuid" type="button" class="btn-secondary w-full !rounded-xl sm:w-auto" @click="resetForm">
                    Cancel edit
                </button>
            </template>

            <form class="space-y-6" @submit.prevent="save">
                <div class="form-stepper">
                    <div class="form-stepper-meta">
                        <p class="font-medium text-slate-700">
                            Step {{ currentStep + 1 }} of {{ formSteps.length }}:
                            <span class="text-institutional">{{ currentStepMeta.label }}</span>
                        </p>
                        <p class="shrink-0 text-xs text-slate-500">{{ Math.round(((currentStep + 1) / formSteps.length) * 100) }}% complete</p>
                    </div>

                    <div class="form-stepper-track" role="progressbar" :aria-valuenow="currentStep + 1" aria-valuemin="1" :aria-valuemax="formSteps.length">
                        <div
                            v-for="(step, index) in formSteps"
                            :key="step.id"
                            class="form-stepper-segment"
                            :class="index <= currentStep ? 'form-stepper-segment-active' : 'form-stepper-segment-inactive'"
                        />
                    </div>

                    <div class="form-stepper-labels">
                        <button
                            v-for="(step, index) in formSteps"
                            :key="`${step.id}-label`"
                            type="button"
                            class="form-stepper-label"
                            :class="{
                                'form-stepper-label-active': index === currentStep,
                                'form-stepper-label-done': index < currentStep,
                            }"
                            @click="goToStep(index)"
                        >
                            {{ step.label }}
                        </button>
                    </div>
                </div>

                <p v-if="stepError" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ stepError }}</p>

                <section v-show="currentStep === 0" class="form-section">
                    <h3 class="form-section-title">Basic information</h3>
                    <div class="admin-form-grid">
                        <div class="form-field sm:col-span-2">
                            <FormFieldLabel
                                label="Title"
                                required
                                help="The public name shown on event cards, calendar, and registration pages."
                            />
                            <input v-model="form.title" class="input-modern" placeholder="Annual general meeting" />
                        </div>
                        <div class="form-field sm:col-span-2">
                            <FormFieldLabel
                                label="Short description"
                                help="A brief summary displayed in event listings and search results."
                            />
                            <RichTextEditor v-model="form.description" placeholder="Brief summary for listings" min-height="5rem" />
                        </div>
                        <div class="form-field sm:col-span-2">
                            <FormFieldLabel
                                label="Full details"
                                help="Complete event information including agenda, speakers, dress code, and instructions for attendees."
                            />
                            <RichTextEditor v-model="form.body" placeholder="Agenda, speakers, dress code, and other event information" min-height="10rem" />
                        </div>
                    </div>
                </section>

                <section v-show="currentStep === 1" class="form-section">
                    <h3 class="form-section-title">Banner image</h3>
                    <AdminImageUpload
                        :existing-url="existingBannerUrl"
                        label="Event banner"
                        help="Wide image displayed on event cards and detail pages. JPEG, PNG, or WebP up to 5 MB. A placeholder is shown when no banner is uploaded."
                        @update:file="onBannerUpdate"
                        @clear="onBannerClear"
                    />
                </section>

                <section v-show="currentStep === 2" class="form-section">
                    <h3 class="form-section-title">Schedule & location</h3>
                    <div class="admin-form-grid">
                        <div class="form-field">
                            <FormFieldLabel
                                label="Location"
                                help="Physical venue or city where the event takes place."
                            />
                            <input v-model="form.location" class="input-modern" placeholder="Venue or city" />
                        </div>
                        <div class="form-field">
                            <FormFieldLabel
                                label="Virtual URL"
                                help="Link for online attendance via Zoom, Teams, or a similar platform."
                            />
                            <input v-model="form.virtual_url" type="url" class="input-modern" placeholder="https://zoom.us/..." />
                        </div>
                        <div class="form-field">
                            <FormFieldLabel
                                label="Starts at"
                                required
                                help="When the event begins. Required before the event can be saved."
                            />
                            <input v-model="form.starts_at" type="datetime-local" class="input-modern" />
                        </div>
                        <div class="form-field">
                            <FormFieldLabel
                                label="Ends at"
                                help="When the event ends. Leave empty if the end time is not yet decided."
                            />
                            <input v-model="form.ends_at" type="datetime-local" class="input-modern" />
                        </div>
                        <div class="form-field">
                            <FormFieldLabel
                                label="Registration opens"
                                help="When attendees can start registering for this event."
                            />
                            <input v-model="form.registration_opens_at" type="datetime-local" class="input-modern" />
                        </div>
                        <div class="form-field">
                            <FormFieldLabel
                                label="Registration closes"
                                help="Last date and time registrations will be accepted."
                            />
                            <input v-model="form.registration_closes_at" type="datetime-local" class="input-modern" />
                        </div>
                    </div>
                </section>

                <section v-show="currentStep === 3" class="form-section">
                    <h3 class="form-section-title">Capacity & pricing</h3>
                    <div class="admin-form-grid">
                        <div class="form-field">
                            <FormFieldLabel
                                label="Max attendees"
                                help="Maximum number of registrations allowed. Leave empty for unlimited capacity."
                            />
                            <input v-model="form.max_attendees" type="number" min="0" class="input-modern" placeholder="Leave empty for unlimited" />
                        </div>
                        <div class="form-field">
                            <FormFieldLabel
                                label="Base fee (NGN)"
                                help="Default registration fee in Nigerian Naira when no pricing tier applies."
                            />
                            <input v-model="form.fee" type="number" min="0" step="0.01" class="input-modern" placeholder="0.00" />
                        </div>
                        <div class="form-field">
                            <FormFieldLabel
                                label="Visibility"
                                help="Controls who can see and register for this event on the public site."
                            />
                            <select v-model="form.visibility" class="select-modern">
                                <option value="public">Public</option>
                                <option value="members_only">Members only</option>
                            </select>
                        </div>
                        <div class="form-field">
                            <FormFieldLabel
                                label="Status"
                                help="Draft events are hidden from the public site until published."
                            />
                            <select v-model="form.status" class="select-modern">
                                <option value="draft">Draft</option>
                                <option value="published">Published</option>
                            </select>
                        </div>
                    </div>
                </section>

                <section v-show="currentStep === 4" class="space-y-6">
                    <div class="form-section">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h3 class="form-section-title !normal-case !tracking-normal !text-slate-900">Sessions</h3>
                                <p class="form-hint">Optional agenda blocks within the event.</p>
                            </div>
                            <button type="button" class="btn-secondary w-full !rounded-xl sm:w-auto" @click="addSession">Add session</button>
                        </div>
                        <div v-for="(session, index) in form.sessions" :key="`session-${index}`" class="repeater-card mt-4 md:grid-cols-[1fr_1fr_1fr_auto]">
                            <div class="form-field md:col-span-1">
                                <FormFieldLabel
                                    label="Session title"
                                    help="Name of this agenda block, talk, or breakout session."
                                />
                                <input v-model="session.title" class="input-modern" placeholder="Opening keynote" />
                            </div>
                            <div class="form-field">
                                <FormFieldLabel
                                    label="Starts"
                                    help="When this session begins within the event schedule."
                                />
                                <input v-model="session.starts_at" type="datetime-local" class="input-modern" />
                            </div>
                            <div class="form-field">
                                <FormFieldLabel
                                    label="Ends"
                                    help="When this session ends. Leave empty if open-ended."
                                />
                                <input v-model="session.ends_at" type="datetime-local" class="input-modern" />
                            </div>
                            <div class="flex items-end">
                                <button type="button" class="btn-secondary w-full !rounded-xl md:w-auto" @click="removeSession(index)">Remove</button>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h3 class="form-section-title !normal-case !tracking-normal !text-slate-900">Pricing tiers</h3>
                                <p class="form-hint">Set different fees by member category.</p>
                            </div>
                            <button type="button" class="btn-secondary w-full !rounded-xl sm:w-auto" @click="addPricingTier">Add tier</button>
                        </div>
                        <div v-for="(tier, index) in form.pricing_tiers" :key="`tier-${index}`" class="repeater-card mt-4 md:grid-cols-[1fr_1fr_auto]">
                            <div class="form-field">
                                <FormFieldLabel
                                    label="Category"
                                    help="Member type this pricing tier applies to."
                                />
                                <select v-model="tier.category" class="select-modern">
                                    <option v-for="type in memberTypes" :key="type.value" :value="type.value">{{ type.label }}</option>
                                </select>
                            </div>
                            <div class="form-field">
                                <FormFieldLabel
                                    label="Fee (NGN)"
                                    help="Registration amount in Nigerian Naira for this member category."
                                />
                                <input v-model="tier.fee" type="number" min="0" step="0.01" class="input-modern" placeholder="0.00" />
                            </div>
                            <div class="flex items-end">
                                <button type="button" class="btn-secondary w-full !rounded-xl md:w-auto" @click="removePricingTier(index)">Remove</button>
                            </div>
                        </div>
                    </div>
                </section>

                <div class="form-stepper-nav">
                    <button
                        type="button"
                        class="btn-secondary inline-flex w-full items-center justify-center gap-2 !rounded-xl sm:w-auto"
                        :disabled="isFirstStep"
                        @click="prevStep"
                    >
                        <ArrowLeftIcon class="size-4" />
                        Previous
                    </button>

                    <div class="flex flex-col gap-2 sm:flex-row sm:justify-end">
                        <button
                            v-if="!isLastStep"
                            type="button"
                            class="btn-primary inline-flex w-full items-center justify-center gap-2 !rounded-xl sm:w-auto"
                            @click="nextStep"
                        >
                            Next
                            <ArrowRightIcon class="size-4" />
                        </button>

                        <template v-else>
                            <button
                                type="submit"
                                class="btn-primary w-full !rounded-xl sm:w-auto"
                                :disabled="saving"
                                @click="stepError = ''"
                            >
                                {{ saving ? 'Saving…' : (editingUuid ? 'Update event' : 'Create event') }}
                            </button>
                            <button
                                v-if="!editingUuid"
                                type="button"
                                class="btn-secondary w-full !rounded-xl sm:w-auto"
                                :disabled="saving"
                                @click="stepError = ''; form.status = 'published'; save()"
                            >
                                Create & publish
                            </button>
                        </template>
                    </div>
                </div>
            </form>
        </AdminPanel>

        <AdminToolbar class="mb-4">
            <template #search>
                <input
                    v-model="search"
                    type="search"
                    placeholder="Search events..."
                    class="input-modern"
                    @keyup.enter="load"
                />
            </template>
            <template #actions>
                <RouterLink to="/admin/event-registrations" class="btn-institutional inline-flex w-full items-center justify-center !rounded-xl !px-4 !py-2.5 text-sm sm:w-auto">
                    <PlusIcon class="mr-1 size-4" />
                    View registrations
                </RouterLink>
            </template>
        </AdminToolbar>

        <div v-if="loading" class="empty-state bg-white">Loading events...</div>

        <div v-else-if="!events.length" class="empty-state bg-white">
            <CalendarDaysIcon class="mx-auto size-10 text-slate-300" />
            <p class="mt-3 font-medium text-slate-700">No events scheduled</p>
            <p class="mt-1 text-sm text-text-secondary">Create your first event using the form above.</p>
        </div>

        <div v-else class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <article
                v-for="event in events"
                :key="event.uuid"
                class="admin-stat-card overflow-hidden !p-0"
            >
                <EventBannerThumb :url="event.banner_url" :title="event.title" variant="card" />
                <div class="p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div class="rounded-xl bg-brand-50 px-3 py-2 text-center">
                            <p class="text-lg font-bold text-institutional">{{ new Date(event.starts_at).getDate() }}</p>
                            <p class="text-[10px] uppercase text-slate-500">{{ new Date(event.starts_at).toLocaleDateString(undefined, { month: 'short' }) }}</p>
                        </div>
                        <div class="flex flex-wrap justify-end gap-2">
                            <span class="badge capitalize" :class="statusClass(event.status)">{{ event.status }}</span>
                            <span
                                v-if="event.status === 'published' && !isEventUpcoming(event)"
                                class="badge bg-slate-100 text-slate-600"
                            >
                                Past · listed under past events
                            </span>
                        </div>
                    </div>
                    <h2 class="mt-4 font-display text-lg font-bold text-slate-900">{{ event.title }}</h2>
                    <p class="mt-2 flex items-center gap-1.5 text-sm text-slate-500">
                        <MapPinIcon class="size-4 shrink-0" />
                        {{ event.location || 'Location TBA' }}
                    </p>
                    <p class="mt-1 text-sm text-slate-500">{{ formatDate(event.starts_at) }} · {{ event.registrations_count ?? 0 }} registrants</p>
                    <div class="mt-4 flex flex-wrap gap-3">
                        <button type="button" class="inline-flex items-center gap-1 text-sm font-semibold text-institutional hover:underline" @click="startEdit(event)">
                            <PencilSquareIcon class="size-4" />
                            Edit
                        </button>
                        <button type="button" class="inline-flex items-center gap-1 text-sm font-semibold text-red-600 hover:underline" @click="removeEvent(event)">
                            <TrashIcon class="size-4" />
                            Delete
                        </button>
                        <RouterLink :to="{ name: 'admin.event-registrations.show', params: { uuid: event.uuid } }" class="text-sm font-semibold text-slate-600 hover:underline">
                            Registrations
                        </RouterLink>
                    </div>
                </div>
            </article>
        </div>
    </div>
</template>
