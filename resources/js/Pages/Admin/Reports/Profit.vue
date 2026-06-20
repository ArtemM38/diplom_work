<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DateInput from '@/Components/DateInput.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    filters: Object,
    report: Object,
    coaches: Array,
    athletes: Array,
    locations: Array,
    events: Array,
});

const defaultProfitDates = () => {
    const now = new Date();
    const pad = (n) => String(n).padStart(2, '0');
    return {
        date_from: `${now.getFullYear()}-${pad(now.getMonth() + 1)}-01`,
        date_to: `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`,
    };
};

const dateFrom = ref(props.filters?.date_from || '');
const dateTo = ref(props.filters?.date_to || '');
const coachId = ref(props.filters?.coach_id || '');
const athleteId = ref(props.filters?.athlete_id || '');
const locationId = ref(props.filters?.location_id || '');
const eventId = ref(props.filters?.event_id || '');

const query = (extra = {}) => {
    const params = {
        date_from: dateFrom.value,
        date_to: dateTo.value,
        ...extra,
    };
    if (coachId.value) params.coach_id = coachId.value;
    if (athleteId.value) params.athlete_id = athleteId.value;
    if (locationId.value) params.location_id = locationId.value;
    if (eventId.value) params.event_id = eventId.value;
    return params;
};

const applyFilters = () => {
    router.get(route('admin.reports.profit'), query(), {
        preserveState: true,
        replace: true,
    });
};

const resetFilters = () => {
    const defaults = defaultProfitDates();
    dateFrom.value = defaults.date_from;
    dateTo.value = defaults.date_to;
    coachId.value = '';
    athleteId.value = '';
    locationId.value = '';
    eventId.value = '';
    router.get(route('admin.reports.profit'), defaults, {
        preserveState: true,
        replace: true,
    });
};

const download = (format) => {
    const params = query({ format });
    const qs = new URLSearchParams(params).toString();
    window.location.href = `${route('admin.reports.profit.export')}?${qs}`;
};

const formatMoney = (value) => `${Number(value || 0).toLocaleString('ru-RU')} ₽`;

const rowAmountClass = (row) => {
    if (row.operation_type === 'refund') return 'text-amber-700';
    if (row.operation_type === 'deposit') return 'text-blue-700';
    return 'text-emerald-700';
};

const formatRowAmount = (row) => {
    if (row.operation_type === 'refund') return `−${formatMoney(row.amount)}`;
    if (row.operation_type === 'deposit') return `+${formatMoney(row.amount)}`;
    return formatMoney(row.amount);
};
</script>

