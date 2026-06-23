<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DateInput from '@/Components/DateInput.vue';
import { formatDisplayDate } from '@/utils/formatDate';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import debounce from 'lodash/debounce';
import { storageUrl } from '@/utils/storageUrl';

const props = defineProps({
    event: Object,
    participants: Array,
    availableAthletes: Array,
    eventTypes: Array,
    eventLevels: Array,
    eventHosts: Array,
    ranks: Array,
    readOnly: Boolean,
    filters: Object,
});

const athleteSearch = ref(props.filters?.athlete_search || '');
const editingEvent = ref(false);
const attachAthleteId = ref('');
const selectedAthletePreview = ref(null);

const eventForm = useForm({
    name: props.event.name,
    cost: props.event.cost,
    event_type_id: props.event.event_type_id,
    event_level_id: props.event.event_level_id ?? '',
    event_place: props.event.event_place ?? '',
    event_host_id: props.event.event_host_id ?? '',
    event_date: props.event.event_date ?? '',
    event_date_to: props.event.event_date_to ?? '',
    status: props.event.status ?? 'planned',
});

const attendanceForm = useForm({
    participants: (props.participants || []).map((p) => ({
        id: p.id,
        attendance_status: p.attendance_status ?? '',
    })),
});

const attendanceCertificates = ref({});

const resultsForm = useForm({
    status: props.event.status ?? 'planned',
    participants: (props.participants || []).map((p) => ({
        id: p.id,
        result_label: p.result_label ?? '',
        result_place: p.result_place ?? '',
        result_rank_id: p.result_rank_id ?? '',
        certificate_id: p.certificate_id ?? '',
        result_description: p.result_description ?? '',
    })),
});

const pendingEvidence = ref({});

const pendingFilesFor = (participantId) => pendingEvidence.value[participantId] || [];

const savedEvidenceFiles = (participantId) => participantIndex.value[participantId]?.evidence_files || [];

watch(() => props.participants, (list) => {
    resultsForm.participants = (list || []).map((p) => ({
        id: p.id,
        result_label: p.result_label ?? '',
        result_place: p.result_place ?? '',
        result_rank_id: p.result_rank_id ?? '',
        certificate_id: p.certificate_id ?? '',
        result_description: p.result_description ?? '',
    }));
    attendanceForm.participants = (list || []).map((p) => ({
        id: p.id,
        attendance_status: p.attendance_status ?? '',
    }));
}, { deep: true });

watch(athleteSearch, debounce((value) => {
    router.get(route('admin.events.show', props.event.id), { athlete_search: value || null }, { preserveState: true, replace: true });
}, 300));

const saveEvent = () => {
    eventForm.patch(route('admin.events.update', props.event.id), {
        onSuccess: () => { editingEvent.value = false; },
    });
};

const attachAthlete = () => {
    if (!attachAthleteId.value) return;
    router.post(route('admin.events.athletes.attach', props.event.id), { athlete_id: attachAthleteId.value }, {
        onSuccess: () => { attachAthleteId.value = ''; },
    });
};

const detachAthlete = (athleteId) => {
    if (!confirm('Исключить спортсмена из мероприятия?')) return;
    router.delete(route('admin.events.athletes.detach', [props.event.id, athleteId]));
};

const saveResults = () => {
    const formData = new FormData();
    formData.append('status', resultsForm.status);
    resultsForm.participants.forEach((p, index) => {
        formData.append(`participants[${index}][id]`, p.id);
        formData.append(`participants[${index}][result_label]`, p.result_label ?? '');
        if (p.result_place !== '' && p.result_place != null) {
            formData.append(`participants[${index}][result_place]`, p.result_place);
        }
        formData.append(`participants[${index}][result_rank_id]`, p.result_rank_id ?? '');
        formData.append(`participants[${index}][certificate_id]`, p.certificate_id ?? '');
        formData.append(`participants[${index}][result_description]`, p.result_description ?? '');
        const pending = pendingFilesFor(p.id);
        pending.forEach((upload) => {
            formData.append(`evidence_${p.id}[]`, upload);
        });
    });

    formData.append('_method', 'patch');
    router.post(route('admin.events.results.update', props.event.id), formData, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            pendingEvidence.value = {};
        },
    });
};

