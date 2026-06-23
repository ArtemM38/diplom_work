<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import FormErrorsAlert from '@/Components/FormErrorsAlert.vue';
import InputError from '@/Components/InputError.vue';
import { fieldClass } from '@/utils/formErrors';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';
import debounce from 'lodash/debounce';

const props = defineProps({ items: Object, filters: Object });
const itemsList = computed(() => props.items?.data ?? []);
const search = ref(props.filters?.search || '');

const createForm = useForm({ name: '' });

const createItem = () => {
    createForm.post(route('admin.inventory-items.store'), {
        onSuccess: () => createForm.reset(),
    });
};

const saveItem = (item) => {
    router.patch(route('admin.inventory-items.update', item.id), {
        name: item.name,
        sort_order: item.sort_order,
        is_active: item.is_active,
    });
};

const removeItem = (itemId) => {
    if (confirm('Удалить позицию инвентаря?')) {
        router.delete(route('admin.inventory-items.destroy', itemId));
    }
};

watch(search, debounce((value) => {
    router.get(route('admin.inventory-items'), { search: value || undefined }, { preserveState: true, replace: true });
}, 300));
</script>

<template>
    <Head title="Инвентарь" />
    <AuthenticatedLayout>
        <template #header>Справочник инвентаря</template>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 h-fit">
                <h3 class="font-bold mb-4">Добавить позицию</h3>
                <form @submit.prevent="createItem" class="space-y-3">
                    <FormErrorsAlert :errors="createForm.errors" />
                    <div>
                        <input v-model="createForm.name" placeholder="Название" :class="fieldClass(createForm.errors, 'name', 'w-full rounded-xl')" required />
                        <InputError :message="createForm.errors.name" />
                    </div>
                    <button type="submit" class="w-full bg-indigo-600 text-white py-2 rounded-xl font-bold" :disabled="createForm.processing">
                        Создать
                    </button>
                </form>
            </div>

            <div class="lg:col-span-2 space-y-3">
                <input v-model="search" class="w-full border-slate-300 rounded-xl" placeholder="Поиск..." />
                <div v-for="item in itemsList" :key="item.id" class="bg-white p-4 rounded-2xl shadow-sm border border-slate-100 space-y-2">
                    <div class="grid md:grid-cols-3 gap-2">
                        <input v-model="item.name" placeholder="Название" class="border-slate-300 rounded-xl md:col-span-2" />
                        <input v-model.number="item.sort_order" type="number" min="0" placeholder="Порядок" class="border-slate-300 rounded-xl" />
                    </div>
                    <label class="flex items-center gap-2 text-sm text-slate-600">
                        <input v-model="item.is_active" type="checkbox" class="rounded text-indigo-600" />
                        Активна (отображается в анкетах)
                    </label>
                    <div class="flex gap-2 justify-end">
                        <button type="button" @click="saveItem(item)" class="bg-emerald-100 text-emerald-700 px-4 py-2 rounded-xl text-sm font-medium">Сохранить</button>
                        <button type="button" @click="removeItem(item.id)" class="bg-red-100 text-red-700 px-4 py-2 rounded-xl text-sm">Удалить</button>
                    </div>
                </div>
                <Pagination :links="items.links" :meta="items" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
