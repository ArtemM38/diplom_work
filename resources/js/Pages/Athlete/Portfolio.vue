<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DateInput from '@/Components/DateInput.vue';
import { formatDisplayDate } from '@/utils/formatDate';
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

const eventDate = (item) => item.event_date_display || formatDisplayDate(item.event_date) || '—';

const placeLabel = (place) => {
    if (place === 1) return '1 место';
    if (place === 2) return '2 место';
    if (place === 3) return '3 место';
    return place ? `${place} место` : null;
};

const placeBadgeClass = (place) => {
    if (place === 1) return 'bg-amber-100 text-amber-800 border-amber-200';
    if (place === 2) return 'bg-slate-100 text-slate-700 border-slate-200';
    if (place === 3) return 'bg-orange-100 text-orange-800 border-orange-200';
    return 'bg-slate-50 text-slate-500 border-slate-200';
};
</script>

<template>
    <Head title="Моё портфолио" />
    <AuthenticatedLayout>
        <template #header>Моё портфолио</template>

        <div class="max-w-4xl mx-auto space-y-4 sm:space-y-6 min-w-0">
            <div class="bg-white rounded-xl sm:rounded-2xl border border-slate-100 shadow-sm p-4 sm:p-6">
                <h3 class="text-base sm:text-lg font-bold text-slate-900 break-anywhere">{{ athleteName }}</h3>
                <p class="text-xs sm:text-sm text-slate-500 mt-1">Достижения и результаты соревнований</p>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 sm:gap-3 mt-4">
                    <div class="rounded-lg sm:rounded-xl bg-indigo-50 p-2.5 sm:p-3 text-center">
                        <p class="text-xl sm:text-2xl font-bold text-indigo-700">{{ stats?.total ?? 0 }}</p>
                        <p class="text-[10px] sm:text-xs text-indigo-600">Всего</p>
                    </div>
                    <div class="rounded-lg sm:rounded-xl bg-amber-50 p-2.5 sm:p-3 text-center">
                        <p class="text-xl sm:text-2xl font-bold text-amber-700">{{ stats?.places_1 ?? 0 }}</p>
                        <p class="text-[10px] sm:text-xs text-amber-600">1 место</p>
                    </div>
                    <div class="rounded-lg sm:rounded-xl bg-slate-100 p-2.5 sm:p-3 text-center">
                        <p class="text-xl sm:text-2xl font-bold text-slate-700">{{ stats?.places_2 ?? 0 }}</p>
                        <p class="text-[10px] sm:text-xs text-slate-600">2 место</p>
                    </div>
                    <div class="rounded-lg sm:rounded-xl bg-orange-50 p-2.5 sm:p-3 text-center">
                        <p class="text-xl sm:text-2xl font-bold text-orange-700">{{ stats?.places_3 ?? 0 }}</p>
                        <p class="text-[10px] sm:text-xs text-orange-600">3 место</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl sm:rounded-2xl border border-slate-100 shadow-sm p-4">
                <p class="text-sm font-medium text-slate-700 mb-3">Фильтры</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <DateInput v-model="dateFrom" label="Дата мероприятия с" input-class="w-full border-gray-300 rounded-lg text-sm" />
                    <DateInput v-model="dateTo" label="Дата мероприятия по" input-class="w-full border-gray-300 rounded-lg text-sm" />
                    <div>
                        <label class="text-xs text-slate-500">Место</label>
                        <select v-model="resultPlace" class="w-full mt-1 border-gray-300 rounded-lg text-sm min-h-[2.75rem]">
                            <option value="">Все</option>
                            <option value="1">1 место</option>
                            <option value="2">2 место</option>
                            <option value="3">3 место</option>
                        </select>
                    </div>
                    <div class="flex flex-col sm:flex-row lg:flex-col xl:flex-row items-stretch sm:items-end gap-2 sm:col-span-2 lg:col-span-1">
                        <button
                            type="button"
                            @click="applyFilters"
                            class="flex-1 bg-indigo-600 text-white py-2.5 sm:py-2 rounded-lg text-sm font-medium hover:bg-indigo-700"
                        >
                            Применить
                        </button>
                        <button
                            type="button"
                            @click="resetFilters"
                            class="px-4 py-2.5 sm:py-2 rounded-lg border border-slate-200 text-sm text-slate-600 hover:bg-slate-50"
                        >
                            Сброс
                        </button>
                    </div>
                </div>
            </div>

            <!-- Мобильные карточки -->
            <div class="md:hidden space-y-3">
                <article
                    v-for="item in achievements"
                    :key="item.id"
                    class="bg-white rounded-xl border border-slate-100 shadow-sm p-4"
                >
                    <div class="flex items-start justify-between gap-2 mb-2">
                        <h4 class="font-semibold text-slate-900 text-sm break-anywhere leading-snug flex-1 min-w-0">
                            {{ item.event_name || '—' }}
                        </h4>
                        <span
                            v-if="placeLabel(item.result_place)"
                            class="shrink-0 text-[10px] px-2 py-0.5 rounded-full border font-semibold"
                            :class="placeBadgeClass(item.result_place)"
                        >
                            {{ placeLabel(item.result_place) }}
                        </span>
                    </div>

                    <dl class="grid grid-cols-2 gap-x-3 gap-y-2 text-xs">
                        <div>
                            <dt class="text-slate-400">Дата</dt>
                            <dd class="font-medium text-slate-700">{{ eventDate(item) }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-400">Тип</dt>
                            <dd class="font-medium text-slate-700 break-anywhere">{{ item.event_type || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-400">Уровень</dt>
                            <dd class="font-medium text-slate-700 break-anywhere">{{ item.event_level || '—' }}</dd>
                        </div>
                        <div v-if="item.result_rank">
                            <dt class="text-slate-400">Разряд</dt>
                            <dd class="font-medium text-slate-700 break-anywhere">{{ item.result_rank }}</dd>
                        </div>
                    </dl>
                </article>

                <div
                    v-if="!achievements?.length"
                    class="p-8 text-center text-slate-400 bg-white rounded-xl border border-slate-100"
                >
                    Пока нет записей в портфолио
                </div>
            </div>

            <!-- Десктоп: таблица -->
            <div class="hidden md:block bg-white rounded-xl sm:rounded-2xl border border-slate-100 shadow-sm overflow-hidden app-table-wrap">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="text-left p-3">Мероприятие</th>
                            <th class="text-left p-3 whitespace-nowrap">Дата</th>
                            <th class="text-left p-3">Тип / уровень</th>
                            <th class="text-left p-3 whitespace-nowrap">Результат</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in achievements" :key="item.id" class="border-t border-slate-100">
                            <td class="p-3 font-medium text-slate-800 break-anywhere max-w-[240px]">{{ item.event_name }}</td>
                            <td class="p-3 text-slate-600 whitespace-nowrap">{{ eventDate(item) }}</td>
                            <td class="p-3 text-slate-600">
                                <span class="break-anywhere">{{ item.event_type || '—' }}</span>
                                <span class="text-slate-400"> · </span>
                                <span class="break-anywhere">{{ item.event_level || '—' }}</span>
                            </td>
                            <td class="p-3 whitespace-nowrap">
                                <span v-if="item.result_place" class="text-indigo-700 font-semibold">{{ placeLabel(item.result_place) }}</span>
                                <span v-else class="text-slate-500">—</span>
                                <span v-if="item.result_rank" class="block text-xs text-slate-500 mt-0.5">{{ item.result_rank }}</span>
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