const onEvidenceChange = (participantId, event) => {
    const picked = Array.from(event.target.files || []);
    if (!picked.length) {
        return;
    }
    pendingEvidence.value[participantId] = [...pendingFilesFor(participantId), ...picked];
    event.target.value = '';
};

const removePendingEvidence = (participantId, index) => {
    const list = [...pendingFilesFor(participantId)];
    list.splice(index, 1);
    pendingEvidence.value[participantId] = list;
};

const deleteEvidenceFile = (fileId) => {
    if (!confirm('Удалить прикреплённый файл?')) return;
    router.delete(route('admin.events.evidence.destroy', [props.event.id, fileId]), {
        preserveScroll: true,
    });
};

const onAttendanceCertificateChange = (participantId, event) => {
    attendanceCertificates.value[participantId] = event.target.files?.[0] ?? null;
};

const saveAttendance = () => {
    const formData = new FormData();
    attendanceForm.participants.forEach((p, index) => {
        formData.append(`participants[${index}][id]`, p.id);
        formData.append(`participants[${index}][attendance_status]`, p.attendance_status ?? '');
        const file = attendanceCertificates.value[p.id];
        if (file) {
            formData.append(`certificates[${p.id}]`, file);
        }
    });
    formData.append('_method', 'patch');
    router.post(route('admin.events.attendance.update', props.event.id), formData, {
        forceFormData: true,
        preserveScroll: true,
    });
};

const selectAthleteForAttach = (athlete) => {
    attachAthleteId.value = athlete.id;
    selectedAthletePreview.value = athlete;
};

const attendanceStatusLabel = (status) => {
    if (status === 'Я') return 'Был';
    if (status === 'Н') return 'Не был';
    if (status === 'У') return 'Уваж. причина';
    return '—';
};

const medicalClass = (status) => {
    if (status === 'expired') return 'bg-red-100 text-red-800 border-red-300';
    if (status === 'warning') return 'bg-amber-100 text-amber-900 border-amber-300';
    if (status === 'missing') return 'bg-slate-100 text-slate-600';
    return 'bg-emerald-50 text-emerald-800';
};

const medicalText = (p) => {
    if (p.medical_status === 'expired') return 'Мед. справка просрочена';
    if (p.medical_status === 'warning') return `Справка истекает через ${p.medical_days_left} дн.`;
    if (p.medical_status === 'missing') return 'Нет мед. справки';
    return 'Мед. справка OK';
};

const exportCsv = () => {
    window.location.href = route('admin.events.export.csv', props.event.id);
};

const exportPdf = () => {
    window.location.href = route('admin.events.export.pdf', props.event.id);
};

const participantIndex = computed(() => {
    const map = {};
    props.participants?.forEach((p) => { map[p.id] = p; });
    return map;
});
</script>

