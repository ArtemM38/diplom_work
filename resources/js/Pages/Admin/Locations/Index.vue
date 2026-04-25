<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import debounce from 'lodash/debounce';

const props = defineProps({ locations: Array, filters: Object });
const search = ref(props.filters?.search || '');

const createForm = useForm({ name: '' });

const createLocation = () => {
    createForm.post(route('admin.locations.store'), {
        onSuccess: () => createForm.reset(),
    });
};

const saveLocation = (location) => {
    router.patch(route('admin.locations.update', location.id), { name: location.name });
};

const removeLocation = (locationId) => {
    if (confirm('Удалить зал?')) {
        router.delete(route('admin.locations.destroy', locationId));
    }
};

watch(search, debounce((value) => {
    router.get(route('admin.locations'), { search: value }, { preserveState: true, replace: true });
}, 300));
</script>

<template>
    <Head title="Залы" />
    <AuthenticatedLayout>
        <template #header>Управление залами</template>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-white p-6 rounded-xl shadow-sm h-fit">
                <h3 class="font-bold mb-4">Добавить зал</h3>
                <form @submit.prevent="createLocation" class="space-y-3">
                    <input v-model="createForm.name" placeholder="Название зала" class="w-full border-gray-300 rounded-lg" required />
                    <button class="w-full bg-indigo-600 text-white py-2 rounded-lg font-bold">Создать</button>
                </form>
            </div>

            <div class="md:col-span-2 space-y-3">
                <input v-model="search" class="w-full border-gray-300 rounded-lg" placeholder="Поиск зала..." />
                <div v-for="location in locations" :key="location.id" class="bg-white p-4 rounded-xl shadow-sm flex gap-2">
                    <input v-model="location.name" class="w-full border-gray-300 rounded-lg" />
                    <button @click="saveLocation(location)" class="bg-emerald-100 text-emerald-700 px-3 rounded-lg">Сохранить</button>
                    <button @click="removeLocation(location.id)" class="bg-red-100 text-red-700 px-3 rounded-lg">Удалить</button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
