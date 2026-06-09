<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DateInput from '@/Components/DateInput.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    ranks: Array,
    groups: Array,
    events: Array,
    athletes: Array,
    filters: Object,
});

const dateFrom = ref(props.filters?.date_from || '');
const dateTo = ref(props.filters?.date_to || '');
const fio = ref(props.filters?.fio || '');
const rankId = ref(props.filters?.rank_id || '');
const groupId = ref(props.filters?.group_id || '');
const eventId = ref(props.filters?.event_id || '');
const ageFrom = ref(props.filters?.age_from || '');
const ageTo = ref(props.filters?.age_to || '');

const query = (extra = {}) => {
    const params = {
        date_from: dateFrom.value,
        date_to: dateTo.value,
        ...extra,
    };
    if (fio.value) params.fio = fio.value;
    if (rankId.value) params.rank_id = rankId.value;
    if (groupId.value) params.group_id = groupId.value;
    if (eventId.value) params.event_id = eventId.value;
    if (ageFrom.value !== '' && ageFrom.value !== null) params.age_from = ageFrom.value;
    if (ageTo.value !== '' && ageTo.value !== null) params.age_to = ageTo.value;
    return params;
};

const download = (routeName, format) => {
    const params = query({ format });
    const qs = new URLSearchParams(params).toString();
    window.location.href = `${route(routeName)}?${qs}`;
};

const applyFilters = () => {
    router.get(route('admin.reports'), query(), {
        preserveState: true,
        replace: true,
    });
};

const resetFilters = () => {
    fio.value = '';
    rankId.value = '';
    groupId.value = '';
    eventId.value = '';
    ageFrom.value = '';
    ageTo.value = '';
    dateFrom.value = props.filters?.date_from || '';
    dateTo.value = props.filters?.date_to || '';
    router.get(route('admin.reports'), {
        date_from: dateFrom.value,
        date_to: dateTo.value,
    }, {
        preserveState: true,
        replace: true,
    });
};
</script>

<template>
    <Head title="Отчёты" />
    <AuthenticatedLayout>
        <template #header>Отчёты</template>

        <div class="max-w-6xl space-y-6">
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 sm:p-6 space-y-4">
                <h3 class="font-bold text-slate-800">Параметры периода</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    <div class="sm:col-span-2 lg:col-span-3">
                        <label class="text-xs text-slate-500 uppercase font-medium">ФИО спортсмена</label>
                        <input v-model="fio" type="text" class="w-full mt-1 border-slate-300 rounded-xl" placeholder="Например: Иванов Иван" />
                    </div>
                    <DateInput v-model="dateFrom" label="Дата начала периода" input-class="w-full border-slate-300 rounded-xl" />
                    <DateInput v-model="dateTo" label="Дата окончания периода" input-class="w-full border-slate-300 rounded-xl" />
                    <div>
                        <label class="text-xs text-slate-500 uppercase font-medium">Разряд (текущий)</label>
                        <select v-model="rankId" class="w-full mt-1 border-slate-300 rounded-xl">
                            <option value="">Все</option>
                            <option v-for="r in ranks" :key="r.id" :value="r.id">{{ r.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-slate-500 uppercase font-medium">Группа</label>
                        <select v-model="groupId" class="w-full mt-1 border-slate-300 rounded-xl">
                            <option value="">Все</option>
                            <option v-for="g in groups" :key="g.id" :value="g.id">{{ g.name }}</option>
                        </select>
                    </div>
                    <div class="sm:col-span-2 lg:col-span-3">
                        <label class="text-xs text-slate-500 uppercase font-medium">Мероприятие</label>
                        <select v-model="eventId" class="w-full mt-1 border-slate-300 rounded-xl">
                            <option value="">Все</option>
                            <option v-for="e in events" :key="e.id" :value="e.id">
                                {{ e.name }}<span v-if="e.event_date"> ({{ e.event_date }})</span>
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-slate-500 uppercase font-medium">Возраст от</label>
                        <input v-model="ageFrom" type="number" min="0" max="100" class="w-full mt-1 border-slate-300 rounded-xl" />
                    </div>
                    <div>
                        <label class="text-xs text-slate-500 uppercase font-medium">Возраст до</label>
                        <input v-model="ageTo" type="number" min="0" max="100" class="w-full mt-1 border-slate-300 rounded-xl" />
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button
                        type="button"
                        class="px-4 py-2 rounded-xl bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700"
                        @click="applyFilters"
                    >
                        Применить фильтры
                    </button>
                    <button
                        type="button"
                        class="px-4 py-2 rounded-xl bg-slate-100 text-slate-700 text-sm font-medium hover:bg-slate-200"
                        @click="resetFilters"
                    >
                        Сбросить
                    </button>
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 sm:p-6">
                <h3 class="font-bold text-slate-800 mb-3">Спортсмены по выбранным фильтрам</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-left text-slate-500 border-b border-slate-200">
                            <tr>
                                <th class="py-2 pr-4">ФИО</th>
                                <th class="py-2 pr-4">Возраст</th>
                                <th class="py-2 pr-4">Дата рождения</th>
                                <th class="py-2 pr-4">Текущий разряд</th>
                                <th class="py-2 pr-4">Группы</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="athlete in athletes" :key="athlete.id" class="border-b border-slate-100">
                                <td class="py-2 pr-4 font-medium text-slate-900">{{ athlete.full_name }}</td>
                                <td class="py-2 pr-4">{{ athlete.age ?? '—' }}</td>
                                <td class="py-2 pr-4">{{ athlete.birth_date || '—' }}</td>
                                <td class="py-2 pr-4">{{ athlete.current_rank || '—' }}</td>
                                <td class="py-2 pr-4">{{ athlete.groups?.length ? athlete.groups.join(', ') : '—' }}</td>
                            </tr>
                            <tr v-if="!athletes?.length">
                                <td colspan="5" class="py-4 text-center text-slate-400">По выбранным фильтрам спортсмены не найдены</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 sm:p-6">
                    <h3 class="font-bold text-slate-800 mb-2">Финансовый отчёт</h3>
                    <p class="text-sm text-slate-500 mb-4">
                        Общая прибыль за период, фильтры по тренерам и спортсменам. Экспорт в CSV и PDF.
                    </p>
                    <Link
                        :href="route('admin.reports.profit')"
                        class="inline-flex px-4 py-2 rounded-xl bg-emerald-600 text-white text-sm font-medium hover:bg-emerald-700"
                    >
                        Открыть финансовый отчёт
                    </Link>
                </div>

                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 sm:p-6">
                    <h3 class="font-bold text-slate-800 mb-2">Спортсмены</h3>
                    <p class="text-sm text-slate-500 mb-4">
                        Участие в мероприятиях за период, разряды и группы. Фильтры разряда и группы применяются к списку спортсменов.
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <button
                            type="button"
                            class="px-4 py-2 rounded-xl bg-emerald-600 text-white text-sm font-medium hover:bg-emerald-700"
                            @click="download('admin.reports.athletes', 'csv')"
                        >
                            CSV
                        </button>
                        <button
                            type="button"
                            class="px-4 py-2 rounded-xl bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700"
                            @click="download('admin.reports.athletes', 'pdf')"
                        >
                            PDF
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
