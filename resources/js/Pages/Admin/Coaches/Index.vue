<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import debounce from 'lodash/debounce';

const props = defineProps({ coaches: Array, filters: Object });
const search = ref(props.filters?.search || '');

const createForm = useForm({
    name: '',
    email: '',
    password: '',
});

const createCoach = () => {
    createForm.post(route('admin.coaches.store'), {
        onSuccess: () => createForm.reset(),
    });
};

const updateCoach = (coach) => {
    router.patch(route('admin.coaches.update', coach.id), {
        name: coach.name,
        email: coach.email,
        password: coach.password || '',
        is_active: !!coach.is_active,
    });
};

const deleteCoach = (coachId) => {
    if (confirm('Удалить тренера?')) {
        router.delete(route('admin.coaches.destroy', coachId));
    }
};

watch(search, debounce((value) => {
    router.get(route('admin.coaches'), { search: value }, { preserveState: true, replace: true });
}, 300));
</script>

<template>
    <Head title="Тренеры" />
    <AuthenticatedLayout>
        <template #header>Управление тренерами</template>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-white p-6 rounded-xl shadow-sm h-fit">
                <h3 class="font-bold mb-4">Добавить тренера</h3>
                <form @submit.prevent="createCoach" class="space-y-3">
                    <input v-model="createForm.name" placeholder="ФИО" class="w-full border-gray-300 rounded-lg" required />
                    <input v-model="createForm.email" type="email" placeholder="Email" class="w-full border-gray-300 rounded-lg" required />
                    <input v-model="createForm.password" type="password" placeholder="Пароль" class="w-full border-gray-300 rounded-lg" required />
                    <button class="w-full bg-indigo-600 text-white py-2 rounded-lg font-bold">Создать</button>
                </form>
            </div>

            <div class="md:col-span-2 space-y-3">
                <input v-model="search" class="w-full border-gray-300 rounded-lg" placeholder="Поиск тренеров..." />
                <div v-for="coach in coaches" :key="coach.id" class="bg-white p-4 rounded-xl shadow-sm grid grid-cols-1 md:grid-cols-5 gap-2 items-center">
                    <input v-model="coach.name" class="border-gray-300 rounded-lg md:col-span-2" />
                    <input v-model="coach.email" class="border-gray-300 rounded-lg md:col-span-2" />
                    <label class="flex items-center text-sm"><input type="checkbox" v-model="coach.is_active" class="mr-2" /> Активен</label>
                    <input v-model="coach.password" type="password" class="border-gray-300 rounded-lg md:col-span-2" placeholder="Новый пароль (опц.)" />
                    <div class="flex gap-2 md:col-span-3">
                        <button @click="updateCoach(coach)" class="bg-emerald-100 text-emerald-700 px-3 py-2 rounded-lg">Сохранить</button>
                        <button @click="deleteCoach(coach.id)" class="bg-red-100 text-red-700 px-3 py-2 rounded-lg">Удалить</button>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
