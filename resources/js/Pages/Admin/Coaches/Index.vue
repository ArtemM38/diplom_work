<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import debounce from 'lodash/debounce';

const props = defineProps({ users: Array, roles: Array, filters: Object });
const search = ref(props.filters?.search || '');
const roleFilter = ref(props.filters?.role || 'all');
const activeFilter = ref(props.filters?.active || 'all');

const createForm = useForm({
    name: '',
    email: '',
    password: '',
    role: 'coach',
    is_active: true,
});

const createCoach = () => {
    createForm.post(route('admin.coaches.store'), {
        onSuccess: () => createForm.reset(),
    });
};

const updateCoach = (user) => {
    router.patch(route('admin.coaches.update', user.id), {
        name: user.name,
        email: user.email,
        password: user.password || '',
        role: user.role,
        is_active: !!user.is_active,
    });
};

const deleteCoach = (userId) => {
    if (confirm('Удалить аккаунт?')) {
        router.delete(route('admin.coaches.destroy', userId));
    }
};

const toggleStatus = (userId) => {
    router.patch(route('admin.coaches.toggle-status', userId));
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

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 h-fit">
                <h3 class="font-bold mb-4 text-slate-800">Создать аккаунт</h3>
                <form @submit.prevent="createCoach" class="space-y-3">
                    <input v-model="createForm.name" placeholder="ФИО" class="w-full border-slate-300 rounded-xl" required />
                    <input v-model="createForm.email" type="email" placeholder="Email" class="w-full border-slate-300 rounded-xl" required />
                    <input v-model="createForm.password" type="password" placeholder="Пароль" class="w-full border-slate-300 rounded-xl" required />
                    <select v-model="createForm.role" class="w-full border-slate-300 rounded-xl">
                        <option v-for="role in roles" :key="role" :value="role">{{ role }}</option>
                    </select>
                    <label class="flex items-center text-sm text-slate-600">
                        <input type="checkbox" v-model="createForm.is_active" class="mr-2 rounded border-slate-300" />
                        Активный аккаунт
                    </label>
                    <button class="w-full bg-indigo-600 text-white py-2 rounded-lg font-bold">Создать</button>
                </form>
            </div>

            <div class="md:col-span-2 space-y-3">
                <div class="grid md:grid-cols-3 gap-2">
                    <input v-model="search" class="w-full border-slate-300 rounded-xl" placeholder="Поиск аккаунтов..." />
                    <select v-model="roleFilter" class="border-slate-300 rounded-xl">
                        <option value="all">Все роли</option>
                        <option v-for="role in roles" :key="role" :value="role">{{ role }}</option>
                    </select>
                    <select v-model="activeFilter" class="border-slate-300 rounded-xl">
                        <option value="all">Все статусы</option>
                        <option value="1">Активные</option>
                        <option value="0">Неактивные</option>
                    </select>
                </div>
                <div v-for="user in users" :key="user.id" class="bg-white p-4 rounded-2xl shadow-sm border border-slate-200 grid grid-cols-1 md:grid-cols-6 gap-2 items-center">
                    <input v-model="user.name" class="border-slate-300 rounded-xl md:col-span-2" />
                    <input v-model="user.email" class="border-slate-300 rounded-xl md:col-span-2" />
                    <select v-model="user.role" class="border-slate-300 rounded-xl">
                        <option v-for="role in roles" :key="role" :value="role">{{ role }}</option>
                    </select>
                    <input v-model="user.password" type="password" class="border-slate-300 rounded-xl md:col-span-3" placeholder="Новый пароль (опц.)" />
                    <div class="flex gap-2 md:col-span-3">
                        <button @click="updateCoach(user)" class="bg-emerald-100 text-emerald-700 px-3 py-2 rounded-lg">Сохранить</button>
                        <button
                            @click="toggleStatus(user.id)"
                            :class="user.is_active ? 'bg-yellow-100 text-yellow-700' : 'bg-blue-100 text-blue-700'"
                            class="px-3 py-2 rounded-lg"
                        >
                            {{ user.is_active ? 'Сделать неактивным' : 'Активировать' }}
                        </button>
                        <button @click="deleteCoach(user.id)" class="bg-red-100 text-red-700 px-3 py-2 rounded-lg">Удалить</button>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
