<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
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
    props.schedules.filter((s) => s.lesson_date === selectedDate.value)
);
</script>

<template>
    <Head :title="pageTitle" />

    <AuthenticatedLayout>
        <template #header>{{ pageTitle }}</template>

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

                <div class="grid grid-cols-7 gap-2">
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
                            'h-24 border rounded-xl p-2 transition-all cursor-pointer relative',
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

            <div class="lg:col-span-4">
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 sticky top-6">
                    <h3 class="font-bold text-lg mb-4 text-slate-800">
                        Тренировки на {{ dayjs(selectedDate).format('DD.MM') }}
                    </h3>

                    <div
                        v-for="s in daySchedules"
                        :key="s.id"
                        class="p-3 bg-gray-50 rounded-xl mb-3 text-sm border border-gray-100"
                    >
                        <div class="font-bold text-indigo-700">
                            {{ s.start_time.substring(0, 5) }} - {{ s.end_time.substring(0, 5) }}
                        </div>
                        <div class="text-slate-600 mt-1">
                            <span class="font-semibold text-slate-900">{{ s.group?.name }}</span>
                            <span v-if="s.location?.name"> | {{ s.location.name }}</span>
                        </div>
                        <div class="text-slate-500 mt-1">
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
    </AuthenticatedLayout>
</template>
