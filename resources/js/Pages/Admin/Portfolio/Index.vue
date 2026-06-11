<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { formatDisplayDate } from '@/utils/formatDate';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import debounce from 'lodash/debounce';

const props = defineProps({
    athletes: Array,
    achievements: Array,
    selectedAthlete: Object,
    athleteReport: Object,
    filters: Object,
});

const athleteSearch = ref(props.filters?.athlete_search || '');
const selectedAthleteId = ref(props.filters?.athlete_id ? String(props.filters.athlete_id) : '');
const selectedAchievement = ref(null);
const showDetailsModal = ref(false);

watch(athleteSearch, debounce((value) => {
    router.get(route('admin.portfolio'), {
        athlete_search: value,
        athlete_id: selectedAthleteId.value || null,
    }, { preserveState: true, replace: true });
}, 300));

const toggleAthlete = (id) => {
    const next = selectedAthleteId.value === String(id) ? '' : String(id);
    selectedAthleteId.value = next;
    router.get(route('admin.portfolio'), {
        athlete_search: athleteSearch.value,
        athlete_id: next || null,
    }, { preserveState: true, replace: true });
};

const medicalClass = (status) => {
    if (status === 'expired') return 'bg-red-100 text-red-800 border-red-200';
    if (status === 'warning') return 'bg-amber-100 text-amber-800 border-amber-200';
    if (status === 'missing') return 'bg-slate-100 text-slate-600 border-slate-200';
    return 'bg-emerald-100 text-emerald-800 border-emerald-200';
};

const medicalLabel = (athlete) => {
    if (athlete.medical_status === 'expired') return 'Мед. справка просрочена';
    if (athlete.medical_status === 'warning') return `Справка истекает через ${athlete.medical_days_left} дн.`;
    if (athlete.medical_status === 'missing') return 'Нет мед. справки';
    return 'Мед. справка в порядке';
};

const exportAthleteCsv = () => {
    if (!selectedAthleteId.value) return;
    window.location.href = route('admin.portfolio.export.athlete', { athlete_id: selectedAthleteId.value });
};

const exportAthletePdf = () => {
    if (!selectedAthleteId.value) return;
    window.location.href = route('admin.portfolio.export.athlete.pdf', { athlete_id: selectedAthleteId.value });
};

const showAchievementDetails = (item) => {
    selectedAchievement.value = item;
    showDetailsModal.value = true;
};

const placeLabel = (place) => {
    if (place === 1) return '1 место';
    if (place === 2) return '2 место';
    if (place === 3) return '3 место';
    return place ? `${place} место` : '—';
};
</script>

