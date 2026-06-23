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
    pageTitle: { type: String, default: 'Моё портфолио' },
    headerTitle: { type: String, default: 'Моё портфолио' },
    children: { type: Array, default: null },
    selectedAthleteId: { type: Number, default: null },
    portfolioRoute: { type: String, default: 'athlete.portfolio' },
});

const dateFrom = ref(props.filters?.date_from ?? '');
const dateTo = ref(props.filters?.date_to ?? '');
const resultPlace = ref(props.filters?.result_place ?? '');
const selectedChildId = ref(props.selectedAthleteId ?? props.children?.[0]?.id ?? '');
const expandedId = ref(null);

const applyFilters = () => {
    router.get(route(props.portfolioRoute), {
        athlete_id: props.children ? selectedChildId.value || undefined : undefined,
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

const onChildChange = () => {
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

const toggleDetails = (id) => {
    expandedId.value = expandedId.value === id ? null : id;
};

const formatCost = (cost) => {
    if (cost == null || cost === '') return null;
    return `${Number(cost).toFixed(2).replace('.', ',')} ₽`;
};
</script>

<template>
    <Head :title="pageTitle" />
    <AuthenticatedLayout>
        <template #header>{{ headerTitle }}</template>

        <div class="max-w-4xl mx-auto space-y-4 sm:space-y-6 min-w-0">
            <div v-if="children?.length" class="bg-white rounded-xl border border-slate-100 shadow-sm p-4">
                <label class="text-sm font-medium text-slate-700">Спортсмен</label>
                <select v-model="selectedChildId" class="w-full mt-1 border-gray-300 rounded-lg" @change="onChildChange">
                    <option v-for="child in children" :key="child.id" :value="child.id">{{ child.full_name }}</option>
                </select>
            </div>

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
                        <button type="button" @click="applyFilters" class="flex-1 bg-indigo-600 text-white py-2.5 sm:py-2 rounded-lg text-sm font-medium hover:bg-indigo-700">Применить</button>
                        <button type="button" @click="resetFilters" class="px-4 py-2.5 sm:py-2 rounded-lg border border-slate-200 text-sm text-slate-600 hover:bg-slate-50">Сброс</button>
                    </div>
                </div>
            </div>

            <div class="space-y-3">
                <article
                    v-for="item in achievements"
                    :key="item.id"
                    class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden"
                >
                    <button type="button" class="w-full text-left p-4" @click="toggleDetails(item.id)">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0 flex-1">
                                <h4 class="font-semibold text-slate-900 text-sm break-anywhere">{{ item.event_name || '—' }}</h4>
                                <p class="text-xs text-slate-500 mt-1">{{ eventDate(item) }} · {{ item.event_type || '—' }}</p>
                                <div v-if="item.evidence_files?.length" class="flex flex-wrap gap-1.5 mt-2">
                                    <a
                                        v-for="file in item.evidence_files"
                                        :key="file.id || file.url"
                                        :href="file.url"
                                        target="_blank"
                                        class="inline-flex items-center gap-1 rounded-md border border-indigo-200 bg-indigo-50 px-2 py-0.5 text-[11px] text-indigo-700 hover:underline max-w-full"
                                        @click.stop
                                    >
                                        <span class="break-anywhere">{{ file.original_name }}</span>
                                    </a>
                                </div>
                            </div>
                            <span
                                v-if="placeLabel(item.result_place)"
                                class="shrink-0 text-[10px] px-2 py-0.5 rounded-full border font-semibold"
                                :class="placeBadgeClass(item.result_place)"
                            >
                                {{ placeLabel(item.result_place) }}
                            </span>
                        </div>
                    </button>

                    <div v-if="expandedId === item.id" class="border-t border-slate-100 p-4 bg-slate-50/60 text-sm space-y-3">
                        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-2">
                            <div><dt class="text-slate-500">Место проведения</dt><dd class="font-medium">{{ item.event_place || '—' }}</dd></div>
                            <div><dt class="text-slate-500">Уровень</dt><dd class="font-medium">{{ item.event_level || '—' }}</dd></div>
                            <div><dt class="text-slate-500">Организатор</dt><dd class="font-medium">{{ item.event_host || '—' }}</dd></div>
                            <div v-if="formatCost(item.event_cost)"><dt class="text-slate-500">Стоимость</dt><dd class="font-medium">{{ formatCost(item.event_cost) }}</dd></div>
                            <div v-if="item.result_rank"><dt class="text-slate-500">Разряд</dt><dd class="font-medium">{{ item.result_rank }}</dd></div>
                            <div v-if="item.result_label"><dt class="text-slate-500">Результат</dt><dd class="font-medium">{{ item.result_label }}</dd></div>
                            <div v-if="item.certificate_id"><dt class="text-slate-500">ID сертификата</dt><dd class="font-medium">{{ item.certificate_id }}</dd></div>
                        </dl>
                        <p v-if="item.result_description" class="text-slate-700"><span class="text-slate-500">Описание:</span> {{ item.result_description }}</p>
                        <div v-if="item.evidence_files?.length">
                            <p class="text-slate-500 mb-2">Подтверждающие файлы</p>
                            <ul class="space-y-1">
                                <li v-for="file in item.evidence_files" :key="file.id || file.url">
                                    <a :href="file.url" target="_blank" class="text-indigo-600 hover:underline break-anywhere">{{ file.original_name }}</a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </article>

                <div v-if="!achievements?.length" class="p-8 text-center text-slate-400 bg-white rounded-xl border border-slate-100">
                    Пока нет записей в портфолио
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
