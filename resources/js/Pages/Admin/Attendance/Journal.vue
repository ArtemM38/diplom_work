<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import debounce from 'lodash/debounce';
import dayjs from 'dayjs';
import 'dayjs/locale/ru';

dayjs.locale('ru');

const props = defineProps({
    athletes: Array,
    schedules: Array,
    scheduleAthletes: Array,
    rows: Array,
    calendar: Array,
    selectedAthlete: Object,
    stats: Object,
    filters: Object,
});

const search = ref(props.filters?.search || '');
const athleteId = ref(props.filters?.athlete_id || '');
const scheduleId = ref(props.filters?.schedule_id || '');
const currentMonth = ref(dayjs());

const calendarByDate = computed(() => {
    const map = new Map();
    (props.calendar || []).forEach((item) => {
        map.set(item.date, item.entries);
    });
    return map;
});

const calendarDays = computed(() => {
    const startOfMonth = currentMonth.value.startOf('month');
    const endOfMonth = currentMonth.value.endOf('month');
    const days = [];

    const offset = startOfMonth.day() === 0 ? 6 : startOfMonth.day() - 1;
    for (let i = 0; i < offset; i++) days.push(null);
    for (let i = 1; i <= endOfMonth.date(); i++) {
        days.push(startOfMonth.date(i).format('YYYY-MM-DD'));
    }

    return days;
});

const getBadgeClass = (status) => {
    if (status === 'Я') return 'bg-green-100 text-green-700';
    if (status === 'У') return 'bg-blue-100 text-blue-700';
    if (status === 'УН') return 'bg-yellow-100 text-yellow-700';
    return 'bg-red-100 text-red-700';
};

const toggleAthlete = (id) => {
    athleteId.value = athleteId.value === id ? '' : id;
};

watch(search, debounce((value) => {
    router.get(route('admin.attendance.journal'), { search: value, athlete_id: athleteId.value || null, schedule_id: scheduleId.value || null }, { preserveState: true, replace: true });
}, 300));

watch(athleteId, (value) => {
    router.get(route('admin.attendance.journal'), { search: search.value, athlete_id: value || null, schedule_id: scheduleId.value || null }, { preserveState: true, replace: true });
});

watch(scheduleId, (value) => {
    router.get(route('admin.attendance.journal'), { search: search.value, athlete_id: athleteId.value || null, schedule_id: value || null }, { preserveState: true, replace: true });
});
</script>

<template>
    <Head title="Табель посещаемости" />
    <AuthenticatedLayout>
        <template #header>Табель посещаемости</template>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <div class="lg:col-span-4 bg-white p-6 rounded-xl shadow-sm h-fit">
                <h3 class="font-bold mb-3">Спортсмены</h3>
                <input v-model="search" class="w-full border-gray-300 rounded-lg mb-3" placeholder="Поиск спортсмена..." />

                <div class="space-y-2 max-h-[550px] overflow-y-auto">
                    <button
                        v-for="athlete in athletes"
                        :key="athlete.id"
                        @click="toggleAthlete(athlete.id)"
                        class="w-full text-left p-3 rounded-lg border transition"
                        :class="athleteId === athlete.id ? 'border-indigo-400 bg-indigo-50' : 'border-gray-200 hover:border-indigo-300'"
                    >
                        {{ athlete.full_name }}
                    </button>
                    <p v-if="athletes.length === 0" class="text-sm text-gray-400">По запросу никого не найдено</p>
                </div>
            </div>

            <div class="lg:col-span-8 bg-white p-6 rounded-xl shadow-sm">
                <div class="mb-6 border rounded-xl p-4 bg-gray-50">
                    <h3 class="font-bold mb-3">Тренировка и отметки группы</h3>
                    <select v-model="scheduleId" class="w-full border-gray-300 rounded-lg mb-3">
                        <option value="">Выберите тренировку</option>
                        <option v-for="item in schedules" :key="item.id" :value="item.id">
                            {{ item.lesson_date }} {{ item.start_time?.substring(0, 5) }}-{{ item.end_time?.substring(0, 5) }} | {{ item.group_name || 'Без группы' }}
                        </option>
                    </select>
                    <div v-if="scheduleId">
                        <div class="text-sm text-gray-600 mb-2">Все спортсмены группы и их статусы</div>
                        <div v-if="scheduleAthletes?.length" class="space-y-1 max-h-52 overflow-y-auto">
                            <div v-for="item in scheduleAthletes" :key="item.id" class="flex justify-between text-sm border-b py-1">
                                <span>{{ item.full_name }}</span>
                                <span class="font-semibold" :class="getBadgeClass(item.status)">{{ item.status }}</span>
                            </div>
                        </div>
                        <div v-else class="text-sm text-gray-400">Для этой тренировки нет спортсменов</div>
                    </div>
                </div>

                <div v-if="!selectedAthlete" class="text-gray-500">
                    Выберите спортсмена слева, чтобы увидеть календарь явок/неявок.
                </div>
                <template v-else>
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h3 class="font-bold text-lg">{{ selectedAthlete.full_name }}</h3>
                            <p class="text-sm text-gray-600">
                                Явки: <b>{{ stats.present }}</b> | Неявки: <b>{{ stats.absent }}</b> | Уважительные: <b>{{ stats.excused }}</b>
                            </p>
                        </div>
                        <div class="flex gap-2">
                            <button @click="currentMonth = currentMonth.subtract(1, 'month')" class="p-2 border rounded-lg">‹</button>
                            <button @click="currentMonth = currentMonth.add(1, 'month')" class="p-2 border rounded-lg">›</button>
                        </div>
                    </div>

                    <h4 class="font-semibold text-slate-700 mb-3 capitalize">{{ currentMonth.format('MMMM YYYY') }}</h4>

                    <div class="grid grid-cols-7 gap-2">
                        <div v-for="d in ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс']" :key="d"
                            class="text-center text-xs font-bold text-slate-400 py-1">
                            {{ d }}
                        </div>

                        <div v-for="(date, idx) in calendarDays" :key="idx"
                            :class="['min-h-24 border rounded-xl p-2', date ? 'border-gray-200' : 'border-transparent bg-gray-50']">
                            <template v-if="date">
                                <div class="text-xs font-bold text-slate-700 mb-1">{{ dayjs(date).date() }}</div>
                                <div class="space-y-1">
                                    <div v-for="(entry, eIdx) in (calendarByDate.get(date) || [])" :key="eIdx"
                                        class="text-[10px] px-2 py-1 rounded"
                                        :class="getBadgeClass(entry.status)">
                                        {{ entry.start_time?.substring(0, 5) }} {{ entry.group }} — {{ entry.status }}
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
