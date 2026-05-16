<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import debounce from 'lodash/debounce';

const props = defineProps({ locations: Array, filters: Object });
const search = ref(props.filters?.search || '');

const createForm = useForm({ name: '', address: '' });

const createLocation = () => {
    createForm.post(route('admin.locations.store'), {
        onSuccess: () => createForm.reset(),
    });
};

const saveLocation = (location) => {
    router.patch(route('admin.locations.update', location.id), {
        name: location.name,
        address: location.address || '',
    });
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

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 h-fit">
                <h3 class="font-bold mb-4">Добавить зал</h3>
                <form @submit.prevent="createLocation" class="space-y-3">
                    <input v-model="createForm.name" placeholder="Название зала" class="w-full border-slate-300 rounded-xl" required />
                    <input v-model="createForm.address" placeholder="Адрес" class="w-full border-slate-300 rounded-xl" />
                    <button class="w-full bg-indigo-600 text-white py-2 rounded-xl font-bold">Создать</button>
                </form>
            </div>

            <div class="lg:col-span-2 space-y-3">
                <input v-model="search" class="w-full border-slate-300 rounded-xl" placeholder="Поиск по названию или адресу..." />
                <div v-for="location in locations" :key="location.id" class="bg-white p-4 rounded-2xl shadow-sm border border-slate-100 space-y-2">
                    <div class="grid md:grid-cols-2 gap-2">
                        <input v-model="location.name" placeholder="Название" class="border-slate-300 rounded-xl" />
                        <input v-model="location.address" placeholder="Адрес" class="border-slate-300 rounded-xl" />
                    </div>
                    <div class="flex gap-2 justify-end">
                        <button @click="saveLocation(location)" class="bg-emerald-100 text-emerald-700 px-4 py-2 rounded-xl text-sm font-medium">Сохранить</button>
                        <button @click="removeLocation(location.id)" class="bg-red-100 text-red-700 px-4 py-2 rounded-xl text-sm">Удалить</button>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
