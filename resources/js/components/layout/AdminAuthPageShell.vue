<script setup>
import { RouterLink } from 'vue-router';
import { BuildingOffice2Icon } from '@heroicons/vue/24/outline';

defineProps({
    title: {
        type: String,
        required: true,
    },
    subtitle: {
        type: String,
        default: '',
    },
    bullets: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <div class="flex min-h-screen flex-col bg-slate-950">
        <header class="border-b border-white/10 bg-slate-950/90 backdrop-blur">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
                <RouterLink to="/" class="group flex items-center gap-2.5">
                    <span class="flex size-10 items-center justify-center rounded-xl bg-white/10 ring-1 ring-white/10 transition group-hover:bg-white/15">
                        <BuildingOffice2Icon class="size-5 text-accent-gold" aria-hidden="true" />
                    </span>
                    <span class="font-display text-lg font-bold text-white">Jocrams</span>
                </RouterLink>
                <RouterLink to="/" class="text-sm font-medium text-slate-300 transition hover:text-white">
                    ← Back to website
                </RouterLink>
            </div>
        </header>

        <div class="flex flex-1 flex-col lg:flex-row">
            <aside class="relative hidden overflow-hidden bg-gradient-to-br from-slate-950 via-institutional-dark to-institutional lg:flex lg:w-[42%] xl:w-[45%]">
                <div class="pointer-events-none absolute inset-0 opacity-40" aria-hidden="true">
                    <div class="absolute -right-16 -top-16 size-80 rounded-full bg-accent-gold/15 blur-3xl" />
                    <div class="absolute -bottom-20 left-0 size-72 rounded-full bg-institutional/30 blur-3xl" />
                    <div
                        class="absolute inset-0 opacity-[0.07]"
                        style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 24px 24px;"
                    />
                </div>
                <div class="relative flex flex-col justify-center px-10 py-16 xl:px-16">
                    <p class="label-caps text-accent-gold/90">Operations Hub</p>
                    <h1 class="mt-4 font-display text-3xl font-bold leading-tight text-white xl:text-4xl">
                        {{ title }}
                    </h1>
                    <p v-if="subtitle" class="mt-4 text-base leading-relaxed text-slate-300">
                        {{ subtitle }}
                    </p>
                    <ul v-if="bullets.length" class="mt-8 space-y-3">
                        <li v-for="bullet in bullets" :key="bullet" class="flex items-start gap-3 text-sm text-slate-300">
                            <span class="mt-1.5 size-1.5 shrink-0 rounded-full bg-accent-gold" />
                            {{ bullet }}
                        </li>
                    </ul>
                    <slot name="aside" />
                </div>
            </aside>

            <main class="flex flex-1 items-center justify-center bg-surface-muted px-4 py-10 sm:px-6 lg:bg-slate-100 lg:px-10 lg:py-14">
                <div class="w-full max-w-md">
                    <div class="mb-6 lg:hidden">
                        <p class="label-caps text-institutional">Operations Hub</p>
                        <h1 class="mt-2 font-display text-2xl font-bold text-slate-900">{{ title }}</h1>
                        <p v-if="subtitle" class="mt-2 text-sm text-text-secondary">{{ subtitle }}</p>
                    </div>
                    <slot />
                </div>
            </main>
        </div>

        <footer class="border-t border-slate-800 bg-slate-950 px-4 py-4 text-center text-sm text-slate-500 sm:px-6">
            Authorized staff only.
            <RouterLink to="/member/login" class="ml-1 font-medium text-slate-300 hover:text-white hover:underline">
                Member login
            </RouterLink>
        </footer>
    </div>
</template>
