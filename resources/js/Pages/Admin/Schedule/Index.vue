<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useForm, Head, Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import dayjs from 'dayjs'; // Рекомендую установить: npm install dayjs
import 'dayjs/locale/ru';

dayjs.locale('ru');

const props = defineProps({
    schedules: Array,
    groups: Array,
    locations: Array,
    coaches: Array
});

// Состояние календаря
const currentMonth = ref(dayjs());
const selectedDate = ref(dayjs().format('YYYY-MM-DD'));

// Генерация дней месяца для сетки
const calendarDays = computed(() => {
    const startOfMonth = currentMonth.value.startOf('month');
    const endOfMonth = currentMonth.value.endOf('month');
    const days = [];

    // Заполняем пустыми ячейками до начала месяца (чтобы Пн был под Пн)
    const offset = startOfMonth.day() === 0 ? 6 : startOfMonth.day() - 1;
    for (let i = 0; i < offset; i++) days.push(null);

    // Сами дни месяца
    for (let i = 1; i <= endOfMonth.date(); i++) {
        days.push(startOfMonth.date(i).format('YYYY-MM-DD'));
    }
    return days;
});

const form = useForm({
    lesson_date: selectedDate.value,
    group_id: '',
    location_id: '',
    coach_id: '',
    start_time: '',
    end_time: '',
    lesson_type: 'group',
});
const editForm = useForm({
    lesson_date: '',
    group_id: '',
    location_id: '',
    coach_id: '',
    start_time: '',
    end_time: '',
    lesson_type: 'group',
});
const editingScheduleId = ref(null);

// Клик по дню в календаре
const selectDay = (date) => {
    if (!date) return;
    selectedDate.value = date;
    form.lesson_date = date;
};

const submit = () => {
    form.post(route('admin.schedule.store'), {
        onSuccess: () => form.reset('start_time', 'end_time'),
    });
};

const canMarkAttendance = (schedule) => {
    const startDateTime = dayjs(`${schedule.lesson_date} ${schedule.start_time}`);
    return dayjs().isAfter(startDateTime) || dayjs().isSame(startDateTime);
};

const startEdit = (schedule) => {
    editingScheduleId.value = schedule.id;
    editForm.lesson_date = schedule.lesson_date;
    editForm.group_id = schedule.group_id;
    editForm.location_id = schedule.location_id;
    editForm.coach_id = schedule.coach_id;
    editForm.start_time = schedule.start_time?.substring(0, 5);
    editForm.end_time = schedule.end_time?.substring(0, 5);
};

const cancelEdit = () => {
    editingScheduleId.value = null;
    editForm.reset();
};

const saveEdit = () => {
    if (!editingScheduleId.value) return;
    editForm.patch(route('admin.schedule.update', editingScheduleId.value), {
        onSuccess: () => cancelEdit(),
    });
};
</script>

