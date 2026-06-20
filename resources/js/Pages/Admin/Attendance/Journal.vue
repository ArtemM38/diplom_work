<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { formatDisplayDate } from '@/utils/formatDate';
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
    if (!status) return '';
    if (status === 'Я') return 'bg-green-100 text-green-700';
    if (status === 'У') return 'bg-yellow-100 text-yellow-700';
    return 'bg-red-100 text-red-700';
};

const statusLabel = (status) => {
    if (status === 'Я') return 'Я';
    if (status === 'Н') return 'Н';
    if (status === 'У') return 'У';
    return '';
};

const getDayCellClass = (date) => {
    if (!date) return '';
    const today = dayjs().format('YYYY-MM-DD');
    if (date === today) return 'border-indigo-400 bg-indigo-50/40';
    if (date < today) return 'border-slate-200 bg-slate-50/80';
    return 'border-sky-100 bg-sky-50/30';
};

const getAthleteEntryClass = (entry) => {
    if (!entry.status) {
        if (entry.is_future) return 'bg-sky-50 text-sky-700 border border-sky-100';
        return 'bg-orange-50 text-orange-700 border border-orange-100';
    }
    return getBadgeClass(entry.status);
};

const getAthleteEntryLabel = (entry) => {
    const mark = statusLabel(entry.status);
    const time = entry.start_time?.substring(0, 5);
    const group = entry.group || '';
    if (!mark) {
        return entry.is_future
            ? `${time} ${group} — запланировано`
            : `${time} ${group} — не отмечено`;
    }
    return `${time} ${group} — ${mark}`;
};

