<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DateInput from '@/Components/DateInput.vue';
import { formatDisplayDate } from '@/utils/formatDate';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import debounce from 'lodash/debounce';

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

const eventForm = useForm({
    name: props.event.name,
    cost: props.event.cost,
    event_type_id: props.event.event_type_id,
    event_level_id: props.event.event_level_id ?? '',
    event_place: props.event.event_place ?? '',
    event_host_id: props.event.event_host_id ?? '',
    event_date: props.event.event_date ?? '',
    event_period: props.event.event_period ?? '',
    status: props.event.status ?? 'planned',
});

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

const evidenceFiles = ref({});

watch(() => props.participants, (list) => {
    resultsForm.participants = (list || []).map((p) => ({
        id: p.id,
        result_label: p.result_label ?? '',
        result_place: p.result_place ?? '',
        result_rank_id: p.result_rank_id ?? '',
        certificate_id: p.certificate_id ?? '',
        result_description: p.result_description ?? '',
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
        const file = evidenceFiles.value[p.id];
        if (file) {
            formData.append(`evidence_${p.id}`, file);
        }
    });

    formData.append('_method', 'patch');
    router.post(route('admin.events.results.update', props.event.id), formData, {
        forceFormData: true,
        preserveScroll: true,
    });
};

const onEvidenceChange = (participantId, event) => {
    evidenceFiles.value[participantId] = event.target.files?.[0] ?? null;
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

        <div class="space-y-6 max-w-6xl mx-auto">
            <div class="bg-white p-6 rounded-xl border border-slate-100 shadow-sm">
                <div class="flex flex-wrap justify-between gap-3 mb-4">
                    <h2 class="text-lg font-bold">Данные мероприятия</h2>
                    <div class="flex gap-2">
                        <button type="button" @click="exportCsv" class="px-3 py-1.5 text-sm border rounded-lg">Отчёт CSV</button>
                        <button type="button" @click="exportPdf" class="px-3 py-1.5 text-sm border rounded-lg">Отчёт PDF</button>
                        <button v-if="!readOnly" type="button" @click="editingEvent = !editingEvent" class="px-3 py-1.5 text-sm bg-indigo-100 text-indigo-800 rounded-lg">
                            {{ editingEvent ? 'Отмена' : 'Редактировать' }}
                        </button>
                    </div>
                </div>

                <form v-if="editingEvent && !readOnly" @submit.prevent="saveEvent" class="grid md:grid-cols-3 gap-4">
                    <div class="md:col-span-2">
                        <label class="text-xs text-slate-500">Наименование</label>
                        <input v-model="eventForm.name" required class="w-full border-gray-300 rounded-lg" />
                    </div>
                    <div>
                        <label class="text-xs text-slate-500">Стоимость</label>
                        <input v-model="eventForm.cost" type="number" min="0" step="0.01" class="w-full border-gray-300 rounded-lg" />
                    </div>
                    <div>
                        <label class="text-xs text-slate-500">Тип</label>
                        <select v-model="eventForm.event_type_id" class="w-full border-gray-300 rounded-lg">
                            <option v-for="t in eventTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-slate-500">Уровень</label>
                        <select v-model="eventForm.event_level_id" class="w-full border-gray-300 rounded-lg">
                            <option value="">—</option>
                            <option v-for="l in eventLevels" :key="l.id" :value="l.id">{{ l.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-slate-500">Ведущий</label>
                        <select v-model="eventForm.event_host_id" class="w-full border-gray-300 rounded-lg">
                            <option value="">—</option>
                            <option v-for="h in eventHosts" :key="h.id" :value="h.id">{{ h.full_name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-slate-500">Место</label>
                        <input v-model="eventForm.event_place" class="w-full border-gray-300 rounded-lg" />
                    </div>
                    <div>
                        <label class="text-xs text-slate-500">Дата</label>
                        <DateInput v-model="eventForm.event_date" label="Дата мероприятия" input-class="w-full border-gray-300 rounded-lg" />
                    </div>
                    <div>
                        <label class="text-xs text-slate-500">Статус</label>
                        <select v-model="eventForm.status" class="w-full border-gray-300 rounded-lg">
                            <option value="planned">Запланировано</option>
                            <option value="completed">Проведено</option>
                        </select>
                    </div>
                    <div class="md:col-span-3">
                        <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-semibold">Сохранить</button>
                    </div>
                </form>

                <dl v-else class="grid sm:grid-cols-2 gap-3 text-sm">
                    <div><dt class="text-slate-500">Тип</dt><dd class="font-medium">{{ event.event_type?.name }}</dd></div>
                    <div><dt class="text-slate-500">Уровень</dt><dd class="font-medium">{{ event.event_level?.name || '—' }}</dd></div>
                    <div><dt class="text-slate-500">Стоимость</dt><dd class="font-medium">{{ event.cost }} ₽</dd></div>
                    <div><dt class="text-slate-500">Дата</dt><dd class="font-medium">{{ event.event_date_display || formatDisplayDate(event.event_date) || event.event_period || '—' }}</dd></div>
                    <div><dt class="text-slate-500">Место</dt><dd class="font-medium">{{ event.event_place || '—' }}</dd></div>
                    <div><dt class="text-slate-500">Ведущий</dt><dd class="font-medium">{{ event.event_host?.full_name || '—' }}</dd></div>
                </dl>
            </div>

            <div v-if="!readOnly" class="bg-white p-6 rounded-xl border border-slate-100">
                <h3 class="font-bold mb-3">Добавить спортсмена</h3>
                <input v-model="athleteSearch" placeholder="Поиск..." class="w-full border-gray-300 rounded-lg mb-3" />
                <div class="flex flex-wrap gap-2 mb-3 max-h-40 overflow-y-auto">
                    <button
                        v-for="a in availableAthletes"
                        :key="a.id"
                        type="button"
                        @click="attachAthleteId = a.id"
                        class="text-left px-3 py-2 rounded-lg border text-sm transition"
                        :class="[
                            attachAthleteId === a.id ? 'border-indigo-500 bg-indigo-50' : 'border-slate-200',
                            a.medical_status === 'expired' || a.medical_status === 'warning' ? 'border-amber-400' : '',
                        ]"
                    >
                        {{ a.full_name }}
                        <span class="block text-[10px] mt-0.5" :class="medicalClass(a.medical_status)">{{ medicalText({ medical_status: a.medical_status, medical_days_left: a.medical_days_left }) }}</span>
                    </button>
                </div>
                <button type="button" :disabled="!attachAthleteId" @click="attachAthlete" class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-semibold disabled:opacity-50">
                    Добавить в мероприятие
                </button>
            </div>

            <div class="bg-white p-6 rounded-xl border border-slate-100">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-bold">Участники и результаты ({{ participants?.length || 0 }})</h3>
                    <select v-if="!readOnly" v-model="resultsForm.status" class="border-gray-300 rounded-lg text-sm">
                        <option value="planned">Запланировано</option>
                        <option value="completed">Проведено</option>
                    </select>
                </div>

                <div v-if="!participants?.length" class="text-slate-400 text-sm py-6 text-center">Нет участников</div>

                <form v-else @submit.prevent="saveResults" class="space-y-4">
                    <div
                        v-for="(row, idx) in resultsForm.participants"
                        :key="row.id"
                        class="p-4 rounded-xl border"
                        :class="(participantIndex[row.id]?.medical_status === 'expired' || participantIndex[row.id]?.medical_status === 'warning') ? 'border-amber-400 bg-amber-50/30' : 'border-slate-200'"
                    >
                        <div class="flex flex-wrap justify-between gap-2 mb-3">
                            <div>
                                <span class="font-semibold">{{ participantIndex[row.id]?.full_name }}</span>
                                <span class="ml-2 text-[10px] px-2 py-0.5 rounded border" :class="medicalClass(participantIndex[row.id]?.medical_status)">
                                    {{ medicalText(participantIndex[row.id] || {}) }}
                                </span>
                            </div>
                            <button v-if="!readOnly" type="button" @click="detachAthlete(participantIndex[row.id]?.athlete_id)" class="text-red-600 text-xs">Удалить</button>
                        </div>
                        <div class="grid md:grid-cols-3 gap-3">
                            <div>
                                <label class="text-xs text-slate-500">Результат (текст)</label>
                                <input v-model="row.result_label" :readonly="readOnly" class="w-full border-gray-300 rounded-lg text-sm" />
                            </div>
                            <div>
                                <label class="text-xs text-slate-500">Место (1–3)</label>
                                <input v-model="row.result_place" type="number" min="1" max="3" :readonly="readOnly" class="w-full border-gray-300 rounded-lg text-sm" />
                            </div>
                            <div>
                                <label class="text-xs text-slate-500">Разряд</label>
                                <select v-model="row.result_rank_id" :disabled="readOnly" class="w-full border-gray-300 rounded-lg text-sm">
                                    <option value="">—</option>
                                    <option v-for="r in ranks" :key="r.id" :value="r.id">{{ r.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-xs text-slate-500">ID сертификата</label>
                                <input v-model="row.certificate_id" :readonly="readOnly" class="w-full border-gray-300 rounded-lg text-sm" />
                            </div>
                            <div class="md:col-span-2">
                                <label class="text-xs text-slate-500">Описание</label>
                                <input v-model="row.result_description" :readonly="readOnly" class="w-full border-gray-300 rounded-lg text-sm" />
                            </div>
                            <div v-if="!readOnly">
                                <label class="text-xs text-slate-500">Подтверждение (файл)</label>
                                <input type="file" accept=".pdf,.jpg,.jpeg,.png" class="text-sm" @change="onEvidenceChange(row.id, $event)" />
                                <a
                                    v-if="participantIndex[row.id]?.evidence_file_path"
                                    :href="`/storage/${participantIndex[row.id].evidence_file_path}`"
                                    target="_blank"
                                    class="text-xs text-indigo-600 block mt-1"
                                >Текущий файл</a>
                            </div>
                        </div>
                    </div>

                    <button v-if="!readOnly" type="submit" class="bg-emerald-600 text-white px-6 py-2 rounded-lg font-semibold" :disabled="resultsForm.processing">
                        Сохранить результаты
                    </button>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
