<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import debounce from 'lodash/debounce';

const props = defineProps({
    athletes: Array,
    eventTypes: Array,
    eventLevels: Array,
    eventHosts: Array,
    ranks: Array,
    achievements: Array,
    selectedAthlete: Object,
    filters: Object,
});

const athleteSearch = ref(props.filters?.athlete_search || '');
const selectedAthleteId = ref(props.filters?.athlete_id ? String(props.filters.athlete_id) : '');
const selectedAchievement = ref(null);
const showDetailsModal = ref(false);

const achievementForm = useForm({
    id: null,
    athlete_id: props.filters?.athlete_id ?? '',
    event_name: '',
    event_type_id: '',
    event_place: '',
    event_date: '',
    event_period: '',
    event_level_id: '',
    event_host_id: '',
    result_label: '',
    result_place: '',
    result_rank_id: '',
    certificate_id: '',
    result_description: '',
    evidence_file: null,
});

const filteredAchievements = computed(() => props.achievements || []);

watch(athleteSearch, debounce((value) => {
    router.get(route('admin.portfolio'), {
        athlete_search: value,
        athlete_id: selectedAthleteId.value || null,
    }, { preserveState: true, replace: true });
}, 300));

watch(selectedAthleteId, (value) => {
    achievementForm.athlete_id = value ? Number(value) : '';
    router.get(route('admin.portfolio'), {
        athlete_search: athleteSearch.value,
        athlete_id: value || null,
    }, { preserveState: true, replace: true });
});

const saveAchievement = () => {
    if (achievementForm.id) {
        achievementForm.patch(route('admin.portfolio.achievements.update', achievementForm.id), {
            forceFormData: true,
            onSuccess: () => resetAchievementForm(),
        });
        return;
    }

    achievementForm.post(route('admin.portfolio.achievements.store'), {
        forceFormData: true,
        onSuccess: () => resetAchievementForm(),
    });
};

const resetAchievementForm = () => {
    achievementForm.reset('id', 'event_name', 'event_type_id', 'event_place', 'event_date', 'event_period', 'event_level_id', 'event_host_id', 'result_label', 'result_place', 'result_rank_id', 'certificate_id', 'result_description', 'evidence_file');
    achievementForm.athlete_id = selectedAthleteId.value ? Number(selectedAthleteId.value) : '';
};

const editAchievement = (item) => {
    selectedAchievement.value = item;
    achievementForm.id = item.id;
    achievementForm.athlete_id = item.athlete_id;
    achievementForm.event_name = item.event_name ?? '';
    achievementForm.event_type_id = item.event_type_id ?? '';
    achievementForm.event_place = item.event_place ?? '';
    achievementForm.event_date = item.event_date ?? '';
    achievementForm.event_period = item.event_period ?? '';
    achievementForm.event_level_id = item.event_level_id ?? '';
    achievementForm.event_host_id = item.event_host_id ?? '';
    achievementForm.result_label = item.result_label ?? '';
    achievementForm.result_place = item.result_place ?? '';
    achievementForm.result_rank_id = item.result_rank_id ?? '';
    achievementForm.certificate_id = item.certificate_id ?? '';
    achievementForm.result_description = item.result_description ?? '';
    achievementForm.evidence_file = null;
};

const removeAchievement = (achievementId) => {
    if (!confirm('Удалить достижение?')) return;
    achievementForm.delete(route('admin.portfolio.achievements.destroy', achievementId));
};

const showAchievementDetails = (item) => {
    selectedAchievement.value = item;
    showDetailsModal.value = true;
};

const selectedAchievementIndex = computed(() =>
    filteredAchievements.value.findIndex((item) => item.id === selectedAchievement.value?.id)
);

const selectPrevAchievement = () => {
    const idx = selectedAchievementIndex.value;
    if (idx > 0) {
        selectedAchievement.value = filteredAchievements.value[idx - 1];
    }
};

const selectNextAchievement = () => {
    const idx = selectedAchievementIndex.value;
    if (idx >= 0 && idx < filteredAchievements.value.length - 1) {
        selectedAchievement.value = filteredAchievements.value[idx + 1];
    }
};
</script>