const getGroupSessionClass = (entry) => {
    if (entry.is_future) return 'bg-sky-50 text-sky-800 hover:bg-sky-100 border border-sky-100';
    if (entry.is_past && !entry.has_marks) return 'bg-orange-50 text-orange-800 hover:bg-orange-100 border border-orange-100';
    if (entry.has_marks) return 'bg-emerald-50 text-emerald-800 hover:bg-emerald-100 border border-emerald-100';
    return 'bg-slate-100 text-slate-700 hover:bg-slate-200 border border-slate-200';
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

const calendarDaysWithEntries = computed(() =>
    calendarDays.value.filter((date) => date && (calendarByDate.value.get(date)?.length ?? 0) > 0),
);

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
            <div class="flex flex-wrap items-center gap-2 min-w-0">
                <Link :href="route('admin.schedule')" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium shrink-0">← Назад</Link>
                <span class="break-anywhere">Табель посещаемости</span>
            </div>
        </template>

        <div class="min-w-0 max-w-full">
        <div class="flex flex-col sm:flex-row gap-2 mb-4 sm:mb-6">
            <button
                type="button"
                @click="viewMode = 'athletes'"
                :class="viewMode === 'athletes' ? 'bg-indigo-600 text-white' : 'bg-white text-slate-600 border'"
                class="flex-1 sm:flex-none px-4 py-2.5 rounded-xl text-sm font-medium"
            >
                По спортсменам
            </button>
            <button
                type="button"
                @click="viewMode = 'groups'"
                :class="viewMode === 'groups' ? 'bg-indigo-600 text-white' : 'bg-white text-slate-600 border'"
                class="flex-1 sm:flex-none px-4 py-2.5 rounded-xl text-sm font-medium"
            >
                По группам
            </button>
        </div>

        <div v-if="viewMode === 'athletes'" class="grid grid-cols-1 lg:grid-cols-12 gap-4 sm:gap-6 min-w-0">
            <div class="lg:col-span-4 bg-white p-4 sm:p-6 rounded-xl shadow-sm border border-slate-100 h-fit min-w-0">
                <h3 class="font-bold mb-3">Спортсмены</h3>
                <input v-model="search" class="w-full max-w-full min-w-0 border-gray-300 rounded-lg mb-3" placeholder="Поиск..." />
                <div class="space-y-2 max-h-[320px] sm:max-h-[480px] overflow-y-auto">
                    <button
                        v-for="athlete in athletesList"
                        :key="athlete.id"
                        @click="toggleAthlete(athlete.id)"
                        class="w-full text-left p-3 rounded-lg border transition break-anywhere"
                        :class="athleteId === athlete.id ? 'border-indigo-400 bg-indigo-50' : 'border-gray-200'"
                    >
                        {{ athlete.full_name }}
                    </button>
                </div>
                <Pagination class="mt-3" :links="athletes.links" :meta="athletes" />
            </div>

            <div class="lg:col-span-8 bg-white p-4 sm:p-6 rounded-xl shadow-sm min-w-0 overflow-hidden">
                <div v-if="!selectedAthlete" class="text-gray-500 text-center py-12 text-sm px-2">
                    Выберите спортсмена слева, чтобы увидеть календарь явок/неявок.
                </div>
                <template v-else>
                    <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-start sm:justify-between mb-4 min-w-0">
                        <div class="min-w-0 flex-1">
                            <h3 class="font-bold text-base sm:text-lg break-anywhere">{{ selectedAthlete.full_name }}</h3>
                            <div class="mt-2 space-y-2">
                                <p class="text-xs sm:text-sm text-gray-600 break-anywhere">
                                    Явки: <b>{{ stats.present }}</b> ·
                                    Неявки: <b>{{ stats.absent }}</b> ·
                                    Уваж.: <b>{{ stats.excused }}</b>
                                    <span class="text-slate-400"> ({{ statsPeriodLabel }})</span>
                                </p>
                                <div class="flex flex-wrap gap-2">
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
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 shrink-0 self-end sm:self-auto">
                            <button type="button" @click="shiftCalendarMonth(-1)" class="p-2 border rounded-lg min-w-[2.5rem]">‹</button>
                            <button type="button" @click="shiftCalendarMonth(1)" class="p-2 border rounded-lg min-w-[2.5rem]">›</button>
                        </div>
                    </div>

                    <h4 class="font-semibold text-slate-700 mb-3 capitalize text-sm sm:text-base">{{ monthLabel }}</h4>

                    <!-- Мобильный список -->
                    <div class="md:hidden space-y-2 max-h-[55vh] overflow-y-auto">
                        <div
                            v-for="date in calendarDaysWithEntries"
                            :key="`m-${date}`"
                            class="rounded-xl border border-slate-200 p-3"
                            :class="getDayCellClass(date)"
                        >
                            <p class="text-sm font-bold text-slate-800 mb-2 capitalize">{{ dayjs(date).format('D MMMM, dddd') }}</p>
                            <div class="space-y-2">
                                <div
                                    v-for="(entry, eIdx) in (calendarByDate.get(date) || [])"
                                    :key="eIdx"
                                    class="text-xs px-3 py-2 rounded-lg break-anywhere leading-snug"
                                    :class="getAthleteEntryClass(entry)"
                                >
                                    <div>{{ getAthleteEntryLabel(entry) }}</div>
                                    <a
                                        v-if="entry.status === 'У' && entry.excused_certificate_url"
                                        :href="entry.excused_certificate_url"
                                        target="_blank"
                                        class="inline-block mt-1 text-indigo-600 hover:underline font-medium"
                                    >Справка</a>
                                </div>
                            </div>
                        </div>
                        <p v-if="!calendarDaysWithEntries.length" class="text-center text-slate-400 text-sm py-6">Нет занятий в этом месяце</p>
                    </div>

                    <!-- Десктоп: календарь -->
                    <div class="hidden md:block calendar-scroll">
                    <div class="calendar-grid">
                        <div v-for="d in ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс']" :key="d" class="text-center text-xs font-bold text-slate-400 py-1">{{ d }}</div>
                        <div
                            v-for="(date, idx) in calendarDays"
                            :key="idx"
                            :class="['min-h-24 border rounded-xl p-2 min-w-0 overflow-hidden', date ? getDayCellClass(date) : 'border-transparent bg-gray-50']"
                        >
                            <template v-if="date">
                                <div class="text-xs font-bold text-slate-700 mb-1">{{ dayjs(date).date() }}</div>
                                <div class="space-y-1">
                                    <div
                                        v-for="(entry, eIdx) in (calendarByDate.get(date) || [])"
                                        :key="eIdx"
                                        class="text-[10px] px-1.5 py-1 rounded break-anywhere leading-tight"
                                        :class="getAthleteEntryClass(entry)"
                                    >
                                        <div>{{ getAthleteEntryLabel(entry) }}</div>
                                        <a
                                            v-if="entry.status === 'У' && entry.excused_certificate_url"
                                            :href="entry.excused_certificate_url"
                                            target="_blank"
                                            class="inline-block mt-0.5 text-indigo-600 hover:underline font-medium"
                                            @click.stop
                                        >Справка</a>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                    </div>
                    <p class="text-xs text-slate-500 mt-3 break-anywhere">Отображаются все отметки, в том числе по прошлым группам</p>
                </template>
            </div>
        </div>

        <div v-else class="grid grid-cols-1 lg:grid-cols-12 gap-4 sm:gap-6 min-w-0">
            <div class="lg:col-span-3 bg-white p-4 sm:p-5 rounded-xl border border-slate-100 min-w-0">
                <h3 class="font-bold mb-3">Группы</h3>
                <div class="space-y-2 max-h-[240px] lg:max-h-none overflow-y-auto">
                    <button
                        v-for="g in groups"
                        :key="g.id"
                        @click="groupId = g.id"
                        class="w-full text-left p-3 rounded-lg border text-sm break-anywhere"
                        :class="groupId === g.id ? 'border-indigo-400 bg-indigo-50' : 'border-gray-200'"
                    >
                        {{ g.name }}
                    </button>
                </div>
            </div>

            <div class="lg:col-span-9 bg-white p-4 sm:p-5 rounded-xl border border-slate-100 min-w-0 overflow-hidden">
                <div v-if="!groupId" class="text-gray-500 text-center py-12 text-sm">Выберите группу</div>
                <template v-else>
                    <div class="flex flex-wrap justify-between items-center gap-2 mb-4">
                        <h3 class="font-bold capitalize text-sm sm:text-base">{{ monthLabel }}</h3>
                        <div class="flex gap-2 shrink-0">
                            <button type="button" @click="shiftCalendarMonth(-1)" class="p-2 border rounded-lg min-w-[2.5rem]">‹</button>
                            <button type="button" @click="shiftCalendarMonth(1)" class="p-2 border rounded-lg min-w-[2.5rem]">›</button>
                        </div>
                    </div>

                    <div class="md:hidden space-y-2 max-h-[55vh] overflow-y-auto">
                        <div
                            v-for="date in calendarDaysWithEntries"
                            :key="`gm-${date}`"
                            class="rounded-xl border border-slate-200 p-3"
                            :class="getDayCellClass(date)"
                        >
                            <p class="text-sm font-bold text-slate-800 mb-2 capitalize">{{ dayjs(date).format('D MMMM, dddd') }}</p>
                            <div class="space-y-2">
                                <button
                                    v-for="entry in (calendarByDate.get(date) || [])"
                                    :key="entry.schedule_id"
                                    type="button"
                                    @click="openSchedule(entry.schedule_id)"
                                    class="w-full text-left text-xs px-3 py-2 rounded-lg break-anywhere"
                                    :class="getGroupSessionClass(entry)"
                                >
                                    {{ entry.start_time?.substring(0,5) }} тренировка
                                    <span v-if="entry.is_future" class="opacity-70"> · будущая</span>
                                    <span v-else-if="entry.has_marks" class="opacity-70"> · отмечено</span>
                                    <span v-else class="opacity-70"> · без отметок</span>
                                </button>
                            </div>
                        </div>
                        <p v-if="!calendarDaysWithEntries.length" class="text-center text-slate-400 text-sm py-6">Нет занятий в этом месяце</p>
                    </div>

                    <div class="hidden md:block calendar-scroll">
                    <div class="calendar-grid">
                        <div v-for="d in ['Пн','Вт','Ср','Чт','Пт','Сб','Вс']" :key="d" class="text-center text-xs font-bold text-slate-400">{{ d }}</div>
                        <div
                            v-for="(date, idx) in calendarDays"
                            :key="idx"
                            :class="['min-h-24 border rounded-lg p-1.5 min-w-0 overflow-hidden', date ? getDayCellClass(date) : 'border-transparent bg-slate-50']"
                        >
                            <template v-if="date">
                                <div class="text-xs font-bold mb-1">{{ dayjs(date).date() }}</div>
                                <button
                                    v-for="entry in (calendarByDate.get(date) || [])"
                                    :key="entry.schedule_id"
                                    type="button"
                                    @click="openSchedule(entry.schedule_id)"
                                    class="w-full text-left text-[10px] px-1 py-1 mb-0.5 rounded break-anywhere leading-tight"
                                    :class="getGroupSessionClass(entry)"
                                >
                                    {{ entry.start_time?.substring(0,5) }} тренировка
                                    <span v-if="entry.is_future" class="opacity-70"> · будущая</span>
                                    <span v-else-if="entry.has_marks" class="opacity-70"> · отмечено</span>
                                    <span v-else class="opacity-70"> · без отметок</span>
                                </button>
                            </template>
                        </div>
                    </div>
                    </div>
                </template>
            </div>
        </div>
        </div>

        <div v-if="showModal && scheduleModal" class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4 bg-black/50" @click.self="closeModal">
            <div class="bg-white rounded-t-2xl sm:rounded-2xl shadow-xl max-w-lg w-full max-h-[85vh] overflow-hidden flex flex-col">
                <div class="p-4 sm:p-5 border-b bg-slate-50 min-w-0">
                    <h3 class="font-bold text-base sm:text-lg break-anywhere">{{ scheduleModal.group_name }}</h3>
                    <p class="text-sm text-slate-600">
                        {{ formatDisplayDate(scheduleModal.lesson_date) }} · {{ scheduleModal.start_time?.substring(0,5) }}-{{ scheduleModal.end_time?.substring(0,5) }}
                    </p>
                </div>
                <div class="p-5 overflow-y-auto flex-1 space-y-2">
                    <div
                        v-for="a in scheduleModal.athletes"
                        :key="a.id"
                        class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 p-3 rounded-xl border border-slate-100 hover:bg-slate-50 min-w-0"
                    >
                        <div class="min-w-0 flex-1">
                            <p class="font-medium break-anywhere">{{ a.full_name }}</p>
                            <span
                                v-if="a.status"
                                class="text-xs px-2 py-0.5 rounded font-semibold"
                                :class="getBadgeClass(a.status)"
                            >{{ a.status }}</span>
                            <span v-else class="text-xs text-slate-400">Отметки ещё нет</span>
                            <a
                                v-if="a.status === 'У' && a.excused_certificate_url"
                                :href="a.excused_certificate_url"
                                target="_blank"
                                class="block text-xs text-indigo-600 hover:underline mt-1"
                            >Посмотреть справку</a>
                        </div>
                        <Link :href="route('admin.athletes.show', a.id)" class="text-sm text-indigo-600 hover:underline shrink-0">Карточка →</Link>
                    </div>
                </div>
                <div class="p-4 border-t flex flex-col sm:flex-row justify-end gap-2">
                    <Link
                        v-if="scheduleModal.id"
                        :href="route('admin.attendance.show', scheduleModal.id)"
                        class="w-full sm:w-auto text-center px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm"
                    >
                        Редактировать отметки
                    </Link>
                    <button type="button" @click="closeModal" class="w-full sm:w-auto px-4 py-2 border rounded-lg text-sm">Закрыть</button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