<template>
    <Head title="Финансовый отчёт" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center gap-3">
                <span>Финансовый отчёт</span>
                <Link :href="route('admin.reports')" class="text-sm font-normal text-indigo-600 hover:text-indigo-800">
                    ← Спортивные отчёты
                </Link>
            </div>
        </template>

        <div class="max-w-6xl space-y-6">
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 sm:p-6 space-y-4">
                <h3 class="font-bold text-slate-800">Фильтры</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    <DateInput v-model="dateFrom" label="Дата с" input-class="w-full rounded-lg" />
                    <DateInput v-model="dateTo" label="Дата по" input-class="w-full rounded-lg" />
                    <div>
                        <label class="text-xs text-slate-500">Тренер</label>
                        <select v-model="coachId" class="w-full border-gray-300 rounded-lg">
                            <option value="">Все тренеры</option>
                            <option v-for="c in coaches" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-slate-500">Зал</label>
                        <select v-model="locationId" class="w-full border-gray-300 rounded-lg">
                            <option value="">Все залы</option>
                            <option v-for="l in locations" :key="l.id" :value="l.id">{{ l.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-slate-500">Мероприятие</label>
                        <select v-model="eventId" class="w-full border-gray-300 rounded-lg">
                            <option value="">Все мероприятия</option>
                            <option v-for="e in events" :key="e.id" :value="e.id">
                                {{ e.name }}<span v-if="e.event_date"> ({{ e.event_date }})</span>
                            </option>
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="text-xs text-slate-500">Спортсмен</label>
                        <select v-model="athleteId" class="w-full border-gray-300 rounded-lg">
                            <option value="">Все спортсмены</option>
                            <option v-for="a in athletes" :key="a.id" :value="a.id">{{ a.full_name }}</option>
                        </select>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="px-4 py-2 rounded-xl bg-indigo-600 text-white text-sm font-medium" @click="applyFilters">
                        Показать
                    </button>
                    <button type="button" class="px-4 py-2 rounded-xl border border-slate-200 text-sm" @click="resetFilters">
                        Сбросить фильтры
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
                <div class="bg-emerald-50 border border-emerald-100 rounded-2xl p-5 md:col-span-2">
                    <p class="text-sm text-emerald-700">Чистая прибыль за период</p>
                    <p class="text-3xl font-bold text-emerald-900 mt-1">{{ formatMoney(report?.total_profit) }}</p>
                    <p class="text-xs text-emerald-600 mt-2">
                        Списаний: {{ report?.operations_count || 0 }} · возвратов: {{ formatMoney(report?.total_refunds) }}
                    </p>
                </div>
                <div class="bg-blue-50 border border-blue-100 rounded-2xl p-5">
                    <p class="text-sm text-blue-700">Пополнения баланса</p>
                    <p class="text-2xl font-bold text-blue-900 mt-1">{{ formatMoney(report?.total_deposits) }}</p>
                </div>
                <div class="bg-white border border-slate-200 rounded-2xl p-5">
                    <p class="text-xs text-slate-500">Списания (брутто)</p>
                    <p class="text-xl font-bold text-slate-900">{{ formatMoney(report?.gross_profit) }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-white border border-slate-200 rounded-2xl p-5">
                    <p class="text-xs text-slate-500">Тренировки</p>
                    <p class="text-xl font-bold text-slate-900">{{ formatMoney(report?.by_source?.training) }}</p>
                    <p v-if="report?.refunds_by_source?.training" class="text-xs text-amber-600 mt-1">
                        Возвраты: {{ formatMoney(report.refunds_by_source.training) }}
                    </p>
                </div>
                <div class="bg-white border border-slate-200 rounded-2xl p-5">
                    <p class="text-xs text-slate-500">Мероприятия</p>
                    <p class="text-xl font-bold text-slate-900">{{ formatMoney(report?.by_source?.event) }}</p>
                    <p v-if="report?.refunds_by_source?.event" class="text-xs text-amber-600 mt-1">
                        Возвраты: {{ formatMoney(report.refunds_by_source.event) }}
                    </p>
                </div>
                <div class="bg-white border border-slate-200 rounded-2xl p-5 md:col-span-2">
                    <p class="text-xs text-slate-500">Ручные операции (списания)</p>
                    <p class="text-xl font-bold text-slate-900">{{ formatMoney(report?.by_source?.manual) }}</p>
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 sm:p-6">
                <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                    <h3 class="font-bold text-slate-800">Операции</h3>
                    <div class="flex gap-2">
                        <button type="button" class="px-4 py-2 rounded-xl bg-emerald-600 text-white text-sm" @click="download('csv')">CSV</button>
                        <button type="button" class="px-4 py-2 rounded-xl bg-indigo-600 text-white text-sm" @click="download('pdf')">PDF</button>
                    </div>
                </div>
                <div class="app-table-wrap overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-3 py-2 text-left">Дата</th>
                                <th class="px-3 py-2 text-left">Спортсмен</th>
                                <th class="px-3 py-2 text-left">Тип</th>
                                <th class="px-3 py-2 text-left">Сумма</th>
                                <th class="px-3 py-2 text-left">Источник</th>
                                <th class="px-3 py-2 text-left">Группа</th>
                                <th class="px-3 py-2 text-left">Зал</th>
                                <th class="px-3 py-2 text-left">Тренер</th>
                                <th class="px-3 py-2 text-left">Основание</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in report?.rows || []" :key="row.id" class="border-b border-slate-100">
                                <td class="px-3 py-2">{{ row.date }}</td>
                                <td class="px-3 py-2 font-medium">{{ row.athlete_name }}</td>
                                <td class="px-3 py-2">{{ row.operation_label }}</td>
                                <td class="px-3 py-2 font-semibold" :class="rowAmountClass(row)">{{ formatRowAmount(row) }}</td>
                                <td class="px-3 py-2">{{ row.source_label }}</td>
                                <td class="px-3 py-2">{{ row.group || '—' }}</td>
                                <td class="px-3 py-2">{{ row.location || '—' }}</td>
                                <td class="px-3 py-2">{{ row.coach || '—' }}</td>
                                <td class="px-3 py-2 text-slate-600">{{ row.reason || '—' }}</td>
                            </tr>
                            <tr v-if="!report?.rows?.length">
                                <td colspan="9" class="px-3 py-6 text-center text-slate-400">Нет операций за выбранный период</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
