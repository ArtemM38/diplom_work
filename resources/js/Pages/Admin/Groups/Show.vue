<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useForm, Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    group: Object,
    allAthletes: Array
});

const form = useForm({
    athlete_id: '',
});

const addAthlete = () => {
    form.post(route('admin.groups.attach', props.group.id), {
        onSuccess: () => form.reset(),
    });
};

const removeAthlete = (athleteId) => {
    if (confirm('Исключить спортсмена из этой группы?')) {
        form.delete(route('admin.groups.detach', [props.group.id, athleteId]));
    }
};
</script>

<template>

    <Head :title="'Группа: ' + group.name" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('admin.groups')" class="text-indigo-600">← Группы</Link>
                <span>Управление составом: {{ group.name }}</span>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 grid grid-cols-1 md:grid-cols-3 gap-6">

                <!-- ЛЕВАЯ КОЛОНКА: Текущий состав -->
                <div class="md:col-span-2 bg-white shadow-sm rounded-xl overflow-hidden">
                    <div class="p-6 border-b flex justify-between items-center">
                        <h3 class="font-bold text-lg text-slate-800">Список группы</h3>
                        <span class="bg-indigo-100 text-indigo-700 px-3 py-1 rounded-full text-xs font-bold">
                            Всего: {{ group.athletes.length }}
                        </span>
                    </div>

                    <table class="w-full">
                        <thead class="bg-gray-50 border-b">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Спортсмены
                                </th>
                                <th class="px-6 py-3 text-right"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="athlete in group.athletes" :key="athlete.id" class="hover:bg-gray-50">
                                <td class="px-6 py-4">
                                    <div class="font-medium text-gray-900">
                                        {{ athlete.last_name_nom }} {{ athlete.first_name_nom }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <button @click="removeAthlete(athlete.id)"
                                        class="text-red-400 hover:text-red-600 text-sm">
                                        Исключить
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="group.athletes.length === 0">
                                <td colspan="2" class="px-6 py-10 text-center text-gray-400 italic">
                                    В этой группе пока нет спортсменов
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- ПРАВАЯ КОЛОНКА: Добавление в группу -->
                <div class="bg-white p-6 shadow-sm rounded-xl h-fit">
                    <h3 class="font-bold mb-4 text-slate-800">Зачислить в группу</h3>
                    <form @submit.prevent="addAthlete" class="space-y-4">
                        <div>
                            <label class="text-xs text-gray-500 block mb-1">Выберите спортсмена</label>
                            <select v-model="form.athlete_id"
                                class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500">
                                <option value="">-- Выбрать из реестра --</option>
                                <option v-for="ath in allAthletes" :key="ath.id" :value="ath.id">
                                    {{ ath.last_name_nom }} {{ ath.first_name_nom }}
                                </option>
                            </select>
                        </div>
                        <button
                            class="w-full bg-indigo-600 text-white py-2 rounded-lg font-bold hover:bg-indigo-700 transition"
                            :disabled="!form.athlete_id">
                            Добавить в состав
                        </button>
                    </form>

                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>