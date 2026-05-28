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
const showEditModal = ref(false);
const editingSchedule = ref(null);
const cancellingSchedule = ref(null);
const cancelForm = useForm({ cancellation_reason: '' });
const showCancelModal = ref(false);

const attendanceStatusLabel = (status) => {
    if (status === 'Я') return { text: 'Явка', class: 'bg-green-100 text-green-800' };
    if (status === 'Н') return { text: 'Неявка', class: 'bg-red-100 text-red-800' };
    if (status === 'У') return { text: 'Уваж. пропуск', class: 'bg-amber-100 text-amber-800' };
    return null;
};

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

const canMarkAttendance = (schedule) => schedule.can_mark_attendance === true;

const canCancelSchedule = (schedule) => schedule.can_cancel === true && !schedule.is_cancelled;

const startEdit = (schedule) => {
    if (schedule.is_cancelled) return;
    editingSchedule.value = schedule;
    editingScheduleId.value = schedule.id;
    editForm.lesson_date = schedule.lesson_date;
    editForm.group_id = schedule.group_id;
    editForm.location_id = schedule.location_id;
    editForm.coach_id = schedule.coach_id;
    editForm.start_time = schedule.start_time?.substring(0, 5);
    editForm.end_time = schedule.end_time?.substring(0, 5);
    showEditModal.value = true;
};

const cancelEdit = () => {
    editingScheduleId.value = null;
    editingSchedule.value = null;
    showEditModal.value = false;
    editForm.reset();
};

const saveEdit = () => {
    if (!editingScheduleId.value) return;
    editForm.patch(route('admin.schedule.update', editingScheduleId.value), {
        onSuccess: () => cancelEdit(),
    });
};

const openCancelModal = (schedule) => {
    if (!canCancelSchedule(schedule)) return;
    cancellingSchedule.value = schedule;
    cancelForm.cancellation_reason = '';
    cancelForm.clearErrors();
    showCancelModal.value = true;
};

const closeCancelModal = () => {
    showCancelModal.value = false;
    cancellingSchedule.value = null;
    cancelForm.reset();
};

const submitCancel = () => {
    if (!cancellingSchedule.value) return;
    cancelForm.post(route('admin.schedule.cancel', cancellingSchedule.value.id), {
        onSuccess: () => closeCancelModal(),
    });
};

const coachLine = (schedule) => {
    const initial = schedule.initial_coach_name || schedule.initial_coach?.name;
    const current = schedule.coach?.name;
    if (initial && current && initial !== current) {
        return `${current} (изначально: ${initial})`;
    }
    return current || initial || '—';
};

const schedulesForDay = computed(() =>
    props.schedules
        .filter((s) => s.lesson_date === selectedDate.value)
        .slice()
        .sort((a, b) => (a.start_time || '').localeCompare(b.start_time || ''))
);
</script>

