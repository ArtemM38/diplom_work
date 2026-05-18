<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import dayjs from 'dayjs';
import 'dayjs/locale/ru';

dayjs.locale('ru');

const props = defineProps({
    children: Array,
    selectedAthlete: Object,
    calendar: Array,
    stats: Object,
    filters: Object,
});

const athleteId = ref(props.filters?.athlete_id || '');
const calendarMonth = ref(props.filters?.calendar_month || dayjs().format('YYYY-MM'));
const statsPeriod = ref(props.filters?.stats_period || 'month');

const calendarByDate = computed(() => {
    const map = new Map();
    (props.calendar || []).forEach((item) => map.set(item.date, item.entries));
    return map;
});

const calendarDays = computed(() => {
    const [y, m] = calendarMonth.value.split('-').map(Number);
    const start = dayjs(`${y}-${m}-01`);
    const end = start.endOf('month');
    const days = [];
    const offset = start.day() === 0 ? 6 : start.day() - 1;
    for (let i = 0; i < offset; i++) days.push(null);
    for (let i = 1; i <= end.date(); i++) {
        days.push(start.date(i).format('YYYY-MM-DD'));
    }
    return days;
});

const monthLabel = computed(() => {
    const [y, m] = calendarMonth.value.split('-');
    return dayjs(`${y}-${m}-01`).format('MMMM YYYY');
});

const statsPeriodLabel = computed(() => {
    if (statsPeriod.value === 'year') {
        return `за ${dayjs().year()} год`;
    }
    const [y, m] = calendarMonth.value.split('-');
    return `за ${dayjs(`${y}-${m}-01`).format('MMMM')} ${dayjs().year()}`;
});

const getBadgeClass = (status) => {
    if (status === 'Я') return 'bg-green-100 text-green-700';
    if (status === 'У') return 'bg-yellow-100 text-yellow-700';
    return 'bg-red-100 text-red-700';
};

const reload = () => {
    router.get(route('guardian.attendance'), {
        athlete_id: athleteId.value || null,
        calendar_month: calendarMonth.value,
        stats_period: statsPeriod.value,
    }, { preserveState: true, replace: true });
};

const selectChild = (id) => {
    athleteId.value = id;
    reload();
};

const setStatsPeriod = (period) => {
    statsPeriod.value = period;
};

const shiftCalendarMonth = (delta) => {
    const [y, m] = calendarMonth.value.split('-').map(Number);
    const next = dayjs(`${y}-${m}-01`).add(delta, 'month');
    calendarMonth.value = next.format('YYYY-MM');
};

watch([calendarMonth, statsPeriod], reload);
</script>

<template>
    <Head title="Табель ребёнка" />
    <AuthenticatedLayout>
        <template #header>Табель ребёнка</template>

        <div class="max-w-5xl mx-auto space-y-6">
            <div v-if="children?.length > 1" class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4">
                <p class="text-sm text-slate-500 mb-3">Выберите ребёнка</p>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="child in children"
                        :key="child.id"
                        type="button"
                        @click="selectChild(child.id)"
                        class="px-4 py-2 rounded-xl text-sm font-medium border transition"
                        :class="selectedAthlete?.id === child.id
                            ? 'border-indigo-500 bg-indigo-50 text-indigo-800'
                            : 'border-slate-200 text-slate-700 hover:border-indigo-300'"
                    >
                        {{ child.full_name }}
                    </button>
                </div>
            </div>

            <div v-if="selectedAthlete" class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
                <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
                    <div>
                        <h3 class="font-bold text-lg text-slate-900">{{ selectedAthlete.full_name }}</h3>
                        <p class="text-sm text-gray-600 mt-1">
                            Явки: <b>{{ stats.present }}</b> |
                            Неявки: <b>{{ stats.absent }}</b> |
                            Уважительные: <b>{{ stats.excused }}</b>
                            <span class="text-slate-400 ml-1">({{ statsPeriodLabel }})</span>
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            type="button"
                            @click="setStatsPeriod('month')"
                            class="px-3 py-1.5 rounded-lg text-sm font-medium border transition"
                            :class="statsPeriod === 'month' ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-slate-600 border-slate-200'"
                        >
                            Месяц
                        </button>
                        <button
                            type="button"
                            @click="setStatsPeriod('year')"
                            class="px-3 py-1.5 rounded-lg text-sm font-medium border transition"
                            :class="statsPeriod === 'year' ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-slate-600 border-slate-200'"
                        >
                            Год
                        </button>
                        <button type="button" @click="shiftCalendarMonth(-1)" class="p-2 border rounded-lg">‹</button>
                        <button type="button" @click="shiftCalendarMonth(1)" class="p-2 border rounded-lg">›</button>
                    </div>
                </div>

                <h4 class="font-semibold text-slate-700 mb-3 capitalize">{{ monthLabel }}</h4>

                <div class="grid grid-cols-7 gap-2">
                    <div
                        v-for="d in ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс']"
                        :key="d"
                        class="text-center text-xs font-bold text-slate-400 py-1"
                    >
                        {{ d }}
                    </div>
                    <div
                        v-for="(date, idx) in calendarDays"
                        :key="idx"
                        :class="['min-h-24 border rounded-xl p-2', date ? 'border-gray-200' : 'border-transparent bg-gray-50']"
                    >
                        <template v-if="date">
                            <div class="text-xs font-bold text-slate-700 mb-1">{{ dayjs(date).date() }}</div>
                            <div class="space-y-1">
                                <div
                                    v-for="(entry, eIdx) in (calendarByDate.get(date) || [])"
                                    :key="eIdx"
                                    class="text-[10px] px-2 py-1 rounded"
                                    :class="getBadgeClass(entry.status)"
                                >
                                    {{ entry.start_time?.substring(0, 5) }} {{ entry.group }} — {{ entry.status }}
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
                <p class="text-xs text-slate-500 mt-3">Отображаются все отметки, в том числе по прошлым группам</p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
