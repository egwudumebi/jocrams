<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { BellIcon } from '@heroicons/vue/24/outline';
import { useAuth } from '../composables/useAuth';

const props = defineProps({
    context: {
        type: String,
        default: 'member',
        validator: (value) => ['member', 'admin'].includes(value),
    },
});

const { getMemberClient, getAdminClient, isMemberAuthenticated, isAdminAuthenticated } = useAuth();
const root = ref(null);
const open = ref(false);
const loading = ref(false);
const unreadCount = ref(0);
const notifications = ref([]);

const client = computed(() => (props.context === 'admin' ? getAdminClient() : getMemberClient()));
const isAuthenticated = computed(() => (props.context === 'admin' ? isAdminAuthenticated.value : isMemberAuthenticated.value));

let pollTimer;

onMounted(() => {
    if (isAuthenticated.value) {
        refreshUnreadCount();
        pollTimer = window.setInterval(refreshUnreadCount, 60000);
    }

    document.addEventListener('click', handleDocumentClick);
    document.addEventListener('keydown', handleKeydown);
});

onUnmounted(() => {
    if (pollTimer) {
        window.clearInterval(pollTimer);
    }

    document.removeEventListener('click', handleDocumentClick);
    document.removeEventListener('keydown', handleKeydown);
    document.body.style.overflow = '';
});

function handleDocumentClick(event) {
    if (!open.value || !root.value) {
        return;
    }

    if (!root.value.contains(event.target)) {
        closePanel();
    }
}

function handleKeydown(event) {
    if (event.key === 'Escape' && open.value) {
        closePanel();
    }
}

async function refreshUnreadCount() {
    if (!isAuthenticated.value) {
        return;
    }

    try {
        const { data } = await client.value.get('/notifications/unread-count');
        unreadCount.value = data.unread_count || 0;
    } catch {
        unreadCount.value = 0;
    }
}

function closePanel() {
    open.value = false;
    document.body.style.overflow = '';
}

async function togglePanel() {
    if (open.value) {
        closePanel();
        return;
    }

    open.value = true;

    if (window.innerWidth < 640) {
        document.body.style.overflow = 'hidden';
    }

    loading.value = true;

    try {
        const { data } = await client.value.get('/notifications/my', { params: { per_page: 10 } });
        notifications.value = data.data || [];
        unreadCount.value = data.unread_count ?? unreadCount.value;
    } catch {
        notifications.value = [];
    } finally {
        loading.value = false;
    }
}

async function markRead(notification) {
    if (notification.read_at) {
        return;
    }

    try {
        await client.value.post(`/notifications/${notification.id}/read`);
        notification.read_at = new Date().toISOString();
        unreadCount.value = Math.max(0, unreadCount.value - 1);
    } catch {
        // Keep panel usable if a single mark-read request fails.
    }
}

async function markAllRead() {
    try {
        await client.value.post('/notifications/read-all');
        notifications.value = notifications.value.map((item) => ({ ...item, read_at: item.read_at || new Date().toISOString() }));
        unreadCount.value = 0;
    } catch {
        // Ignore — user can retry from the panel.
    }
}

function formatDate(value) {
    return value ? new Date(value).toLocaleString() : '';
}
</script>

<template>
    <div v-if="isAuthenticated" ref="root" class="relative shrink-0">
        <button
            type="button"
            class="relative inline-flex size-10 items-center justify-center rounded-xl border border-slate-200 text-slate-500 transition hover:bg-slate-50 hover:text-slate-700"
            :aria-expanded="open"
            aria-haspopup="true"
            aria-label="Notifications"
            @click.stop="togglePanel"
        >
            <BellIcon class="size-5" />
            <span
                v-if="unreadCount > 0"
                class="absolute -right-1 -top-1 flex min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold text-white"
            >
                {{ unreadCount > 9 ? '9+' : unreadCount }}
            </span>
        </button>

        <Teleport to="body">
            <Transition
                enter-active-class="transition-opacity duration-150"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition-opacity duration-150"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="open"
                    class="fixed inset-0 z-40 bg-slate-900/20 backdrop-blur-[1px] sm:hidden"
                    aria-hidden="true"
                    @click="closePanel"
                />
            </Transition>
        </Teleport>

        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0 translate-y-1 sm:translate-y-0 sm:scale-95"
            enter-to-class="opacity-100 translate-y-0 sm:scale-100"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="opacity-100 translate-y-0 sm:scale-100"
            leave-to-class="opacity-0 translate-y-1 sm:translate-y-0 sm:scale-95"
        >
            <div
                v-if="open"
                class="fixed inset-x-3 top-[4.25rem] z-50 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl sm:absolute sm:inset-x-auto sm:top-auto sm:right-0 sm:mt-2 sm:w-96"
                role="dialog"
                aria-label="Notifications"
                @click.stop
            >
                <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                    <h3 class="font-semibold text-slate-900">Notifications</h3>
                    <button
                        v-if="unreadCount > 0"
                        type="button"
                        class="text-xs font-medium text-institutional hover:underline"
                        @click="markAllRead"
                    >
                        Mark all read
                    </button>
                </div>

                <div v-if="loading" class="px-4 py-8 text-center text-sm text-slate-500">Loading...</div>
                <div v-else-if="!notifications.length" class="px-4 py-8 text-center text-sm text-slate-500">No notifications yet.</div>
                <ul v-else class="max-h-[min(60vh,24rem)] divide-y divide-slate-100 overflow-y-auto">
                    <li
                        v-for="notification in notifications"
                        :key="notification.id"
                        class="cursor-pointer px-4 py-3 hover:bg-slate-50"
                        :class="!notification.read_at ? 'bg-brand-50/40' : ''"
                        @click="markRead(notification)"
                    >
                        <p class="text-sm font-medium text-slate-900">{{ notification.subject }}</p>
                        <p class="mt-1 line-clamp-2 text-xs text-slate-600">{{ notification.body }}</p>
                        <p class="mt-2 text-[11px] uppercase tracking-wide text-slate-400">{{ notification.event }}</p>
                        <p class="mt-1 text-[11px] text-slate-400">{{ formatDate(notification.created_at) }}</p>
                    </li>
                </ul>
            </div>
        </Transition>
    </div>
</template>
