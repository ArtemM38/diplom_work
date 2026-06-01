<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import axios from 'axios';

const page = usePage();
const open = ref(false);
const loading = ref(false);
const notifications = ref([]);
const unreadCount = ref(page.props.unreadNotificationsCount ?? 0);

const userRoles = computed(() => page.props.auth?.user?.roles ?? []);
const showBell = computed(() =>
    userRoles.value.includes('athlete') || userRoles.value.includes('guardian'),
);

const fetchNotifications = async () => {
    if (!showBell.value) return;
    loading.value = true;
    try {
        const { data } = await axios.get(route('notifications.index'));
        notifications.value = data.notifications ?? [];
        unreadCount.value = data.unread_count ?? 0;
    } finally {
        loading.value = false;
    }
};

const toggle = async () => {
    open.value = !open.value;
    if (open.value) {
        await fetchNotifications();
    }
};

const markRead = async (id) => {
    const { data } = await axios.post(route('notifications.read', id));
    unreadCount.value = data.unread_count ?? 0;
    const item = notifications.value.find((n) => n.id === id);
    if (item) item.read_at = new Date().toISOString();
};

const markAllRead = async () => {
    await axios.post(route('notifications.read-all'));
    unreadCount.value = 0;
    notifications.value.forEach((n) => {
        n.read_at = n.read_at || new Date().toISOString();
    });
};

const iconClass = (type) => {
    if (type === 'document_expired') return 'text-red-600 bg-red-50';
    if (type === 'document_expiring') return 'text-amber-700 bg-amber-50';
    if (type === 'training_reminder' || type === 'training_scheduled') return 'text-indigo-700 bg-indigo-50';
    if (type === 'training_cancelled') return 'text-orange-700 bg-orange-50';
    if (type === 'password_changed') return 'text-violet-700 bg-violet-50';
    if (type === 'balance_negative') return 'text-rose-700 bg-rose-50';
    if (type === 'event_registration') return 'text-emerald-700 bg-emerald-50';
    return 'text-slate-600 bg-slate-50';
};

const iconEmoji = (type) => {
    if (type === 'training_reminder' || type === 'training_scheduled') return '🥋';
    if (type === 'training_cancelled') return '✕';
    if (type === 'password_changed') return '🔐';
    if (type === 'balance_negative') return '₽';
    if (type === 'event_registration') return '🏆';
    return '📄';
};

watch(() => page.props.unreadNotificationsCount, (v) => {
    unreadCount.value = v ?? 0;
});

onMounted(() => {
    if (showBell.value && unreadCount.value > 0) {
        fetchNotifications();
    }
});
</script>

<template>
    <div v-if="showBell" class="relative">
        <button
            type="button"
            @click="toggle"
            class="relative p-2 rounded-lg text-gray-500 hover:bg-gray-100 hover:text-gray-700 transition"
            aria-label="Уведомления"
        >
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6 6 0 10-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
            </svg>
            <span
                v-if="unreadCount > 0"
                class="absolute -top-0.5 -right-0.5 min-w-[18px] h-[18px] px-1 flex items-center justify-center rounded-full bg-red-500 text-white text-[10px] font-bold"
            >
                {{ unreadCount > 9 ? '9+' : unreadCount }}
            </span>
        </button>

        <div
            v-if="open"
            class="absolute right-0 mt-2 w-80 sm:w-96 bg-white rounded-xl shadow-xl border border-slate-200 z-50 overflow-hidden"
        >
            <div class="flex items-center justify-between px-4 py-3 border-b bg-slate-50">
                <span class="font-semibold text-sm text-slate-800">Уведомления</span>
                <button
                    v-if="unreadCount > 0"
                    type="button"
                    @click="markAllRead"
                    class="text-xs text-indigo-600 hover:underline"
                >
                    Прочитать все
                </button>
            </div>

            <div v-if="loading" class="p-6 text-center text-sm text-slate-400">Загрузка...</div>
            <div v-else-if="!notifications.length" class="p-6 text-center text-sm text-slate-400">Нет уведомлений</div>
            <ul v-else class="max-h-80 overflow-y-auto divide-y divide-slate-100">
                <li
                    v-for="item in notifications"
                    :key="item.id"
                    class="p-3 hover:bg-slate-50 cursor-pointer transition"
                    :class="!item.read_at ? 'bg-indigo-50/40' : ''"
                    @click="markRead(item.id)"
                >
                    <div class="flex gap-2">
                        <span class="shrink-0 w-8 h-8 rounded-lg flex items-center justify-center text-xs" :class="iconClass(item.type)">
                            {{ iconEmoji(item.type) }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-slate-900">{{ item.title }}</p>
                            <p class="text-xs text-slate-600 mt-0.5 leading-snug">{{ item.message }}</p>
                            <p class="text-[10px] text-slate-400 mt-1">{{ item.created_at }}</p>
                        </div>
                    </div>
                </li>
            </ul>
        </div>

        <div v-if="open" class="fixed inset-0 z-40" @click="open = false"></div>
    </div>
</template>
