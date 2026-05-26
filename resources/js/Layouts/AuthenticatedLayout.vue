<script setup>
import { ref, computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NotificationBell from '@/Components/NotificationBell.vue';

const mobileMenuOpen = ref(false);
const page = usePage();
const user = computed(() => page.props.auth?.user);

const roleLabels = {
    admin: 'Администратор',
    accountant: 'Бухгалтер',
    coach: 'Тренер',
    athlete: 'Спортсмен',
    guardian: 'Родитель',
};

const userRoles = computed(() => {
    if (user.value?.roles?.length) {
        return user.value.roles;
    }
    return user.value?.role ? [user.value.role] : [];
});

const roleDisplay = computed(() => {
    const roles = userRoles.value;
    if (!roles.length) {
        return '—';
    }
    return roles.map((r) => roleLabels[r] || r).join(', ');
});

const safeRoute = (name, fallback = '#') => {
    try {
        return route(name);
    } catch (e) {
        return fallback;
    }
};

const navigation = computed(() => [
    { name: 'Реестр спортсменов', href: safeRoute('admin.athletes'), roles: ['admin', 'coach'] },
    { name: 'Портфолио', href: safeRoute('admin.portfolio'), roles: ['admin', 'coach', 'accountant'] },
    { name: 'Мероприятия', href: safeRoute('admin.events'), roles: ['admin', 'coach', 'accountant'] },
    { name: 'Группы и секции', href: safeRoute('admin.groups'), roles: ['admin', 'coach', 'accountant'] },
    { name: 'Расписание', href: safeRoute('admin.schedule'), roles: ['admin', 'coach'] },
    { name: 'Моё расписание', href: safeRoute('athlete.schedule.calendar', safeRoute('dashboard')), roles: ['athlete'] },
    { name: 'Мой табель', href: safeRoute('athlete.attendance', safeRoute('dashboard')), roles: ['athlete'] },
    { name: 'Моё портфолио', href: safeRoute('athlete.portfolio', safeRoute('dashboard')), roles: ['athlete'] },
    { name: 'Мои финансы', href: safeRoute('finance', safeRoute('dashboard')), roles: ['athlete'] },
    { name: 'Мой ребёнок', href: safeRoute('dashboard'), roles: ['guardian'] },
    { name: 'Расписание ребёнка', href: safeRoute('guardian.schedule', safeRoute('dashboard')), roles: ['guardian'] },
    { name: 'Табель ребёнка', href: safeRoute('guardian.attendance', safeRoute('dashboard')), roles: ['guardian'] },
    { name: 'Финансы ребёнка', href: safeRoute('finance', safeRoute('dashboard')), roles: ['guardian'] },
    { name: 'Мед. справки', href: safeRoute('admin.medical-certificates'), roles: ['admin'] },
    { name: 'Залы', href: safeRoute('admin.locations'), roles: ['admin'] },
    { name: 'Пользователи', href: safeRoute('admin.coaches'), roles: ['admin'] },
    { name: 'Табель', href: safeRoute('admin.attendance.journal'), roles: ['admin', 'coach', 'accountant'] },
    { name: 'Финансы', href: safeRoute('admin.finance'), roles: ['admin', 'accountant'] },
]);

const canSeeNavItem = (itemRoles) => itemRoles.some((role) => userRoles.value.includes(role));
</script>

<template>
    <div class="min-h-screen bg-gray-100 flex text-gray-900">
        <div v-if="mobileMenuOpen" class="fixed inset-0 z-40 bg-black/40 md:hidden" @click="mobileMenuOpen = false"></div>
        <aside
            :class="mobileMenuOpen ? 'translate-x-0' : '-translate-x-full'"
            class="w-64 bg-slate-900 text-white fixed z-50 md:sticky md:top-0 md:translate-x-0 flex flex-col min-h-screen shrink-0 transition-transform duration-200"
        >
            <a href="/profile">
                <div class="p-6 flex items-center gap-3 border-b border-slate-800">
                    <span class="text-xl font-bold tracking-wider ml-5 uppercase">АЙКИДО</span>
                </div>
            </a>
            <nav class="flex-1 p-4 space-y-2 mt-4 overflow-y-auto">
                <template v-for="item in navigation" :key="item.name">
                    <Link
                        v-if="canSeeNavItem(item.roles)"
                        :href="item.href"
                        :class="[
                            route().current(item.href)
                                ? 'bg-indigo-600 text-white'
                                : 'text-slate-400 hover:bg-slate-800 hover:text-white',
                        ]"
                        class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all group"
                    >
                        <span class="font-medium">{{ item.name }}</span>
                    </Link>
                </template>
            </nav>

            <div class="p-4 border-t border-slate-800">
                <div class="flex items-center gap-3 px-4 py-2 text-sm text-slate-400">
                    <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                    <span>Роль: <span class="font-semibold text-slate-200">{{ roleDisplay }}</span></span>
                </div>
            </div>
        </aside>

        <div class="flex-1 flex flex-col min-w-0 min-h-screen bg-gray-50">
            <header class="bg-white shadow-sm border-b min-h-16 flex items-center justify-between gap-2 px-3 sm:px-4 md:px-8 shrink-0 py-2">
                <button type="button" class="md:hidden shrink-0 p-2 rounded border" @click="mobileMenuOpen = !mobileMenuOpen" aria-label="Меню">☰</button>
                <h2 class="text-sm sm:text-lg font-semibold text-gray-700 truncate min-w-0 flex-1 px-1">
                    <slot name="header" />
                </h2>

                <div class="flex items-center gap-1 sm:gap-3 shrink-0">
                    <NotificationBell />
                    <Dropdown align="right" width="48">
                        <template #trigger>
                            <span class="inline-flex rounded-md">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1 sm:gap-2 px-1 sm:px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none transition ease-in-out duration-150 max-w-[min(100%,12rem)] sm:max-w-none"
                                >
                                    <img
                                        v-if="user?.avatar_url"
                                        :src="user.avatar_url"
                                        class="w-8 h-8 rounded-full object-cover border border-slate-200"
                                        alt=""
                                    />
                                    <span
                                        v-else
                                        class="w-8 h-8 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-xs font-bold"
                                    >
                                        {{ (user?.display_name || user?.name || '?')[0] }}
                                    </span>
                                    <span class="hidden sm:inline truncate max-w-[8rem] md:max-w-[12rem]">{{ user?.display_name || user?.name || 'Загрузка...' }}</span>
                                    <svg
                                        class="ms-0 sm:ms-2 -me-0.5 h-4 w-4 shrink-0 hidden sm:block"
                                        xmlns="http://www.w3.org/2000/svg"
                                        viewBox="0 0 20 20"
                                        fill="currentColor"
                                    >
                                        <path
                                            fill-rule="evenodd"
                                            d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                            clip-rule="evenodd"
                                        />
                                    </svg>
                                </button>
                            </span>
                        </template>

                        <template #content>
                            <DropdownLink :href="route('profile.edit')">Профиль</DropdownLink>
                            <DropdownLink :href="route('logout')" method="post" as="button">Выйти</DropdownLink>
                        </template>
                    </Dropdown>
                </div>
            </header>

            <main class="flex-1 p-3 sm:p-4 md:p-8 min-w-0 max-w-full overflow-x-hidden">
                <slot />
            </main>
        </div>
    </div>
</template>