<template>
    <Head title="Портфолио и мероприятия" />
    <AuthenticatedLayout>
        <template #header>Портфолио и мероприятия</template>

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
            <div class="bg-white p-6 rounded-xl shadow-sm">
                <h3 class="font-bold mb-3">Спортсмены</h3>
                <input v-model="athleteSearch" placeholder="Поиск спортсмена..." class="w-full border-gray-300 rounded-lg mb-3" />
                <div class="space-y-2 max-h-[600px] overflow-y-auto">
                    <button
                        v-for="a in athletes"
                        :key="a.id"
                        @click="selectedAthleteId = String(a.id)"
                        class="w-full text-left p-3 rounded-lg border transition"
                        :class="selectedAthleteId === String(a.id) ? 'border-indigo-400 bg-indigo-50' : 'border-gray-200 hover:border-indigo-300'"
                    >
                        {{ a.last_name_nom }} {{ a.first_name_nom }} {{ a.middle_name_nom }}
                    </button>
                    <p v-if="athletes.length === 0" class="text-sm text-gray-400">Список пуст</p>
                </div>
                <div v-if="selectedAthlete" class="mt-4 text-sm text-slate-600">
                    Выбран: <b>{{ selectedAthlete.last_name_nom }} {{ selectedAthlete.first_name_nom }}</b>
                </div>
            </div>

            <div class="xl:col-span-2 bg-white p-6 rounded-xl shadow-sm">
                <h3 class="font-bold mb-4">Достижения спортсмена</h3>
                <div v-if="!selectedAthleteId" class="text-sm text-gray-500 mb-4">
                    Выберите спортсмена слева, чтобы увидеть его личные достижения.
                </div>
                <table class="w-full text-sm mb-6">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="text-left p-2">Мероприятие</th>
                            <th class="text-left p-2">Дата</th>
                            <th class="text-left p-2">Результат</th>
                            <th class="text-left p-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in filteredAchievements" :key="item.id" class="border-t cursor-pointer hover:bg-gray-50" @click="showAchievementDetails(item)">
                            <td class="p-2">{{ item.event_name }}</td>
                            <td class="p-2">{{ item.event_date || item.event_period || '—' }}</td>
                            <td class="p-2">{{ item.result_label || '—' }} ({{ item.result_place || '—' }})</td>
                            <td class="p-2">
                                <button @click="editAchievement(item)" class="text-xs px-2 py-1 rounded bg-indigo-100 text-indigo-700 mr-2">Изменить</button>
                                <button @click="removeAchievement(item.id)" class="text-xs px-2 py-1 rounded bg-red-100 text-red-700">Удалить</button>
                            </td>
                        </tr>
                        <tr v-if="filteredAchievements.length === 0">
                            <td colspan="4" class="p-3 text-gray-400">Нет достижений</td>
                        </tr>
                    </tbody>
                </table>

                <h4 class="font-semibold mb-3">{{ achievementForm.id ? 'Редактировать достижение' : 'Добавить достижение' }}</h4>
                <form @submit.prevent="saveAchievement" class="space-y-3">
                    <input v-model="achievementForm.event_name" placeholder="Название мероприятия" class="w-full border-gray-300 rounded-lg" required>
                    <select v-model="achievementForm.event_type_id" class="w-full border-gray-300 rounded-lg" required>
                        <option value="">Тип мероприятия</option>
                        <option v-for="t in eventTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
                    </select>
                    <div class="grid grid-cols-2 gap-2">
                        <input v-model="achievementForm.event_place" placeholder="Место проведения" class="w-full border-gray-300 rounded-lg">
                        <input v-model="achievementForm.event_date" type="date" class="w-full border-gray-300 rounded-lg">
                    </div>
                    <select v-model="achievementForm.event_level_id" class="w-full border-gray-300 rounded-lg">
                        <option value="">Уровень</option>
                        <option v-for="l in eventLevels" :key="l.id" :value="l.id">{{ l.name }}</option>
                    </select>
                    <select v-model="achievementForm.event_host_id" class="w-full border-gray-300 rounded-lg">
                        <option value="">Ведущий</option>
                        <option v-for="h in eventHosts" :key="h.id" :value="h.id">{{ h.full_name }}</option>
                    </select>
                    <div class="grid grid-cols-2 gap-2">
                        <input v-model="achievementForm.result_label" placeholder="Результат" class="w-full border-gray-300 rounded-lg">
                        <input v-model="achievementForm.result_place" type="number" min="1" max="3" placeholder="Место" class="w-full border-gray-300 rounded-lg">
                    </div>
                    <select v-model="achievementForm.result_rank_id" class="w-full border-gray-300 rounded-lg">
                        <option value="">Присвоенный разряд</option>
                        <option v-for="r in ranks" :key="r.id" :value="r.id">{{ r.name }}</option>
                    </select>
                    <input v-model="achievementForm.certificate_id" placeholder="ID сертификата" class="w-full border-gray-300 rounded-lg">
                    <textarea v-model="achievementForm.result_description" placeholder="Описание" class="w-full border-gray-300 rounded-lg" rows="2"></textarea>
                    <input type="file" @input="achievementForm.evidence_file = $event.target.files[0]" class="w-full text-sm">
                    <div class="flex gap-2">
                        <button class="bg-green-600 text-white py-2 px-4 rounded-lg font-bold">Сохранить</button>
                        <button type="button" @click="resetAchievementForm" class="bg-gray-100 text-gray-700 py-2 px-4 rounded-lg font-bold">Отмена</button>
                    </div>
                </form>
            </div>
        </div>

        <div
            v-if="showDetailsModal && selectedAchievement"
            class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4"
            @click.self="showDetailsModal = false"
        >
            <div class="bg-white w-full max-w-3xl rounded-2xl shadow-xl p-6">
                <div class="flex items-start justify-between mb-4">
                    <h3 class="text-lg font-bold">Детали достижения</h3>
                    <button @click="showDetailsModal = false" class="text-gray-400 hover:text-gray-700">✕</button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-sm">
                    <div><b>Мероприятие:</b> {{ selectedAchievement.event_name }}</div>
                    <div><b>Тип:</b> {{ selectedAchievement.event_type?.name || '—' }}</div>
                    <div><b>Уровень:</b> {{ selectedAchievement.event_level?.name || '—' }}</div>
                    <div><b>Дата:</b> {{ selectedAchievement.event_date || selectedAchievement.event_period || '—' }}</div>
                    <div><b>Место проведения:</b> {{ selectedAchievement.event_place || '—' }}</div>
                    <div><b>Ведущий:</b> {{ selectedAchievement.event_host?.full_name || '—' }}</div>
                    <div><b>Результат:</b> {{ selectedAchievement.result_label || '—' }}</div>
                    <div><b>Место:</b> {{ selectedAchievement.result_place || '—' }}</div>
                    <div><b>Разряд:</b> {{ selectedAchievement.result_rank?.name || '—' }}</div>
                    <div><b>ID сертификата:</b> {{ selectedAchievement.certificate_id || '—' }}</div>
                    <div class="md:col-span-2"><b>Описание:</b> {{ selectedAchievement.result_description || '—' }}</div>
                    <div class="md:col-span-2" v-if="selectedAchievement.evidence_file_path">
                        <b>Файл:</b>
                        <a :href="`/storage/${selectedAchievement.evidence_file_path}`" target="_blank"
                            class="text-indigo-600 hover:underline ml-1">
                            Открыть подтверждение
                        </a>
                    </div>
                </div>

                <div class="flex justify-between mt-6">
                    <button
                        @click="selectPrevAchievement"
                        :disabled="selectedAchievementIndex <= 0"
                        class="px-3 py-2 rounded-lg border disabled:opacity-40"
                    >
                        ← Предыдущее
                    </button>
                    <button
                        @click="selectNextAchievement"
                        :disabled="selectedAchievementIndex === -1 || selectedAchievementIndex >= filteredAchievements.length - 1"
                        class="px-3 py-2 rounded-lg border disabled:opacity-40"
                    >
                        Следующее →
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
