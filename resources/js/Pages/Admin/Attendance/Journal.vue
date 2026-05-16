<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import debounce from 'lodash/debounce';
import dayjs from 'dayjs';
import 'dayjs/locale/ru';

dayjs.locale('ru');

const props = defineProps({
    athletes: Object,
    groups: Array,
    calendar: Array,
    groupCalendar: Array,
    scheduleModal: Object,
    selectedAthlete: Object,
    stats: Object,
    rows: Array,
    filters: Object,
});

const viewMode = ref(props.filters?.view || 'athletes');
const search = ref(props.filters?.search || '');
const athleteId = ref(props.filters?.athlete_id || '');
const groupId = ref(props.filters?.group_id || '');
const calendarMonth = ref(props.filters?.calendar_month || dayjs().format('YYYY-MM'));
const statsPeriod = ref(props.filters?.stats_period || 'month');

const athletesList = computed(() => props.athletes?.data ?? []);
const showModal = computed(() => !!props.scheduleModal);

const calendarByDate = computed(() => {
    const map = new Map();
    const source = viewMode.value === 'groups' ? props.groupCalendar : props.calendar;
    (source || []).forEach((item) => map.set(item.date, item.entries));
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

const getBadgeClass = (status) => {
    if (status === 'Я') return 'bg-green-100 text-green-700';
    if (status === 'У') return 'bg-yellow-100 text-yellow-700';
    return 'bg-red-100 text-red-700';
};

const reload = () => {
    router.get(route('admin.attendance.journal'), {
        view: viewMode.value,
        search: search.value || null,
        athlete_id: viewMode.value === 'athletes' ? (athleteId.value || null) : null,
        group_id: viewMode.value === 'groups' ? (groupId.value || null) : null,
        schedule_id: props.filters?.schedule_id || null,
        calendar_month: calendarMonth.value,
        stats_period: statsPeriod.value,
    }, { preserveState: true, replace: true });
};

watch(search, debounce(reload, 300));
watch([viewMode, athleteId, groupId, calendarMonth, statsPeriod], reload);

const setStatsPeriod = (period) => {
    statsPeriod.value = period;
};

const statsPeriodLabel = computed(() => {
    if (statsPeriod.value === 'year') {
        return `за ${dayjs().year()} год`;
    }
    const [y, m] = calendarMonth.value.split('-');
    return `за ${dayjs(`${y}-${m}-01`).format('MMMM')} ${dayjs().year()}`;
});

const toggleAthlete = (id) => {
    athleteId.value = athleteId.value === id ? '' : id;
};

const openSchedule = (scheduleId) => {
    router.get(route('admin.attendance.journal'), {
        view: 'groups',
        group_id: groupId.value,
        schedule_id: scheduleId,
        calendar_month: calendarMonth.value,
        search: search.value || null,
    }, { preserveState: true, replace: true });
};

const closeModal = () => {
    router.get(route('admin.attendance.journal'), {
        view: 'groups',
        group_id: groupId.value,
        calendar_month: calendarMonth.value,
        search: search.value || null,
    }, { preserveState: true, replace: true });
};

const shiftCalendarMonth = (delta) => {
    const [y, m] = calendarMonth.value.split('-').map(Number);
    const next = dayjs(`${y}-${m}-01`).add(delta, 'month');
    calendarMonth.value = next.format('YYYY-MM');
};
</script>

<template>
    <Head title="Табель посещаемости" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('admin.schedule')" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium">← Назад</Link>
                <span>Табель посещаемости</span>
            </div>
        </template>

        <div class="flex gap-2 mb-6">
            <button
                type="button"
                @click="viewMode = 'athletes'"
                :class="viewMode === 'athletes' ? 'bg-indigo-600 text-white' : 'bg-white text-slate-600 border'"
                class="px-4 py-2 rounded-xl text-sm font-medium"
            >
                По спортсменам
            </button>
            <button
                type="button"
                @click="viewMode = 'groups'"
                :class="viewMode === 'groups' ? 'bg-indigo-600 text-white' : 'bg-white text-slate-600 border'"
                class="px-4 py-2 rounded-xl text-sm font-medium"
            >
                По группам
            </button>
        </div>

        <div v-if="viewMode === 'athletes'" class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <div class="lg:col-span-4 bg-white p-6 rounded-xl shadow-sm border border-slate-100 h-fit">
                <h3 class="font-bold mb-3">Спортсмены</h3>
                <input v-model="search" class="w-full border-gray-300 rounded-lg mb-3" placeholder="Поиск..." />
                <div class="space-y-2 max-h-[480px] overflow-y-auto">
                    <button
                        v-for="athlete in athletesList"
                        :key="athlete.id"
                        @click="toggleAthlete(athlete.id)"
                        class="w-full text-left p-3 rounded-lg border transition"
                        :class="athleteId === athlete.id ? 'border-indigo-400 bg-indigo-50' : 'border-gray-200'"
                    >
                        {{ athlete.full_name }}
                    </button>
                </div>
                <Pagination class="mt-3" :links="athletes.links" :meta="athletes" />
            </div>

            <div class="lg:col-span-8 bg-white p-6 rounded-xl shadow-sm">
                <div v-if="!selectedAthlete" class="text-gray-500 text-center py-12">
                    Выберите спортсмена слева, чтобы увидеть календарь явок/неявок.
                </div>
                <template v-else>
                    <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
                        <div>
                            <h3 class="font-bold text-lg">{{ selectedAthlete.full_name }}</h3>
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
                        <div v-for="d in ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс']" :key="d" class="text-center text-xs font-bold text-slate-400 py-1">{{ d }}</div>
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
                </template>
            </div>
        </div>

        <div v-else class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <div class="lg:col-span-3 bg-white p-5 rounded-xl border border-slate-100">
                <h3 class="font-bold mb-3">Группы</h3>
                <div class="space-y-2">
                    <button
                        v-for="g in groups"
                        :key="g.id"
                        @click="groupId = g.id"
                        class="w-full text-left p-3 rounded-lg border text-sm"
                        :class="groupId === g.id ? 'border-indigo-400 bg-indigo-50' : 'border-gray-200'"
                    >
                        {{ g.name }}
                    </button>
                </div>
            </div>

            <div class="lg:col-span-9 bg-white p-5 rounded-xl border border-slate-100">
                <div v-if="!groupId" class="text-gray-500 text-center py-12">Выберите группу</div>
                <template v-else>
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="font-bold capitalize">{{ monthLabel }}</h3>
                        <div class="flex gap-2">
                            <button type="button" @click="shiftCalendarMonth(-1)" class="p-2 border rounded-lg">‹</button>
                            <button type="button" @click="shiftCalendarMonth(1)" class="p-2 border rounded-lg">›</button>
                        </div>
                    </div>
                    <div class="grid grid-cols-7 gap-2">
                        <div v-for="d in ['Пн','Вт','Ср','Чт','Пт','Сб','Вс']" :key="d" class="text-center text-xs font-bold text-slate-400">{{ d }}</div>
                        <div
                            v-for="(date, idx) in calendarDays"
                            :key="idx"
                            :class="['min-h-24 border rounded-lg p-1.5', date ? 'border-slate-200' : 'border-transparent bg-slate-50']"
                        >
                            <template v-if="date">
                                <div class="text-xs font-bold mb-1">{{ dayjs(date).date() }}</div>
                                <button
                                    v-for="entry in (calendarByDate.get(date) || [])"
                                    :key="entry.schedule_id"
                                    type="button"
                                    @click="openSchedule(entry.schedule_id)"
                                    class="w-full text-left text-[10px] px-1.5 py-1 mb-0.5 rounded bg-indigo-50 text-indigo-800 hover:bg-indigo-100"
                                >
                                    {{ entry.start_time?.substring(0,5) }} тренировка
                                </button>
                            </template>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <div v-if="showModal && scheduleModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50" @click.self="closeModal">
            <div class="bg-white rounded-2xl shadow-xl max-w-lg w-full max-h-[80vh] overflow-hidden flex flex-col">
                <div class="p-5 border-b bg-slate-50">
                    <h3 class="font-bold text-lg">{{ scheduleModal.group_name }}</h3>
                    <p class="text-sm text-slate-600">
                        {{ scheduleModal.lesson_date }} · {{ scheduleModal.start_time?.substring(0,5) }}-{{ scheduleModal.end_time?.substring(0,5) }}
                    </p>
                </div>
                <div class="p-5 overflow-y-auto flex-1 space-y-2">
                    <div
                        v-for="a in scheduleModal.athletes"
                        :key="a.id"
                        class="flex items-center justify-between p-3 rounded-xl border border-slate-100 hover:bg-slate-50"
                    >
                        <div>
                            <p class="font-medium">{{ a.full_name }}</p>
                            <span class="text-xs px-2 py-0.5 rounded font-semibold" :class="getBadgeClass(a.status)">{{ a.status }}</span>
                        </div>
                        <Link :href="route('admin.athletes.show', a.id)" class="text-sm text-indigo-600 hover:underline">Карточка →</Link>
                    </div>
                </div>
                <div class="p-4 border-t flex justify-end gap-2">
                    <Link
                        v-if="scheduleModal.id"
                        :href="route('admin.attendance.show', scheduleModal.id)"
                        class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm"
                    >
                        Редактировать отметки
                    </Link>
                    <button type="button" @click="closeModal" class="px-4 py-2 border rounded-lg text-sm">Закрыть</button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
