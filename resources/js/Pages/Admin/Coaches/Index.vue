<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';
import debounce from 'lodash/debounce';

const props = defineProps({ users: Object, roles: Array, roleLabels: Object, filters: Object });
const usersList = computed(() => props.users?.data ?? []);
const search = ref(props.filters?.search || '');
const roleFilter = ref(props.filters?.role || 'all');
const activeFilter = ref(props.filters?.active || 'all');

const staffRoles = ['admin', 'accountant', 'coach'];

const createForm = useForm({
    name: '',
    email: '',
    password: '',
    roles: ['coach'],
    is_active: true,
});

const toggleCreateRole = (role) => {
    if (createForm.roles.includes(role)) {
        createForm.roles = createForm.roles.filter((r) => r !== role);
    } else {
        createForm.roles = [...createForm.roles, role];
    }
};

const toggleUserRole = (user, role) => {
    if (!user.roles) {
        user.roles = [user.role];
    }
    if (user.roles.includes(role)) {
        user.roles = user.roles.filter((r) => r !== role);
    } else {
        user.roles = [...user.roles, role];
    }
};

const createCoach = () => {
    createForm.post(route('admin.coaches.store'), {
        onSuccess: () => createForm.reset('name', 'email', 'password'),
    });
};

const updateCoach = (user) => {
    router.patch(route('admin.coaches.update', user.id), {
        name: user.name,
        email: user.email,
        password: user.password || '',
        roles: user.roles?.length ? user.roles : [user.role],
        is_active: !!user.is_active,
    });
};

const deleteCoach = (user) => {
    if (user.is_self) return;
    if (confirm('Удалить аккаунт?')) {
        router.delete(route('admin.coaches.destroy', user.id));
    }
};

const toggleStatus = (user) => {
    if (user.is_self) return;
    router.patch(route('admin.coaches.toggle-status', user.id));
};

watch(search, debounce((value) => {
    router.get(route('admin.coaches'), { search: value, role: roleFilter.value, active: activeFilter.value }, { preserveState: true, replace: true });
}, 300));

watch([roleFilter, activeFilter], () => {
    router.get(route('admin.coaches'), { search: search.value, role: roleFilter.value, active: activeFilter.value }, { preserveState: true, replace: true });
});
</script>

<template>
    <Head title="Аккаунты" />
    <AuthenticatedLayout>
        <template #header>Управление аккаунтами</template>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 h-fit">
                <h3 class="font-bold mb-4 text-slate-800">Создать аккаунт</h3>
                <form @submit.prevent="createCoach" class="space-y-3">
                    <input v-model="createForm.name" placeholder="ФИО" class="w-full border-slate-300 rounded-xl" required />
                    <input v-model="createForm.email" type="email" placeholder="Email" class="w-full border-slate-300 rounded-xl" required />
                    <input v-model="createForm.password" type="password" placeholder="Пароль" class="w-full border-slate-300 rounded-xl" required />
                    <div class="space-y-1">
                        <p class="text-xs font-medium text-slate-500 uppercase">Роли</p>
                        <label v-for="role in staffRoles" :key="role" class="flex items-center text-sm gap-2">
                            <input type="checkbox" :checked="createForm.roles.includes(role)" @change="toggleCreateRole(role)" />
                            {{ roleLabels[role] || role }}
                        </label>
                    </div>
                    <label class="flex items-center text-sm text-slate-600">
                        <input type="checkbox" v-model="createForm.is_active" class="mr-2 rounded border-slate-300" />
                        Активный аккаунт
                    </label>
                    <button class="w-full bg-indigo-600 text-white py-2 rounded-lg font-bold">Создать</button>
                </form>
            </div>

            <div class="lg:col-span-2 space-y-3">
                <div class="grid md:grid-cols-3 gap-2">
                    <input v-model="search" class="w-full border-slate-300 rounded-xl" placeholder="Поиск по ФИО или email..." />
                    <select v-model="roleFilter" class="border-slate-300 rounded-xl">
                        <option value="all">Все роли</option>
                        <option v-for="role in roles" :key="role" :value="role">{{ roleLabels[role] || role }}</option>
                    </select>
                    <select v-model="activeFilter" class="border-slate-300 rounded-xl">
                        <option value="all">Все статусы</option>
                        <option value="1">Активные</option>
                        <option value="0">Неактивные</option>
                    </select>
                </div>

                <div
                    v-for="user in usersList"
                    :key="user.id"
                    class="bg-white p-4 rounded-2xl shadow-sm border border-slate-200 space-y-3"
                >
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <Link :href="route('admin.users.show', user.id)" class="font-semibold text-indigo-700 hover:underline">
                                {{ user.display_name }}
                            </Link>
                            <p class="text-xs text-slate-500">{{ user.email }}</p>
                        </div>
                        <span class="text-xs px-2 py-1 rounded-full bg-slate-100 text-slate-600">{{ user.role_labels }}</span>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                        <input v-model="user.name" class="border-slate-300 rounded-xl text-sm" placeholder="ФИО в аккаунте" />
                        <input v-model="user.email" class="border-slate-300 rounded-xl text-sm" />
                    </div>

                    <div class="flex flex-wrap gap-3 text-sm">
                        <label v-for="role in staffRoles" :key="`${user.id}-${role}`" class="flex items-center gap-1">
                            <input
                                type="checkbox"
                                :checked="(user.roles || [user.role]).includes(role)"
                                @change="toggleUserRole(user, role)"
                            />
                            {{ roleLabels[role] || role }}
                        </label>
                    </div>

                    <input v-model="user.password" type="password" class="border-slate-300 rounded-xl w-full text-sm" placeholder="Новый пароль (опционально)" />

                    <div class="flex flex-wrap gap-2">
                        <button @click="updateCoach(user)" class="bg-emerald-100 text-emerald-700 px-3 py-2 rounded-lg text-sm font-medium">Сохранить</button>
                        <button
                            v-if="!user.is_self"
                            @click="toggleStatus(user)"
                            :class="user.is_active ? 'bg-yellow-100 text-yellow-700' : 'bg-blue-100 text-blue-700'"
                            class="px-3 py-2 rounded-lg text-sm"
                        >
                            {{ user.is_active ? 'Сделать неактивным' : 'Активировать' }}
                        </button>
                        <span v-else class="text-xs text-slate-400 self-center px-2">Свой аккаунт</span>
                        <button
                            v-if="!user.is_self"
                            @click="deleteCoach(user)"
                            class="bg-red-100 text-red-700 px-3 py-2 rounded-lg text-sm"
                        >
                            Удалить
                        </button>
                        <Link :href="route('admin.users.show', user.id)" class="ml-auto text-sm text-indigo-600 self-center hover:underline">Профиль →</Link>
                    </div>
                </div>
                <Pagination :links="users.links" :meta="users" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