<template>
    <AuthenticatedLayout>
        <template #header>Планировщик расписания</template>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

            <!-- ЛЕВАЯ КОЛОНКА: Календарь месяца -->
            <div class="lg:col-span-8 bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-bold text-slate-800 capitalize">
                        {{ currentMonth.format('MMMM YYYY') }}
                    </h3>
                    <div class="flex gap-2">
                        <button @click="currentMonth = currentMonth.subtract(1, 'month')"
                            class="p-2 border rounded-lg hover:bg-gray-50">‹</button>
                        <button @click="currentMonth = currentMonth.add(1, 'month')"
                            class="p-2 border rounded-lg hover:bg-gray-50">›</button>
                    </div>
                </div>

                <!-- Сетка календаря -->
                <div class="grid grid-cols-7 gap-2">
                    <div v-for="d in ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс']" :key="d"
                        class="text-center text-xs font-bold text-slate-400 py-2">{{ d }}</div>

                    <div v-for="(date, idx) in calendarDays" :key="idx" @click="selectDay(date)" :class="[
                        'h-24 border rounded-xl p-2 transition-all cursor-pointer relative',
                        !date ? 'bg-gray-50 border-transparent cursor-default' : 'hover:border-indigo-400',
                        selectedDate === date ? 'border-indigo-600 bg-indigo-50 ring-2 ring-indigo-100' : 'border-gray-100'
                    ]">
                        <span v-if="date" class="text-sm font-bold"
                            :class="selectedDate === date ? 'text-indigo-600' : 'text-slate-700'">
                            {{ dayjs(date).date() }}
                        </span>

                        <!-- Индикаторы занятий в этот день -->
                        <div class="mt-1 space-y-1 overflow-hidden">
                            <div v-for="s in schedules.filter(s => s.lesson_date === date).slice(0, 2)" :key="s.id"
                                class="text-[9px] bg-white border border-indigo-100 text-indigo-700 px-1 rounded truncate">
                                {{ s.start_time.substring(0, 5) }} - {{ s.end_time.substring(0, 5) }} {{ s.group.name }}
                            </div>
                            <div v-if="schedules.filter(s => s.lesson_date === date).length > 2"
                                class="text-[9px] text-gray-400 text-center">
                                + еще {{schedules.filter(s => s.lesson_date === date).length - 2}}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ПРАВАЯ КОЛОНКА: Форма добавления -->
            <div class="lg:col-span-4 space-y-6">
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 sticky top-6">
                    <h3 class="font-bold text-lg mb-4 text-slate-800">Добавить на {{ dayjs(selectedDate).format('DD.MM')
                        }}</h3>
                    <div class="flex gap-3 mb-2">
                        <Link :href="route('admin.attendance.journal')" class="text-xs text-indigo-600 hover:underline">Открыть общий табель</Link>
                        <Link :href="route('admin.locations')" class="text-xs text-indigo-600 hover:underline">Управление залами</Link>
                    </div>

                    <form @submit.prevent="submit" class="space-y-4">
                        <div v-if="form.errors.conflict"
                            class="p-3 bg-red-50 text-red-600 text-xs rounded-lg border border-red-100">
                            {{ form.errors.conflict }}
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="text-xs text-gray-500">Начало</label>
                                <input v-model="form.start_time" type="time" class="w-full border-gray-300 rounded-lg">
                            </div>
                            <div>
                                <label class="text-xs text-gray-500">Конец</label>
                                <input v-model="form.end_time" type="time" class="w-full border-gray-300 rounded-lg">
                            </div>
                        </div>

                        <div>
                            <label class="text-xs text-gray-500">Группа</label>
                            <select v-model="form.group_id" class="w-full border-gray-300 rounded-lg">
                                <option v-for="g in groups" :key="g.id" :value="g.id">{{ g.name }}</option>
                            </select>
                        </div>

                        <div>
                            <label class="text-xs text-gray-500">Зал</label>
                            <select v-model="form.location_id" class="w-full border-gray-300 rounded-lg">
                                <option v-for="l in locations" :key="l.id" :value="l.id">{{ l.name }}</option>
                            </select>
                        </div>

                        <div>
                            <label class="text-xs text-gray-500">Тренер</label>
                            <select v-model="form.coach_id" class="w-full border-gray-300 rounded-lg">
                                <option v-for="c in coaches" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                        </div>

                        <button
                            class="w-full bg-indigo-600 text-white py-3 rounded-xl font-bold hover:bg-indigo-700 transition shadow-lg shadow-indigo-100">
                            Запланировать
                        </button>
                    </form>

                    <!-- Список занятий в выбранный день -->
                    <div class="mt-8 border-t pt-6">
                        <h4 class="text-sm font-bold text-slate-700 mb-4">Занятия на {{
                            dayjs(selectedDate).format('DD.MM') }}:
                        </h4>

                        <div v-for="s in schedules.filter(s => s.lesson_date === selectedDate)" :key="s.id"
                            class="flex items-center justify-between p-3 bg-gray-50 rounded-xl mb-3 text-xs border border-transparent hover:border-indigo-200 transition shadow-sm">

                            <div class="flex flex-col flex-1" v-if="editingScheduleId !== s.id">
                                <span class="font-bold text-indigo-700">{{ s.start_time.substring(0, 5) }} - {{
                                    s.end_time.substring(0, 5) }}</span>
                                <span class="text-slate-600">
                                    <span class="font-semibold text-slate-900">{{ s.location?.name }}</span>
                                    | {{ s.group.name }}
                                </span>
                            </div>
                            <div v-else class="grid grid-cols-2 gap-2 flex-1">
                                <input v-model="editForm.start_time" type="time" class="border-gray-300 rounded-lg" />
                                <input v-model="editForm.end_time" type="time" class="border-gray-300 rounded-lg" />
                                <select v-model="editForm.group_id" class="border-gray-300 rounded-lg">
                                    <option v-for="g in groups" :key="g.id" :value="g.id">{{ g.name }}</option>
                                </select>
                                <select v-model="editForm.location_id" class="border-gray-300 rounded-lg">
                                    <option v-for="l in locations" :key="l.id" :value="l.id">{{ l.name }}</option>
                                </select>
                                <select v-model="editForm.coach_id" class="border-gray-300 rounded-lg col-span-2">
                                    <option v-for="c in coaches" :key="c.id" :value="c.id">{{ c.name }}</option>
                                </select>
                            </div>

                            <div class="flex items-center gap-2 ml-2">
                                <!-- КНОПКА ОТМЕТИТЬ (Переход в журнал) -->
                                <Link v-if="canMarkAttendance(s)" :href="route('admin.attendance.show', s.id)"
                                    class="bg-indigo-100 text-indigo-700 px-3 py-1.5 rounded-lg hover:bg-indigo-600 hover:text-white transition font-bold flex items-center gap-1">
                                <span>Журнал</span>
                                </Link>
                                <span v-else class="text-[10px] px-2 py-1 rounded bg-gray-100 text-gray-400 font-semibold">
                                    Доступно после тренировки
                                </span>

                                <button v-if="editingScheduleId !== s.id" @click="startEdit(s)" class="text-indigo-500 hover:text-indigo-700 transition p-1">✎</button>
                                <button v-else @click="saveEdit" class="px-2 py-1 rounded bg-emerald-100 text-emerald-700">Сохранить</button>
                                <button v-if="editingScheduleId === s.id" @click="cancelEdit" class="px-2 py-1 rounded bg-gray-100 text-gray-700">Отмена</button>

                                <button @click="form.delete(route('admin.schedule.destroy', s.id))"
                                    class="text-red-300 hover:text-red-600 transition p-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div v-if="schedules.filter(s => s.lesson_date === selectedDate).length === 0"
                            class="text-center py-4 text-gray-400 italic text-xs">
                            На этот день ничего не запланировано
                        </div>
                    </div>

                </div>
            </div>

        </div>
    </AuthenticatedLayout>
</template>