<script setup>
import { ref, onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import {
    BuildingOffice2Icon,
    CheckCircleIcon,
    EnvelopeIcon,
    MapPinIcon,
    PaperAirplaneIcon,
    PhoneIcon,
} from '@heroicons/vue/24/outline';
import { publicApi } from '../../api/client';
import { extractApiError } from '../../utils/apiError';

const form = ref({ name: '', email: '', phone: '', subject: '', message: '' });
const sent = ref(false);
const error = ref('');
const submitting = ref(false);
const branches = ref([]);

async function submit() {
    error.value = '';
    submitting.value = true;

    try {
        await publicApi().post('/contact', form.value);
        sent.value = true;
        form.value = { name: '', email: '', phone: '', subject: '', message: '' };
    } catch (e) {
        error.value = extractApiError(e, 'Failed to send message. Please try again.');
    } finally {
        submitting.value = false;
    }
}

onMounted(async () => {
    try {
        const { data } = await publicApi().get('/branches');
        branches.value = data.data.slice(0, 2);
    } catch {
        branches.value = [];
    }
});
</script>

<template>
    <div>
        <!-- Page header -->
        <section class="border-b border-slate-200/80 bg-gradient-to-br from-surface-muted via-white to-institutional/5">
            <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 sm:py-16 lg:px-8">
                <div class="max-w-2xl">
                    <p class="label-caps text-institutional">Get in touch</p>
                    <h1 class="mt-2 font-display text-4xl font-bold tracking-tight text-institutional-dark sm:text-5xl">
                        Contact Us
                    </h1>
                    <p class="mt-4 text-lg leading-relaxed text-text-secondary">
                        Send us a message and our team will respond as soon as possible. We're here to help with membership, events, and general inquiries.
                    </p>
                </div>
            </div>
        </section>

        <!-- Content -->
        <section class="section-padding bg-white">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="grid gap-10 lg:grid-cols-5 lg:gap-14">
                    <!-- Form -->
                    <div class="lg:col-span-3">
                        <div v-if="sent" class="card-modern p-10 text-center sm:p-12">
                            <div class="mx-auto flex size-16 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
                                <CheckCircleIcon class="size-9" aria-hidden="true" />
                            </div>
                            <h2 class="mt-6 font-display text-2xl font-bold text-institutional-dark">Message sent</h2>
                            <p class="mx-auto mt-3 max-w-md text-text-secondary">
                                Thank you for reaching out. We've received your inquiry and will get back to you shortly.
                            </p>
                            <button type="button" class="btn-institutional mt-8" @click="sent = false">
                                Send another message
                            </button>
                        </div>

                        <form v-else class="card-modern p-6 sm:p-8 lg:p-10" @submit.prevent="submit">
                            <h2 class="font-display text-xl font-bold text-institutional-dark">Send a message</h2>
                            <p class="mt-1 text-sm text-text-secondary">All fields marked with * are required.</p>

                            <div v-if="error" class="mt-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                                {{ error }}
                            </div>

                            <div class="mt-8 grid gap-5 sm:grid-cols-2">
                                <div class="sm:col-span-1">
                                    <label class="label" for="contact-name">Name *</label>
                                    <input
                                        id="contact-name"
                                        v-model="form.name"
                                        required
                                        autocomplete="name"
                                        class="input !rounded-xl !border-slate-200/80 !py-3 focus:!border-institutional focus:!ring-institutional/20"
                                        placeholder="Your full name"
                                    />
                                </div>
                                <div class="sm:col-span-1">
                                    <label class="label" for="contact-email">Email *</label>
                                    <input
                                        id="contact-email"
                                        v-model="form.email"
                                        type="email"
                                        required
                                        autocomplete="email"
                                        class="input !rounded-xl !border-slate-200/80 !py-3 focus:!border-institutional focus:!ring-institutional/20"
                                        placeholder="you@example.com"
                                    />
                                </div>
                                <div class="sm:col-span-1">
                                    <label class="label" for="contact-phone">Phone</label>
                                    <input
                                        id="contact-phone"
                                        v-model="form.phone"
                                        type="tel"
                                        autocomplete="tel"
                                        class="input !rounded-xl !border-slate-200/80 !py-3 focus:!border-institutional focus:!ring-institutional/20"
                                        placeholder="+234 800 000 0000"
                                    />
                                </div>
                                <div class="sm:col-span-1">
                                    <label class="label" for="contact-subject">Subject *</label>
                                    <input
                                        id="contact-subject"
                                        v-model="form.subject"
                                        required
                                        class="input !rounded-xl !border-slate-200/80 !py-3 focus:!border-institutional focus:!ring-institutional/20"
                                        placeholder="How can we help?"
                                    />
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="label" for="contact-message">Message *</label>
                                    <textarea
                                        id="contact-message"
                                        v-model="form.message"
                                        required
                                        rows="6"
                                        class="input !rounded-xl !border-slate-200/80 !py-3 focus:!border-institutional focus:!ring-institutional/20"
                                        placeholder="Tell us more about your inquiry..."
                                    />
                                </div>
                            </div>

                            <button
                                type="submit"
                                class="btn-institutional mt-8 w-full sm:w-auto"
                                :disabled="submitting"
                            >
                                <PaperAirplaneIcon class="mr-2 size-4" aria-hidden="true" />
                                {{ submitting ? 'Sending...' : 'Send Message' }}
                            </button>
                        </form>
                    </div>

                    <!-- Sidebar -->
                    <aside class="lg:col-span-2">
                        <div class="sticky top-24 space-y-6">
                            <div class="card-modern p-6 sm:p-7">
                                <h2 class="font-display text-lg font-bold text-institutional-dark">Headquarters</h2>
                                <ul class="mt-5 space-y-4">
                                    <li class="flex gap-3 text-sm text-text-secondary">
                                        <MapPinIcon class="mt-0.5 size-5 shrink-0 text-institutional" aria-hidden="true" />
                                        <span>Lagos, Nigeria<br>Association headquarters</span>
                                    </li>
                                    <li class="flex gap-3 text-sm text-text-secondary">
                                        <EnvelopeIcon class="mt-0.5 size-5 shrink-0 text-institutional" aria-hidden="true" />
                                        <a href="mailto:info@jocrams.test" class="transition hover:text-institutional">info@jocrams.test</a>
                                    </li>
                                    <li class="flex gap-3 text-sm text-text-secondary">
                                        <PhoneIcon class="mt-0.5 size-5 shrink-0 text-institutional" aria-hidden="true" />
                                        <span>+234 800 JOCRAMS</span>
                                    </li>
                                </ul>
                            </div>

                            <div v-if="branches.length > 0" class="card-modern p-6 sm:p-7">
                                <h2 class="font-display text-lg font-bold text-institutional-dark">Branch offices</h2>
                                <ul class="mt-5 space-y-4">
                                    <li
                                        v-for="branch in branches"
                                        :key="branch.uuid"
                                        class="rounded-xl border border-slate-100 bg-surface-muted/50 p-4"
                                    >
                                        <p class="flex items-center gap-2 font-semibold text-institutional-dark">
                                            <BuildingOffice2Icon class="size-4 text-institutional" aria-hidden="true" />
                                            {{ branch.name }}
                                        </p>
                                        <p class="mt-1 text-sm text-text-secondary">
                                            {{ branch.city }}, {{ branch.state }}
                                        </p>
                                        <p v-if="branch.email" class="mt-2 text-sm text-institutional">{{ branch.email }}</p>
                                    </li>
                                </ul>
                                <RouterLink to="/branches" class="link-arrow mt-5 inline-flex text-sm">
                                    View all branches
                                </RouterLink>
                            </div>

                            <div class="rounded-2xl bg-gradient-to-br from-institutional to-institutional-dark p-6 text-white sm:p-7">
                                <h2 class="font-display text-lg font-bold">Become a member</h2>
                                <p class="mt-2 text-sm leading-relaxed text-slate-200">
                                    Join thousands of professionals and unlock full access to events, resources, and credentials.
                                </p>
                                <RouterLink to="/member/register" class="btn-gold mt-5 inline-flex text-sm">
                                    Join Us Today
                                </RouterLink>
                            </div>
                        </div>
                    </aside>
                </div>
            </div>
        </section>
    </div>
</template>