<template>
    <AuthenticatedLayout>
        <template #header>Планировщик расписания</template>

        <div class="grid grid-cols-1 md:grid-cols-12 gap-4 md:gap-6">

            <!-- ЛЕВАЯ КОЛОНКА: Календарь месяца -->
            <div class="md:col-span-7 lg:col-span-8 bg-white p-4 sm:p-6 rounded-2xl shadow-sm border border-gray-100 min-w-0">
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
                <div class="calendar-scroll">
                <div class="calendar-grid">
                    <div v-for="d in ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс']" :key="d"
                        class="text-center text-xs font-bold text-slate-400 py-2">{{ d }}</div>

                    <div v-for="(date, idx) in calendarDays" :key="idx" @click="selectDay(date)" :class="[
                        'min-h-16 sm:min-h-24 border rounded-xl p-1 sm:p-2 transition-all cursor-pointer relative',
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
            </div>

            <!-- ПРАВАЯ КОЛОНКА: Форма добавления -->
            <div class="md:col-span-5 lg:col-span-4 space-y-4 md:space-y-6 min-w-0">
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 sticky top-6">
                    <h3 class="font-bold text-lg mb-4 text-slate-800">Добавить на {{ dayjs(selectedDate).format('DD.MM')
                        }}</h3>
                    <div class="flex gap-3 mb-2">
                        <Link :href="route('admin.attendance.journal')" class="text-xs text-indigo-600 hover:underline">Открыть общий табель</Link>
                        <Link :href="route('admin.locations')" class="text-xs text-indigo-600 hover:underline">Управление залами</Link>
                    </div>

                    <form @submit.prevent="submit" class="space-y-4">
                        <p v-if="form.errors.conflict" class="p-3 bg-red-50 text-red-600 text-xs rounded-lg border border-red-100">{{ form.errors.conflict }}</p>
                        <p v-if="form.errors.lesson_date" class="text-red-600 text-xs">{{ form.errors.lesson_date }}</p>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="text-xs text-gray-500">Начало</label>
                                <input v-model="form.start_time" type="time" :class="['w-full rounded-lg', form.errors.start_time ? 'border-red-500' : 'border-gray-300']" />
                                <p v-if="form.errors.start_time" class="text-red-600 text-xs mt-1">{{ form.errors.start_time }}</p>
                            </div>
                            <div>
                                <label class="text-xs text-gray-500">Конец</label>
                                <input v-model="form.end_time" type="time" :class="['w-full rounded-lg', form.errors.end_time ? 'border-red-500' : 'border-gray-300']" />
                                <p v-if="form.errors.end_time" class="text-red-600 text-xs mt-1">{{ form.errors.end_time }}</p>
                            </div>
                        </div>

                        <div>
                            <label class="text-xs text-gray-500">Группа</label>
                            <select v-model="form.group_id" :class="['w-full rounded-lg', form.errors.group_id ? 'border-red-500' : 'border-gray-300']">
                                <option value="">—</option>
                                <option v-for="g in groups" :key="g.id" :value="g.id">{{ g.name }}</option>
                            </select>
                            <p v-if="form.errors.group_id" class="text-red-600 text-xs mt-1">{{ form.errors.group_id }}</p>
                        </div>

                        <div>
                            <label class="text-xs text-gray-500">Зал</label>
                            <select v-model="form.location_id" :class="['w-full rounded-lg', form.errors.location_id ? 'border-red-500' : 'border-gray-300']">
                                <option value="">—</option>
                                <option v-for="l in locations" :key="l.id" :value="l.id">{{ l.name }}</option>
                            </select>
                            <p v-if="form.errors.location_id" class="text-red-600 text-xs mt-1">{{ form.errors.location_id }}</p>
                        </div>

                        <div>
                            <label class="text-xs text-gray-500">Тренер</label>
                            <select v-model="form.coach_id" :class="['w-full rounded-lg', form.errors.coach_id ? 'border-red-500' : 'border-gray-300']">
                                <option value="">—</option>
                                <option v-for="c in coaches" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                            <p v-if="form.errors.coach_id" class="text-red-600 text-xs mt-1">{{ form.errors.coach_id }}</p>
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

                        <div v-for="s in schedulesForDay" :key="s.id"
                            :class="[
                                'flex items-center justify-between p-3 rounded-xl mb-3 text-xs border transition shadow-sm',
                                s.is_cancelled ? 'bg-red-50 border-red-200 opacity-80' : 'bg-gray-50 border-transparent hover:border-indigo-200',
                            ]">

                            <div class="flex flex-col flex-1">
                                <span class="font-bold" :class="s.is_cancelled ? 'text-red-600 line-through' : 'text-indigo-700'">
                                    {{ s.start_time.substring(0, 5) }} - {{ s.end_time.substring(0, 5) }}
                                </span>
                                <span v-if="s.is_cancelled" class="text-red-700 font-semibold text-[10px] mt-0.5">Отменена</span>
                                <span class="text-slate-600">
                                    <span class="font-semibold text-slate-900">{{ s.location?.name }}</span>
                                    | {{ s.group.name }}
                                </span>
                                <span class="text-[10px] text-slate-400 mt-0.5">{{ coachLine(s) }}</span>
                            </div>

                            <div class="flex items-center gap-2 ml-2">
                                <Link v-if="!s.is_cancelled && canMarkAttendance(s)" :href="route('admin.attendance.show', s.id)"
                                    class="bg-indigo-100 text-indigo-700 px-3 py-1.5 rounded-lg hover:bg-indigo-600 hover:text-white transition font-bold">
                                    Журнал
                                </Link>
                                <span v-else-if="!s.is_cancelled" class="text-[10px] px-2 py-1 rounded bg-gray-100 text-gray-400 font-semibold">
                                    Журнал с −10 мин
                                </span>

                                <button v-if="!s.is_cancelled" @click="startEdit(s)" class="px-2 py-1 rounded-lg bg-white border border-indigo-200 text-indigo-700 text-xs font-medium hover:bg-indigo-50">Изменить</button>

                                <button
                                    v-if="!s.is_cancelled"
                                    @click="openCancelModal(s)"
                                    :disabled="!canCancelSchedule(s)"
                                    class="px-2 py-1 rounded-lg text-xs font-medium border"
                                    :class="canCancelSchedule(s) ? 'border-red-200 text-red-600 hover:bg-red-50' : 'border-gray-200 text-gray-300 cursor-not-allowed'"
                                >
                                    Отменить
                                </button>
                            </div>
                        </div>

                        <div v-if="schedulesForDay.length === 0"
                            class="text-center py-4 text-gray-400 italic text-xs">
                            На этот день ничего не запланировано
                        </div>
                    </div>

                </div>
            </div>

        </div>

        <div
            v-if="showEditModal && editingSchedule"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
            @click.self="cancelEdit"
        >
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
                <div class="bg-gradient-to-r from-indigo-600 to-indigo-500 px-6 py-4 text-white">
                    <h3 class="text-lg font-bold">Редактирование тренировки</h3>
                    <p class="text-indigo-100 text-sm mt-1">{{ dayjs(editingSchedule.lesson_date).format('DD.MM.YYYY') }}</p>
                </div>
                <form @submit.prevent="saveEdit" class="p-6 space-y-4">
                    <p v-if="editForm.errors.conflict" class="text-red-600 text-sm bg-red-50 p-3 rounded-xl border border-red-100">
                        {{ editForm.errors.conflict }}
                    </p>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs text-slate-500 font-medium">Начало</label>
                            <input v-model="editForm.start_time" type="time" class="w-full mt-1 border-gray-300 rounded-xl" required />
                        </div>
                        <div>
                            <label class="text-xs text-slate-500 font-medium">Конец</label>
                            <input v-model="editForm.end_time" type="time" class="w-full mt-1 border-gray-300 rounded-xl" required />
                        </div>
                    </div>
                    <div>
                        <label class="text-xs text-slate-500 font-medium">Группа</label>
                        <select v-model="editForm.group_id" class="w-full mt-1 border-gray-300 rounded-xl">
                            <option v-for="g in groups" :key="g.id" :value="g.id">{{ g.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-slate-500 font-medium">Зал</label>
                        <select v-model="editForm.location_id" class="w-full mt-1 border-gray-300 rounded-xl">
                            <option v-for="l in locations" :key="l.id" :value="l.id">{{ l.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-slate-500 font-medium">Тренер</label>
                        <select v-model="editForm.coach_id" class="w-full mt-1 border-gray-300 rounded-xl">
                            <option v-for="c in coaches" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                    <div class="flex gap-2 pt-2">
                        <button type="submit" class="flex-1 bg-indigo-600 text-white py-2.5 rounded-xl font-semibold hover:bg-indigo-700" :disabled="editForm.processing">
                            Сохранить
                        </button>
                        <button type="button" @click="cancelEdit" class="px-4 py-2.5 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50">
                            Отмена
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div v-if="showCancelModal && cancellingSchedule" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50" @click.self="closeCancelModal">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6">
                <h3 class="text-lg font-bold text-slate-900">Отмена тренировки</h3>
                <p class="text-sm text-slate-500 mt-1">{{ dayjs(cancellingSchedule.lesson_date).format('DD.MM.YYYY') }}, {{ cancellingSchedule.start_time?.substring(0, 5) }}</p>
                <div v-if="cancellingSchedule.cancellation_reason_required" class="mt-4">
                    <label class="text-xs text-slate-500 font-medium">Причина отмены (обязательно — менее 5 ч до начала)</label>
                    <textarea v-model="cancelForm.cancellation_reason" rows="3" :class="['w-full mt-1 rounded-xl', cancelForm.errors.cancellation_reason ? 'border-red-500' : 'border-gray-300']" />
                    <p v-if="cancelForm.errors.cancellation_reason" class="text-red-600 text-xs mt-1">{{ cancelForm.errors.cancellation_reason }}</p>
                </div>
                <p v-else class="mt-4 text-sm text-slate-600">Подтвердите отмену тренировки.</p>
                <div class="flex gap-2 mt-6">
                    <button type="button" @click="submitCancel" class="flex-1 bg-red-600 text-white py-2.5 rounded-xl font-semibold hover:bg-red-700" :disabled="cancelForm.processing">
                        Отменить тренировку
                    </button>
                    <button type="button" @click="closeCancelModal" class="px-4 py-2.5 rounded-xl border border-slate-200 text-slate-600">Назад</button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>