<template>
    <Head :title="`Мероприятие: ${event.name}`" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center gap-2 min-w-0">
                <Link :href="route('admin.events')" class="text-indigo-600 text-sm shrink-0">← Мероприятия</Link>
                <span class="font-semibold truncate">{{ event.name }}</span>
            </div>
        </template>

        <div class="space-y-4 sm:space-y-6 max-w-6xl mx-auto min-w-0">
            <div class="bg-white p-4 sm:p-6 rounded-xl border border-slate-100 shadow-sm min-w-0">
                <div class="flex flex-col sm:flex-row sm:justify-between gap-3 mb-4">
                    <h2 class="text-lg font-bold">Данные мероприятия</h2>
                    <div class="flex flex-col sm:flex-row flex-wrap gap-2 w-full sm:w-auto">
                        <button type="button" @click="exportCsv" class="w-full sm:w-auto px-3 py-2 text-sm border rounded-lg">Отчёт CSV</button>
                        <button type="button" @click="exportPdf" class="w-full sm:w-auto px-3 py-2 text-sm border rounded-lg">Отчёт PDF</button>
                        <button v-if="!readOnly" type="button" @click="editingEvent = !editingEvent" class="w-full sm:w-auto px-3 py-2 text-sm bg-indigo-100 text-indigo-800 rounded-lg">
                            {{ editingEvent ? 'Отмена' : 'Редактировать' }}
                        </button>
                    </div>
                </div>

                <form v-if="editingEvent && !readOnly" @submit.prevent="saveEvent" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 min-w-0">
                    <div class="sm:col-span-2 min-w-0">
                        <label class="text-xs text-slate-500">Наименование</label>
                        <input v-model="eventForm.name" required class="w-full max-w-full min-w-0 border-gray-300 rounded-lg" />
                    </div>
                    <div class="min-w-0">
                        <label class="text-xs text-slate-500">Стоимость</label>
                        <input v-model="eventForm.cost" type="number" min="0" step="0.01" class="w-full max-w-full min-w-0 border-gray-300 rounded-lg" />
                    </div>
                    <div class="min-w-0">
                        <label class="text-xs text-slate-500">Тип</label>
                        <select v-model="eventForm.event_type_id" class="w-full max-w-full min-w-0 border-gray-300 rounded-lg">
                            <option v-for="t in eventTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
                        </select>
                    </div>
                    <div class="min-w-0">
                        <label class="text-xs text-slate-500">Уровень</label>
                        <select v-model="eventForm.event_level_id" class="w-full max-w-full min-w-0 border-gray-300 rounded-lg">
                            <option value="">—</option>
                            <option v-for="l in eventLevels" :key="l.id" :value="l.id">{{ l.name }}</option>
                        </select>
                    </div>
                    <div class="min-w-0">
                        <label class="text-xs text-slate-500">Ведущий</label>
                        <select v-model="eventForm.event_host_id" class="w-full max-w-full min-w-0 border-gray-300 rounded-lg">
                            <option value="">—</option>
                            <option v-for="h in eventHosts" :key="h.id" :value="h.id">{{ h.full_name }}</option>
                        </select>
                    </div>
                    <div class="min-w-0">
                        <label class="text-xs text-slate-500">Место</label>
                        <input v-model="eventForm.event_place" class="w-full max-w-full min-w-0 border-gray-300 rounded-lg" />
                    </div>
                    <div class="min-w-0">
                        <DateInput v-model="eventForm.event_date" label="Дата начала" input-class="w-full max-w-full min-w-0 border-gray-300 rounded-lg" />
                    </div>
                    <div class="min-w-0">
                        <DateInput v-model="eventForm.event_date_to" label="Дата окончания" input-class="w-full max-w-full min-w-0 border-gray-300 rounded-lg" />
                    </div>
                    <div class="min-w-0">
                        <label class="text-xs text-slate-500">Статус</label>
                        <select v-model="eventForm.status" class="w-full max-w-full min-w-0 border-gray-300 rounded-lg">
                            <option value="planned">Запланировано</option>
                            <option value="completed">Проведено</option>
                        </select>
                    </div>
                    <div class="sm:col-span-2 lg:col-span-3">
                        <button type="submit" class="w-full sm:w-auto bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-semibold">Сохранить</button>
                    </div>
                </form>

                <dl v-else class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm min-w-0">
                    <div class="min-w-0"><dt class="text-slate-500">Тип</dt><dd class="font-medium break-anywhere">{{ event.event_type?.name }}</dd></div>
                    <div class="min-w-0"><dt class="text-slate-500">Уровень</dt><dd class="font-medium break-anywhere">{{ event.event_level?.name || '—' }}</dd></div>
                    <div class="min-w-0"><dt class="text-slate-500">Стоимость</dt><dd class="font-medium">{{ event.cost }} ₽</dd></div>
                    <div class="min-w-0"><dt class="text-slate-500">Дата</dt><dd class="font-medium break-anywhere">{{ event.event_date_range_display || event.event_date_display || formatDisplayDate(event.event_date) || '—' }}</dd></div>
                    <div class="min-w-0"><dt class="text-slate-500">Место</dt><dd class="font-medium break-anywhere">{{ event.event_place || '—' }}</dd></div>
                    <div class="min-w-0"><dt class="text-slate-500">Ведущий</dt><dd class="font-medium break-anywhere">{{ event.event_host?.full_name || '—' }}</dd></div>
                </dl>
            </div>

            <div v-if="!readOnly" class="bg-white p-4 sm:p-6 rounded-xl border border-slate-100 min-w-0">
                <h3 class="font-bold mb-3">Добавить спортсмена</h3>
                <input v-model="athleteSearch" placeholder="Поиск..." class="w-full max-w-full min-w-0 border-gray-300 rounded-lg mb-3" />
                <div class="flex flex-col sm:flex-row sm:flex-wrap gap-2 mb-3 max-h-48 overflow-y-auto">
                    <button
                        v-for="a in availableAthletes"
                        :key="a.id"
                        type="button"
                        @click="selectAthleteForAttach(a)"
                        class="w-full sm:w-auto text-left px-3 py-2 rounded-lg border text-sm transition min-w-0"
                        :class="[
                            attachAthleteId === a.id ? 'border-indigo-500 bg-indigo-50' : 'border-slate-200',
                            a.medical_status === 'expired' || a.medical_status === 'warning' ? 'border-amber-400' : '',
                        ]"
                    >
                        <span class="break-anywhere">{{ a.full_name }}</span>
                        <span class="block text-[10px] mt-0.5 break-anywhere" :class="medicalClass(a.medical_status)">{{ medicalText({ medical_status: a.medical_status, medical_days_left: a.medical_days_left }) }}</span>
                    </button>
                </div>
                <div v-if="selectedAthletePreview" class="mb-4 p-4 rounded-xl border border-slate-200 bg-slate-50 text-sm space-y-3 min-w-0 overflow-hidden">
                    <p class="font-semibold break-anywhere">{{ selectedAthletePreview.full_name }}</p>
                    <div v-if="selectedAthletePreview.inventory_items?.length">
                        <p class="text-xs text-slate-500 mb-1">Инвентарь</p>
                        <p>{{ selectedAthletePreview.inventory_items.join(', ') }}</p>
                    </div>
                    <p v-else class="text-slate-400 text-xs">Инвентарь не заполнен</p>
                    <div v-if="selectedAthletePreview.documents?.length">
                        <p class="text-xs text-slate-500 mb-1">Документы</p>
                        <ul class="space-y-1">
                            <li v-for="(doc, idx) in selectedAthletePreview.documents" :key="idx">
                                {{ doc.label }}
                                <span v-if="doc.expiry_date" class="text-slate-400">до {{ doc.expiry_date }}</span>
                                <a v-if="doc.file_path" :href="storageUrl(doc.file_path)" target="_blank" class="text-indigo-600 ml-2 text-xs">файл</a>
                            </li>
                        </ul>
                    </div>
                    <p v-else class="text-slate-400 text-xs">Документы не загружены</p>
                </div>
                <button type="button" :disabled="!attachAthleteId" @click="attachAthlete" class="w-full sm:w-auto bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-semibold disabled:opacity-50">
                    Добавить в мероприятие
                </button>
            </div>

            <div v-if="participants?.length" class="bg-white p-4 sm:p-6 rounded-xl border border-slate-100 min-w-0">
                <h3 class="font-bold mb-4">Посещаемость на мероприятии</h3>
                <p class="text-xs text-slate-500 mb-4 break-anywhere">При отметках «Был» или «Не был» (без уважительной причины) стоимость мероприятия списывается с баланса спортсмена. Для «Уваж. причина» приложите справку — списание отменяется.</p>
                <form @submit.prevent="saveAttendance" class="space-y-3">
                    <div
                        v-for="(row, idx) in attendanceForm.participants"
                        :key="`att-${row.id}`"
                        class="p-4 rounded-xl border border-slate-200 min-w-0"
                    >
                        <div class="flex flex-col sm:flex-row sm:justify-between gap-1 mb-2 min-w-0">
                            <span class="font-semibold break-anywhere">{{ participantIndex[row.id]?.full_name }}</span>
                            <span v-if="participantIndex[row.id]?.attendance_status" class="text-xs text-slate-500 shrink-0">
                                Текущая: {{ attendanceStatusLabel(participantIndex[row.id]?.attendance_status) }}
                            </span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 min-w-0">
                            <div class="min-w-0">
                                <label class="text-xs text-slate-500">Отметка</label>
                                <select v-model="row.attendance_status" :disabled="readOnly" class="w-full max-w-full min-w-0 border-gray-300 rounded-lg text-sm">
                                    <option value="">—</option>
                                    <option value="Я">Был (Я)</option>
                                    <option value="Н">Не был (Н)</option>
                                    <option value="У">Уваж. причина (У)</option>
                                </select>
                            </div>
                            <div v-if="row.attendance_status === 'У' && !readOnly" class="min-w-0 sm:col-span-2">
                                <label class="text-xs text-slate-500">Справка</label>
                                <input type="file" accept=".pdf,.jpg,.jpeg,.png" class="text-sm w-full max-w-full min-w-0" @change="onAttendanceCertificateChange(row.id, $event)" />
                                <a
                                    v-if="participantIndex[row.id]?.excused_certificate"
                                    :href="storageUrl(participantIndex[row.id].excused_certificate)"
                                    target="_blank"
                                    class="text-xs text-indigo-600 block mt-1"
                                >Текущая справка</a>
                            </div>
                        </div>
                    </div>
                    <button v-if="!readOnly" type="submit" class="w-full sm:w-auto bg-amber-600 text-white px-6 py-2 rounded-lg font-semibold" :disabled="attendanceForm.processing">
                        Сохранить посещаемость
                    </button>
                </form>
            </div>

            <div class="bg-white p-4 sm:p-6 rounded-xl border border-slate-100 min-w-0">
                <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3 mb-4">
                    <h3 class="font-bold">Участники и результаты ({{ participants?.length || 0 }})</h3>
                    <select v-if="!readOnly" v-model="resultsForm.status" class="w-full sm:w-auto border-gray-300 rounded-lg text-sm min-h-[2.75rem]">
                        <option value="planned">Запланировано</option>
                        <option value="completed">Проведено</option>
                    </select>
                </div>

                <div v-if="!participants?.length" class="text-slate-400 text-sm py-6 text-center">Нет участников</div>

                <form v-else @submit.prevent="saveResults" class="space-y-4">
                    <div
                        v-for="(row, idx) in resultsForm.participants"
                        :key="row.id"
                        class="p-4 rounded-xl border min-w-0 overflow-hidden"
                        :class="(participantIndex[row.id]?.medical_status === 'expired' || participantIndex[row.id]?.medical_status === 'warning') ? 'border-amber-400 bg-amber-50/30' : 'border-slate-200'"
                    >
                        <div class="flex flex-col sm:flex-row sm:justify-between gap-2 mb-3 min-w-0">
                            <div class="min-w-0 flex-1">
                                <span class="font-semibold break-anywhere">{{ participantIndex[row.id]?.full_name }}</span>
                                <span class="mt-1 sm:mt-0 sm:ml-2 inline-block text-[10px] px-2 py-0.5 rounded border break-anywhere" :class="medicalClass(participantIndex[row.id]?.medical_status)">
                                    {{ medicalText(participantIndex[row.id] || {}) }}
                                </span>
                            </div>
                            <button v-if="!readOnly" type="button" @click="detachAthlete(participantIndex[row.id]?.athlete_id)" class="text-red-600 text-xs shrink-0 self-start">Удалить</button>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 min-w-0">
                            <div class="min-w-0">
                                <label class="text-xs text-slate-500">Результат (текст)</label>
                                <input v-model="row.result_label" :readonly="readOnly" class="w-full max-w-full min-w-0 border-gray-300 rounded-lg text-sm" />
                            </div>
                            <div class="min-w-0">
                                <label class="text-xs text-slate-500">Место (1–3)</label>
                                <input v-model="row.result_place" type="number" min="1" max="3" :readonly="readOnly" class="w-full max-w-full min-w-0 border-gray-300 rounded-lg text-sm" />
                            </div>
                            <div class="min-w-0">
                                <label class="text-xs text-slate-500">Разряд</label>
                                <select v-model="row.result_rank_id" :disabled="readOnly" class="w-full max-w-full min-w-0 border-gray-300 rounded-lg text-sm">
                                    <option value="">—</option>
                                    <option v-for="r in ranks" :key="r.id" :value="r.id">{{ r.name }}</option>
                                </select>
                            </div>
                            <div class="min-w-0">
                                <label class="text-xs text-slate-500">ID сертификата</label>
                                <input v-model="row.certificate_id" :readonly="readOnly" class="w-full max-w-full min-w-0 border-gray-300 rounded-lg text-sm" />
                            </div>
                            <div class="min-w-0 sm:col-span-2">
                                <label class="text-xs text-slate-500">Описание</label>
                                <input v-model="row.result_description" :readonly="readOnly" class="w-full max-w-full min-w-0 border-gray-300 rounded-lg text-sm" />
                            </div>
                        </div>
                        <div class="min-w-0 border-t border-slate-100 pt-3 mt-3">
                            <label class="text-xs text-slate-500 block mb-2">Подтверждающие файлы</label>
                            <div
                                v-if="savedEvidenceFiles(row.id).length || pendingFilesFor(row.id).length"
                                class="flex flex-wrap gap-2 mb-2"
                            >
                                <div
                                    v-for="file in savedEvidenceFiles(row.id)"
                                    :key="'saved-' + file.id"
                                    class="inline-flex items-center gap-1 max-w-full rounded-lg border border-indigo-200 bg-indigo-50 px-2 py-1 text-xs"
                                >
                                    <a :href="file.url" target="_blank" class="text-indigo-700 break-anywhere hover:underline">{{ file.original_name }}</a>
                                    <button
                                        v-if="!readOnly"
                                        type="button"
                                        class="shrink-0 text-red-600 hover:text-red-800 font-bold leading-none px-1"
                                        title="Удалить файл"
                                        @click="deleteEvidenceFile(file.id)"
                                    >×</button>
                                </div>
                                <div
                                    v-for="(file, idx) in pendingFilesFor(row.id)"
                                    :key="'pending-' + row.id + '-' + idx"
                                    class="inline-flex items-center gap-1 max-w-full rounded-lg border border-emerald-200 bg-emerald-50 px-2 py-1 text-xs"
                                >
                                    <span class="text-emerald-800 break-anywhere">{{ file.name }} (новый)</span>
                                    <button
                                        v-if="!readOnly"
                                        type="button"
                                        class="shrink-0 text-red-600 hover:text-red-800 font-bold leading-none px-1"
                                        title="Убрать из списка"
                                        @click="removePendingEvidence(row.id, idx)"
                                    >×</button>
                                </div>
                            </div>
                            <p v-else class="text-xs text-slate-400 mb-2">Файлы не прикреплены</p>
                            <input
                                v-if="!readOnly"
                                type="file"
                                accept=".pdf,.jpg,.jpeg,.png"
                                multiple
                                class="text-sm w-full max-w-full min-w-0"
                                @change="onEvidenceChange(row.id, $event)"
                            />
                        </div>
                    </div>

                    <button v-if="!readOnly" type="submit" class="w-full sm:w-auto bg-emerald-600 text-white px-6 py-2 rounded-lg font-semibold" :disabled="resultsForm.processing">
                        Сохранить результаты
                    </button>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
