<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useForm, Link } from '@inertiajs/vue3';

const props = defineProps({ groups: Array });

const form = useForm({
    name: '',
    type: 'Учебная',
    tariff_amount: 0,
});

const submit = () => {
    form.post(route('admin.groups.store'), {
        onSuccess: () => form.reset(),
    });
};
</script>

<template>
    <AuthenticatedLayout>
        <template #header>Управление группами</template>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Форма создания -->
            <div class="bg-white p-6 rounded-xl shadow-sm h-fit">
                <h3 class="font-bold mb-4">Создать новую группу</h3>
                <form @submit.prevent="submit" class="space-y-4">
                    <div>
                        <label class="text-sm">Название</label>
                        <input v-model="form.name" type="text" class="w-full border-gray-300 rounded-lg">
                    </div>
                    <div>
                        <label class="text-sm">Тип</label>
                        <select v-model="form.type" class="w-full border-gray-300 rounded-lg">
                            <option value="Учебная">Учебная (У)</option>
                            <option value="Спортивная">Спортивная (С)</option>
                            <option value="Индивидуальная">Индивидуальная</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-sm">Стоимость в месяц (руб)</label>
                        <input v-model="form.tariff_amount" type="number" class="w-full border-gray-300 rounded-lg">
                    </div>
                    <button class="w-full bg-indigo-600 text-white py-2 rounded-lg font-bold">Создать</button>
                </form>
            </div>

            <!-- Список групп -->
            <div class="md:col-span-2 space-y-4">
                <div v-for="group in groups" :key="group.id"
                    class="bg-white p-6 rounded-xl shadow-sm flex justify-between items-center">
                    <div>
                        <h4 class="text-lg font-bold text-slate-800">{{ group.name }}</h4>
                        <p class="text-sm text-gray-500">{{ group.type }} • {{ group.tariff_amount }} руб/мес</p>
                        <span class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full mt-2 inline-block">
                            Спортсменов: {{ group.athletes_count }}
                        </span>
                    </div>
                    <Link :href="route('admin.groups.show', group.id)"
                        class="bg-slate-100 hover:bg-slate-200 p-2 rounded-lg transition">
                        Управлять составом →
                    </Link>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>