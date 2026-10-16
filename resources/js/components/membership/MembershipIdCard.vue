<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    member: {
        type: Object,
        required: true,
    },
    logoUrl: {
        type: String,
        default: '/images/sicama-logo.png',
    },
    verifyUrl: {
        type: String,
        default: '',
    },
    showEmail: {
        type: Boolean,
        default: true,
    },
});

const flipped = ref(false);

const isSicama = computed(() => {
    const number = String(props.member.membership_number || '');
    return props.member.organization === 'sicama' || number.startsWith('SICAMA');
});

const roleTitle = computed(() => {
    if (props.member.card_role) {
        return props.member.card_role;
    }
    return props.member.tier?.name || 'Member';
});

const validUntil = computed(() => {
    if (!props.member.expires_at) {
        return 'No expiry date';
    }

    const date = new Date(props.member.expires_at);
    const day = date.getDate();
    const suffix = (day % 100 >= 11 && day % 100 <= 13)
        ? 'th'
        : ({ 1: 'st', 2: 'nd', 3: 'rd' }[day % 10] || 'th');

    return `${day}${suffix} ${date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' })}`;
});

const initials = computed(() => {
    const name = props.member.name || '?';
    return name
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part.charAt(0))
        .join('')
        .toUpperCase();
});

const chairmanSignatureUrl = '/images/signatures/bot-chairman.png';
const presidentSignatureUrl = '/images/signatures/president.png';

const qrSrc = computed(() => {
    const target = props.verifyUrl
        || (typeof window !== 'undefined' ? `${window.location.origin}/verify` : '/verify');
    return `/api/v1/public/qr.svg?data=${encodeURIComponent(target)}`;
});

function toggleFlip() {
    flipped.value = !flipped.value;
}
</script>

