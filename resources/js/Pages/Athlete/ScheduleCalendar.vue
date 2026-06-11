<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import GuardianNoChildren from '@/Components/GuardianNoChildren.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import dayjs from 'dayjs';
import 'dayjs/locale/ru';

dayjs.locale('ru');

const props = defineProps({
    schedules: {
        type: Array,
        default: () => [],
    },
    isGuardian: { type: Boolean, default: false },
    children: { type: Array, default: () => [] },
    selectedAthlete: { type: Object, default: null },
    filters: { type: Object, default: () => ({}) },
    noChildren: { type: Boolean, default: false },
});

const pageTitle = computed(() => (props.isGuardian ? 'Расписание ребёнка' : 'Моё расписание'));

const selectChild = (id) => {
    router.get(route('guardian.schedule'), { athlete_id: id }, { preserveState: true, replace: true });
};

const currentMonth = ref(dayjs());
const selectedDate = ref(dayjs().format('YYYY-MM-DD'));

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

const selectDay = (date) => {
    if (!date) return;
    selectedDate.value = date;
};

const daySchedules = computed(() =>
    props.schedules
        .filter((s) => s.lesson_date === selectedDate.value)
        .slice()
        .sort((a, b) => (a.start_time || '').localeCompare(b.start_time || ''))
);

const attendanceLabel = (status) => {
    if (status === 'Я') return { text: 'Явка', class: 'bg-green-100 text-green-800' };
    if (status === 'Н') return { text: 'Неявка', class: 'bg-red-100 text-red-800' };
    if (status === 'У') return { text: 'Уваж. пропуск', class: 'bg-amber-100 text-amber-800' };
    return { text: 'Не отмечено', class: 'bg-slate-100 text-slate-500' };
};
</script>

<template>
    <Head :title="pageTitle" />

    <AuthenticatedLayout>
        <template #header>{{ pageTitle }}</template>

        <GuardianNoChildren v-if="noChildren" class="my-8" />

        <template v-else>
        <div v-if="isGuardian && children?.length > 1" class="mb-6 bg-white rounded-2xl border border-slate-100 shadow-sm p-4">
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

        <p v-if="isGuardian && selectedAthlete" class="mb-4 text-sm text-slate-600">
            Расписание: <span class="font-semibold text-slate-900">{{ selectedAthlete.full_name }}</span>
        </p>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <div class="lg:col-span-8 bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-bold text-slate-800 capitalize">
                        {{ currentMonth.format('MMMM YYYY') }}
                    </h3>
                    <div class="flex gap-2">
                        <button
                            @click="currentMonth = currentMonth.subtract(1, 'month')"
                            class="p-2 border rounded-lg hover:bg-gray-50"
                        >
                            ‹
                        </button>
                        <button
                            @click="currentMonth = currentMonth.add(1, 'month')"
                            class="p-2 border rounded-lg hover:bg-gray-50"
                        >
                            ›
                        </button>
                    </div>
                </div>

                <div class="calendar-scroll">
                <div class="calendar-grid">
                    <div
                        v-for="d in ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс']"
                        :key="d"
                        class="text-center text-xs font-bold text-slate-400 py-2"
                    >
                        {{ d }}
                    </div>

                    <div
                        v-for="(date, idx) in calendarDays"
                        :key="idx"
                        @click="selectDay(date)"
                        :class="[
                            'min-h-16 sm:min-h-24 border rounded-xl p-1 sm:p-2 transition-all cursor-pointer relative',
                            !date ? 'bg-gray-50 border-transparent cursor-default' : 'hover:border-indigo-400',
                            selectedDate === date ? 'border-indigo-600 bg-indigo-50 ring-2 ring-indigo-100' : 'border-gray-100',
                        ]"
                    >
                        <span
                            v-if="date"
                            class="text-sm font-bold"
                            :class="selectedDate === date ? 'text-indigo-600' : 'text-slate-700'"
                        >
                            {{ dayjs(date).date() }}
                        </span>

                        <div class="mt-1 space-y-1 overflow-hidden">
                            <div
                                v-for="s in schedules.filter((item) => item.lesson_date === date).slice(0, 2)"
                                :key="s.id"
                                class="text-[9px] bg-white border border-indigo-100 text-indigo-700 px-1 rounded truncate"
                            >
                                {{ s.start_time.substring(0, 5) }} - {{ s.end_time.substring(0, 5) }} {{ s.group?.name }}
                            </div>
                            <div
                                v-if="schedules.filter((item) => item.lesson_date === date).length > 2"
                                class="text-[9px] text-gray-400 text-center"
                            >
                                + еще {{ schedules.filter((item) => item.lesson_date === date).length - 2 }}
                            </div>
                        </div>
                    </div>
                </div>
                </div>
            </div>

            <div class="lg:col-span-4">
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 sticky top-6">
                    <h3 class="font-bold text-lg mb-4 text-slate-800">
                        Тренировки на {{ dayjs(selectedDate).format('DD.MM') }}
                    </h3>

                    <div
                        v-for="s in daySchedules"
                        :key="s.id"
                        :class="[
                            'p-3 rounded-xl mb-3 text-sm border',
                            s.is_cancelled ? 'bg-red-50 border-red-200' : 'bg-gray-50 border-gray-100',
                        ]"
                    >
                        <div class="flex justify-between items-start gap-2">
                            <div class="font-bold" :class="s.is_cancelled ? 'text-red-600 line-through' : 'text-indigo-700'">
                                {{ s.start_time.substring(0, 5) }} - {{ s.end_time.substring(0, 5) }}
                            </div>
                            <span
                                v-if="s.is_cancelled"
                                class="text-xs font-semibold text-red-700 bg-red-100 px-2 py-0.5 rounded"
                            >Отменена</span>
                            <span
                                v-else
                                class="text-xs font-semibold px-2 py-0.5 rounded shrink-0"
                                :class="attendanceLabel(s.attendance_status).class"
                            >
                                {{ attendanceLabel(s.attendance_status).text }}
                            </span>
                        </div>
                        <div class="text-slate-600 mt-1 font-medium">{{ s.group?.name }}</div>
                        <div v-if="s.location?.name || s.location_name" class="text-slate-700 mt-1 text-xs">
                            Зал: {{ s.location?.name || s.location_name }}
                        </div>
                        <div
                            v-if="s.location_address || s.location?.address"
                            class="text-slate-500 mt-0.5 text-xs break-words"
                        >
                            {{ s.location_address || s.location?.address }}
                        </div>
                        <div class="text-slate-500 mt-1 text-xs">
                            Тренер: {{ s.coach?.name || '—' }}
                        </div>
                    </div>

                    <div
                        v-if="daySchedules.length === 0"
                        class="text-center py-4 text-gray-400 italic text-sm"
                    >
                        На этот день тренировок нет
                    </div>
                </div>
            </div>
        </div>
        </template>
    </AuthenticatedLayout>
</template>