<template>
    <Head title="Портфолио спортсменов" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <span>Портфолио спортсменов</span>
                <Link :href="route('admin.events')" class="text-sm text-indigo-600 hover:underline">Мероприятия →</Link>
            </div>
        </template>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <div class="lg:col-span-4 bg-white p-5 rounded-xl border border-slate-100 shadow-sm">
                <h3 class="font-bold mb-3">Спортсмены</h3>
                <input v-model="athleteSearch" type="text" placeholder="Поиск..." class="w-full border-gray-300 rounded-lg mb-3" />
                <div class="space-y-2 max-h-[560px] overflow-y-auto">
                    <button
                        v-for="athlete in athletes"
                        :key="athlete.id"
                        type="button"
                        @click="toggleAthlete(athlete.id)"
                        class="w-full text-left p-3 rounded-lg border transition"
                        :class="selectedAthleteId === String(athlete.id) ? 'border-indigo-400 bg-indigo-50' : 'border-slate-200 hover:border-indigo-200'"
                    >
                        <div class="font-semibold text-sm">{{ athlete.full_name }}</div>
                        <div class="flex flex-wrap gap-1 mt-1">
                            <span class="text-xs text-slate-500">{{ athlete.achievements_count }} достиж.</span>
                            <span class="text-[10px] px-1.5 py-0.5 rounded border font-medium" :class="medicalClass(athlete.medical_status)">
                                {{ medicalLabel(athlete) }}
                            </span>
                        </div>
                    </button>
                </div>
            </div>

            <div class="lg:col-span-8 space-y-4">
                <div v-if="!selectedAthlete" class="bg-white p-10 rounded-xl border text-center text-slate-500">
                    Выберите спортсмена слева, чтобы увидеть достижения
                </div>

                <template v-else>
                    <div class="bg-white p-5 rounded-xl border border-slate-100 shadow-sm">
                        <div class="flex flex-wrap justify-between gap-3 items-start">
                            <div>
                                <h2 class="text-xl font-bold">{{ selectedAthlete.full_name }}</h2>
                                <span class="text-xs px-2 py-1 rounded border mt-2 inline-block" :class="medicalClass(selectedAthlete.medical_status)">
                                    {{ medicalLabel(selectedAthlete) }}
                                    <template v-if="selectedAthlete.medical_expiry_date"> (до {{ selectedAthlete.medical_expiry_date }})</template>
                                </span>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <button type="button" @click="exportAthleteCsv" class="px-3 py-1.5 text-sm border rounded-lg hover:bg-slate-50">CSV</button>
                                <button type="button" @click="exportAthletePdf" class="px-3 py-1.5 text-sm border rounded-lg hover:bg-slate-50">PDF</button>
                            </div>
                        </div>

                        <div v-if="athleteReport" class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-4">
                            <div class="p-3 rounded-lg bg-slate-50 text-center">
                                <div class="text-2xl font-bold">{{ athleteReport.total }}</div>
                                <div class="text-xs text-slate-500">Всего</div>
                            </div>
                            <div class="p-3 rounded-lg bg-amber-50 text-center">
                                <div class="text-2xl font-bold text-amber-700">{{ athleteReport.places_1 }}</div>
                                <div class="text-xs text-slate-500">1 место</div>
                            </div>
                            <div class="p-3 rounded-lg bg-slate-100 text-center">
                                <div class="text-2xl font-bold">{{ athleteReport.places_2 }}</div>
                                <div class="text-xs text-slate-500">2 место</div>
                            </div>
                            <div class="p-3 rounded-lg bg-orange-50 text-center">
                                <div class="text-2xl font-bold text-orange-700">{{ athleteReport.places_3 }}</div>
                                <div class="text-xs text-slate-500">3 место</div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden app-table-wrap">
                        <table class="min-w-full text-sm">
                            <thead class="bg-slate-50 text-slate-600">
                                <tr>
                                    <th class="px-4 py-3 text-left">Мероприятие</th>
                                    <th class="px-4 py-3 text-left">Тип / уровень</th>
                                    <th class="px-4 py-3 text-left">Дата</th>
                                    <th class="px-4 py-3 text-left">Результат</th>
                                    <th class="px-4 py-3"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="item in achievements"
                                    :key="item.id"
                                    class="border-t border-slate-100 hover:bg-slate-50 cursor-pointer"
                                    @click="showAchievementDetails(item)"
                                >
                                    <td class="px-4 py-3 font-medium">{{ item.event_name }}</td>
                                    <td class="px-4 py-3 text-slate-600">{{ item.event_type }} · {{ item.event_level || '—' }}</td>
                                    <td class="px-4 py-3">{{ item.event_date_display || formatDisplayDate(item.event_date) || item.event_period || '—' }}</td>
                                    <td class="px-4 py-3">
                                        <span v-if="item.result_place" class="font-semibold">{{ placeLabel(item.result_place) }}</span>
                                        <span v-else-if="item.result_label">{{ item.result_label }}</span>
                                        <span v-else class="text-slate-400">Не заполнено</span>
                                    </td>
                                    <td class="px-4 py-3 text-indigo-600">Подробнее</td>
                                </tr>
                                <tr v-if="!achievements?.length">
                                    <td colspan="5" class="px-4 py-8 text-center text-slate-400">Нет достижений</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </template>
            </div>
        </div>

        <div v-if="showDetailsModal && selectedAchievement" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50" @click.self="showDetailsModal = false">
            <div class="bg-white rounded-2xl shadow-xl max-w-lg w-full p-6 max-h-[85vh] overflow-y-auto">
                <h3 class="font-bold text-lg mb-4">{{ selectedAchievement.event_name }}</h3>
                <dl class="space-y-2 text-sm">
                    <div><dt class="text-slate-500 inline">Тип: </dt>{{ selectedAchievement.event_type || '—' }}</div>
                    <div><dt class="text-slate-500 inline">Уровень: </dt>{{ selectedAchievement.event_level || '—' }}</div>
                    <div><dt class="text-slate-500 inline">Дата: </dt>{{ selectedAchievement.event_date_display || formatDisplayDate(selectedAchievement.event_date) || '—' }}</div>
                    <div><dt class="text-slate-500 inline">Период: </dt>{{ selectedAchievement.event_period || '—' }}</div>
                    <div><dt class="text-slate-500 inline">Место: </dt>{{ selectedAchievement.event_place || '—' }}</div>
                    <div><dt class="text-slate-500 inline">Ведущий: </dt>{{ selectedAchievement.event_host?.full_name || '—' }}</div>
                    <div v-if="selectedAchievement.cost != null"><dt class="text-slate-500 inline">Стоимость: </dt>{{ selectedAchievement.cost }} ₽</div>
                    <div><dt class="text-slate-500 inline">Результат: </dt>{{ selectedAchievement.result_label || placeLabel(selectedAchievement.result_place) }}</div>
                    <div><dt class="text-slate-500 inline">Разряд: </dt>{{ selectedAchievement.result_rank || '—' }}</div>
                    <div><dt class="text-slate-500 inline">Сертификат: </dt>{{ selectedAchievement.certificate_id || '—' }}</div>
                    <div v-if="selectedAchievement.result_description"><dt class="text-slate-500">Описание:</dt><p>{{ selectedAchievement.result_description }}</p></div>
                </dl>
                <a
                    v-if="selectedAchievement.evidence_file_path"
                    :href="`/storage/${selectedAchievement.evidence_file_path}`"
                    target="_blank"
                    class="inline-block mt-3 text-indigo-600 text-sm font-medium"
                >Открыть подтверждение</a>
                <div class="mt-4 flex justify-end">
                    <button type="button" @click="showDetailsModal = false" class="px-4 py-2 border rounded-lg text-sm">Закрыть</button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