<template>
    <div class="w-full max-w-[34rem]">
        <div class="mb-3 flex items-center justify-between gap-3">
            <p class="text-xs font-medium text-slate-500">
                {{ flipped ? 'Back of card' : 'Front of card' }} · click card to flip
            </p>
            <button
                type="button"
                class="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-700 shadow-sm transition hover:border-slate-300 hover:bg-slate-50"
                @click="toggleFlip"
            >
                {{ flipped ? 'Show front' : 'Show back' }}
            </button>
        </div>

        <div class="id-card-scene" style="aspect-ratio: 242.64 / 152.52;">
            <button
                type="button"
                class="id-card-inner group w-full text-left"
                :class="{ 'is-flipped': flipped }"
                :aria-pressed="flipped"
                aria-label="Flip membership ID card"
                @click="toggleFlip"
            >
                <!-- FRONT -->
                <article
                    v-if="isSicama"
                    class="id-card-face id-card-front overflow-hidden rounded-xl bg-white shadow-xl ring-1 ring-black/10"
                >
                    <div class="flex h-full flex-col">
                        <header class="flex items-center gap-2.5 bg-[#156082] px-3 py-2.5 text-white sm:gap-3 sm:px-4 sm:py-3">
                            <div class="flex size-10 shrink-0 items-center justify-center overflow-hidden rounded-full bg-white sm:size-12">
                                <img :src="logoUrl" alt="SICAMA" class="size-9 object-contain sm:size-11" />
                            </div>
                            <div class="min-w-0">
                                <p class="text-[0.55rem] font-bold uppercase leading-tight tracking-wide sm:text-[0.65rem]">
                                    Scholars in Communication and Media Advancement Initiative
                                </p>
                                <p class="mt-0.5 text-base font-bold tracking-[0.12em] sm:text-lg">SICAMA</p>
                            </div>
                        </header>

                        <div class="relative flex-1 overflow-hidden px-3 py-3 sm:px-4 sm:py-3.5">
                            <div class="pointer-events-none absolute inset-0 flex items-center justify-center" aria-hidden="true">
                                <span class="select-none text-2xl font-bold tracking-[0.2em] text-slate-200/90 -rotate-[32deg] sm:text-3xl">
                                    SICAMA INITIATIVE
                                </span>
                            </div>

                            <div class="relative z-10 flex gap-3 sm:gap-4">
                                <div class="size-16 shrink-0 overflow-hidden border-[1.5px] border-[#156082] bg-slate-100 sm:size-20">
                                    <img
                                        v-if="member.profile_image"
                                        :src="member.profile_image"
                                        :alt="member.name"
                                        class="size-full object-cover"
                                    />
                                    <div
                                        v-else
                                        class="flex size-full flex-col items-center justify-center px-1 text-center text-[0.6rem] font-bold leading-tight text-slate-400 sm:text-[0.7rem]"
                                    >
                                        Your Picture<br>Here
                                    </div>
                                </div>
                                <div class="flex min-w-0 flex-1 flex-col justify-center text-center">
                                    <p class="text-sm font-bold leading-snug text-slate-900 sm:text-base">
                                        {{ member.name }}
                                    </p>
                                    <p class="mt-1 text-xs font-bold text-slate-900 sm:text-sm">{{ roleTitle }}</p>
                                </div>
                            </div>

                            <div class="relative z-10 mt-3 space-y-0.5 text-[0.7rem] leading-snug text-slate-900 sm:mt-3.5 sm:text-xs">
                                <p class="font-bold">Membership No:</p>
                                <p class="font-bold tracking-wide">{{ member.membership_number }}</p>
                                <p>Valid Until {{ validUntil }}</p>
                                <p v-if="showEmail && member.email">
                                    <span class="font-bold">E-mail:</span> {{ member.email }}
                                </p>
                            </div>
                        </div>

                        <footer class="bg-[#156082] px-3 py-2 text-center text-[0.65rem] font-bold tracking-wide text-white sm:text-xs">
                            OFFICIAL SICAMA MEMBERSHIP CARD
                        </footer>
                    </div>
                </article>

                <article
                    v-else
                    class="id-card-face id-card-front overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl"
                >
                    <header class="bg-[#0a3d91] px-5 py-4 text-white">
                        <div class="flex items-center gap-3">
                            <div class="flex size-11 items-center justify-center rounded-xl bg-white/15">
                                <img :src="logoUrl" alt="JOCRAMS" class="size-8 object-contain" />
                            </div>
                            <div>
                                <p class="text-lg font-bold">JOCRAMS</p>
                                <p class="text-[0.65rem] uppercase tracking-[0.14em] text-blue-100">Verified member credential</p>
                            </div>
                        </div>
                    </header>
                    <div class="h-1 bg-[#f4b400]" />
                    <div class="flex gap-4 p-5">
                        <div class="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-full border-2 border-slate-200 bg-slate-100 text-lg font-bold text-slate-500">
                            <img
                                v-if="member.profile_image"
                                :src="member.profile_image"
                                :alt="member.name"
                                class="size-full object-cover"
                            />
                            <span v-else>{{ initials }}</span>
                        </div>
                        <div class="min-w-0">
                            <p class="text-[0.65rem] font-semibold uppercase tracking-wider text-slate-500">Member name</p>
                            <p class="mt-0.5 text-xl font-bold text-slate-900">{{ member.name }}</p>
                            <p class="mt-3 text-[0.65rem] font-semibold uppercase tracking-wider text-slate-500">Membership number</p>
                            <p class="mt-0.5 font-mono text-sm font-bold text-[#0a3d91]">{{ member.membership_number }}</p>
                            <p class="mt-3 text-sm text-slate-600">{{ roleTitle }}</p>
                            <p class="mt-1 text-sm text-slate-600">Valid until {{ validUntil }}</p>
                        </div>
                    </div>
                </article>

                <!-- BACK -->
                <article
                    class="id-card-face id-card-back overflow-hidden rounded-xl bg-white shadow-xl ring-1 ring-black/10"
                    :class="isSicama ? '' : 'rounded-2xl'"
                >
                    <div class="flex h-full flex-col">
                        <header
                            class="flex items-center gap-2.5 px-3 py-2.5 text-white sm:gap-3 sm:px-4 sm:py-3"
                            :class="isSicama ? 'bg-[#156082]' : 'bg-[#0a3d91]'"
                        >
                            <div class="flex size-10 shrink-0 items-center justify-center overflow-hidden rounded-full bg-white sm:size-11">
                                <img :src="logoUrl" alt="" class="size-9 object-contain sm:size-10" />
                            </div>
                            <div class="min-w-0">
                                <p class="text-base font-bold tracking-[0.14em] sm:text-lg">
                                    {{ isSicama ? 'SICAMA' : 'JOCRAMS' }}
                                </p>
                                <p class="mt-0.5 text-[0.5rem] font-bold uppercase leading-tight tracking-wide sm:text-[0.58rem]">
                                    {{ isSicama
                                        ? 'Scholars in Communication and Media Advancement Initiative'
                                        : 'Journal of Communication Research and Media Studies' }}
                                </p>
                            </div>
                        </header>

                        <div class="relative flex-1 overflow-hidden px-3 py-2.5 sm:px-4 sm:py-3">
                            <div class="pointer-events-none absolute inset-0 flex items-center justify-center" aria-hidden="true">
                                <span class="select-none text-xl font-bold tracking-[0.18em] text-slate-200/90 -rotate-[32deg] sm:text-2xl">
                                    {{ isSicama ? 'SICAMA INITIATIVE' : 'JOCRAMS' }}
                                </span>
                            </div>

                            <div class="relative z-10 flex h-full flex-col">
                                <p class="text-center text-sm font-bold tracking-wide text-slate-900 underline sm:text-base">
                                    MEMBERSHIP CARD
                                </p>
                                <p class="mx-auto mt-2 max-w-[92%] text-center text-[0.62rem] leading-snug text-slate-800 sm:text-[0.7rem]">
                                    This Card remains the property of
                                    <strong>{{ isSicama ? 'SICAMA' : 'JOCRAMS' }}</strong>
                                    and is valid only for the period stated on the
                                    front. If found, please return it to the organization.
                                </p>

                                <p class="mt-2 text-center text-[0.65rem] font-bold text-slate-900 sm:text-xs">
                                    For Membership Verification &amp; Enquiries
                                </p>
                                <p class="mt-1 text-center text-[0.62rem] leading-snug text-slate-800 sm:text-[0.7rem]">
                                    <template v-if="isSicama">
                                        E-Mail: <span class="text-[#156082] underline">merlaw2002@yahoo.com</span><br>
                                        TEL: +2348035525785, +2348060051572
                                    </template>
                                    <template v-else>
                                        Visit <span class="text-[#0a3d91] underline">jocrams.com/verify</span>
                                    </template>
                                </p>

                                <div class="mt-auto grid grid-cols-3 items-end gap-2 pt-2">
                                    <div class="text-center">
                                        <div class="flex h-8 items-end justify-center sm:h-9">
                                            <img
                                                :src="chairmanSignatureUrl"
                                                alt="Chairman signature"
                                                class="max-h-8 max-w-full object-contain sm:max-h-9"
                                            />
                                        </div>
                                        <div class="mx-1 mt-0.5 border-t border-slate-900" />
                                        <p class="mt-0.5 text-[0.6rem] font-bold text-slate-900 sm:text-[0.65rem]">Chairman</p>
                                    </div>
                                    <div class="text-center">
                                        <div class="flex h-8 items-end justify-center sm:h-9">
                                            <img
                                                :src="presidentSignatureUrl"
                                                alt="President signature"
                                                class="max-h-8 max-w-full object-contain sm:max-h-9"
                                            />
                                        </div>
                                        <div class="mx-1 mt-0.5 border-t border-slate-900" />
                                        <p class="mt-0.5 text-[0.6rem] font-bold text-slate-900 sm:text-[0.65rem]">President</p>
                                    </div>
                                    <div class="text-center">
                                        <img
                                            :src="qrSrc"
                                            alt="Verification QR code"
                                            class="mx-auto size-12 rounded-sm bg-white p-0.5 sm:size-14"
                                        />
                                        <p class="mt-0.5 text-[0.55rem] font-bold text-slate-900 sm:text-[0.6rem]">Scan me!</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <footer
                            class="h-2.5"
                            :class="isSicama ? 'bg-[#156082]' : 'bg-[#0a3d91]'"
                        />
                    </div>
                </article>
            </button>
        </div>
    </div>
</template>

<style scoped>
.id-card-scene {
    perspective: 1400px;
}

.id-card-inner {
    position: relative;
    height: 100%;
    transform-style: preserve-3d;
    transition: transform 0.7s cubic-bezier(0.4, 0.2, 0.2, 1);
}

.id-card-inner.is-flipped {
    transform: rotateY(180deg);
}

.id-card-face {
    position: absolute;
    inset: 0;
    backface-visibility: hidden;
    -webkit-backface-visibility: hidden;
}

.id-card-back {
    transform: rotateY(180deg);
}

@media (prefers-reduced-motion: reduce) {
    .id-card-inner {
        transition: none;
    }
}
</style>
