<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    athleteName: String,
    achievements: Array,
    stats: Object,
    filters: Object,
});

const dateFrom = ref(props.filters?.date_from ?? '');
const dateTo = ref(props.filters?.date_to ?? '');
const resultPlace = ref(props.filters?.result_place ?? '');

const applyFilters = () => {
    router.get(route('athlete.portfolio'), {
        date_from: dateFrom.value || undefined,
        date_to: dateTo.value || undefined,
        result_place: resultPlace.value || undefined,
    }, { preserveState: true, replace: true });
};

const resetFilters = () => {
    dateFrom.value = '';
    dateTo.value = '';
    resultPlace.value = '';
    applyFilters();
};
</script>

<template>
    <Head title="Моё портфолио" />
    <AuthenticatedLayout>
        <template #header>Моё портфолио</template>

        <div class="max-w-4xl mx-auto space-y-6">
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6">
                <h3 class="text-lg font-bold text-slate-900">{{ athleteName }}</h3>
                <p class="text-sm text-slate-500 mt-1">Достижения и результаты соревнований</p>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mt-4">
                    <div class="rounded-xl bg-indigo-50 p-3 text-center">
                        <p class="text-2xl font-bold text-indigo-700">{{ stats?.total ?? 0 }}</p>
                        <p class="text-xs text-indigo-600">Всего</p>
                    </div>
                    <div class="rounded-xl bg-amber-50 p-3 text-center">
                        <p class="text-2xl font-bold text-amber-700">{{ stats?.places_1 ?? 0 }}</p>
                        <p class="text-xs text-amber-600">1 место</p>
                    </div>
                    <div class="rounded-xl bg-slate-100 p-3 text-center">
                        <p class="text-2xl font-bold text-slate-700">{{ stats?.places_2 ?? 0 }}</p>
                        <p class="text-xs text-slate-600">2 место</p>
                    </div>
                    <div class="rounded-xl bg-orange-50 p-3 text-center">
                        <p class="text-2xl font-bold text-orange-700">{{ stats?.places_3 ?? 0 }}</p>
                        <p class="text-xs text-orange-600">3 место</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4">
                <p class="text-sm font-medium text-slate-700 mb-3">Фильтры</p>
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                    <div>
                        <label class="text-xs text-slate-500">Дата с</label>
                        <input v-model="dateFrom" type="date" class="w-full mt-1 border-gray-300 rounded-lg text-sm" />
                    </div>
                    <div>
                        <label class="text-xs text-slate-500">Дата по</label>
                        <input v-model="dateTo" type="date" class="w-full mt-1 border-gray-300 rounded-lg text-sm" />
                    </div>
                    <div>
                        <label class="text-xs text-slate-500">Место</label>
                        <select v-model="resultPlace" class="w-full mt-1 border-gray-300 rounded-lg text-sm">
                            <option value="">Все</option>
                            <option value="1">1 место</option>
                            <option value="2">2 место</option>
                            <option value="3">3 место</option>
                        </select>
                    </div>
                    <div class="flex items-end gap-2">
                        <button type="button" @click="applyFilters" class="flex-1 bg-indigo-600 text-white py-2 rounded-lg text-sm font-medium hover:bg-indigo-700">Применить</button>
                        <button type="button" @click="resetFilters" class="px-3 py-2 rounded-lg border border-slate-200 text-sm text-slate-600 hover:bg-slate-50">Сброс</button>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden app-table-wrap">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="text-left p-3">Мероприятие</th>
                            <th class="text-left p-3">Дата</th>
                            <th class="text-left p-3">Тип / уровень</th>
                            <th class="text-left p-3">Результат</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in achievements" :key="item.id" class="border-t border-slate-100">
                            <td class="p-3 font-medium text-slate-800">{{ item.event_name }}</td>
                            <td class="p-3 text-slate-600">{{ item.event_date || '—' }}</td>
                            <td class="p-3 text-slate-600">{{ item.event_type || '—' }} · {{ item.event_level || '—' }}</td>
                            <td class="p-3">
                                <span v-if="item.result_place" class="text-indigo-700 font-semibold">{{ item.result_place }} место</span>
                                <span v-else class="text-slate-500">—</span>
                                <span v-if="item.result_rank" class="block text-xs text-slate-500">{{ item.result_rank }}</span>
                            </td>
                        </tr>
                        <tr v-if="!achievements?.length">
                            <td colspan="4" class="p-8 text-center text-slate-400">Пока нет записей в портфолио</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
