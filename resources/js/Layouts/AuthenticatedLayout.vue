<script setup>
import { ref, computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';

const showingNavigationDropdown = ref(false);
const page = usePage();
const user = computed(() => page.props.auth?.user);

// Функция для безопасной проверки существования маршрута перед вызовом route()
const safeRoute = (name, fallback = '#') => {
    try {
        return route(name);
    } catch (e) {
        return fallback;
    }
};

const navigation = computed(() => [
    { name: 'Реестр спортсменов', href: safeRoute('admin.athletes'), icon: '', roles: ['admin', 'coach'] },
    { name: 'Портфолио', href: safeRoute('admin.portfolio'), icon: '', roles: ['admin', 'coach'] },
    { name: 'Группы и секции', href: safeRoute('admin.groups'), icon: '', roles: ['admin', 'coach'] },
    { name: 'Расписание', href: safeRoute('admin.schedule'), icon: '', roles: ['admin', 'coach', 'athlete'] },
    { name: 'Залы', href: safeRoute('admin.locations'), icon: '', roles: ['admin'] },
    { name: 'Пользователи', href: safeRoute('admin.coaches'), icon: '', roles: ['admin'] },
    { name: 'Табель', href: safeRoute('admin.attendance.journal'), icon: '', roles: ['admin', 'coach'] },
    { name: 'Финансы', href: safeRoute('admin.finance'), icon: '', roles: ['admin', 'accountant'] },
]);

const isRole = (roles) => {
    return user.value && roles.includes(user.value.role);
};
</script>

<template>
    <div class="min-h-screen bg-gray-100 flex text-gray-900">
        <!-- SIDEBAR -->
        <aside class="w-64 bg-slate-900 text-white hidden md:flex flex-col sticky top-0 h-screen shrink-0">
            <a href="/dashboard">
            <div class="p-6 flex items-center gap-3 border-b border-slate-800">
                <span class="text-xl font-bold tracking-wider ml-5 uppercase">Sport CRM</span>
            </div>
        </a>
            <nav class="flex-1 p-4 space-y-2 mt-4 overflow-y-auto">
                <template v-for="item in navigation" :key="item.name">
                    <Link v-if="isRole(item.roles)" :href="item.href"
                        :class="[route().current(item.href) ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:bg-slate-800 hover:text-white']"
                        class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all group">
                        <span class="text-xl">{{ item.icon }}</span>
                        <span class="font-medium">{{ item.name }}</span>
                    </Link>
                </template>
            </nav>

            <div class="p-4 border-t border-slate-800">
                <div class="flex items-center gap-3 px-4 py-2 text-sm text-slate-400">
                    <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                    Роль: <span class="uppercase font-bold text-xs">{{ user?.role }}</span>
                </div>
            </div>
        </aside>

        <!-- MAIN CONTENT -->
        <div class="flex-1 flex flex-col min-w-0 overflow-hidden">
            <header class="bg-white shadow-sm border-b h-16 flex items-center justify-between px-8 shrink-0">
                <h2 class="text-lg font-semibold text-gray-700">
                    <slot name="header" />
                </h2>

                <div class="flex items-center gap-4">
                    <Dropdown align="right" width="48">
                        <template #trigger>
                            <span class="inline-flex rounded-md">
                                <button type="button"
                                    class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none transition ease-in-out duration-150">
                                    {{ user?.name || 'Загрузка...' }}
                                    <svg class="ms-2 -me-0.5 h-4 w-4" xmlns="http://www.w3.org/2000/svg"
                                        viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd"
                                            d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                            clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </span>
                        </template>

                        <template #content>
                            <!-- Теперь эти маршруты существуют в web.php -->
                            <DropdownLink :href="route('profile.edit')"> Профиль </DropdownLink>
                            <DropdownLink :href="route('logout')" method="post" as="button"> Выйти </DropdownLink>
                        </template>
                    </Dropdown>
                </div>
            </header>

            <main class="flex-1 overflow-y-auto p-8 bg-gray-50">
                <slot />
            </main>
        </div>
    </div>
</template>