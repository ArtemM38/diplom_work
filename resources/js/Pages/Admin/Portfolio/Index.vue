<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    athletes: Array,
    eventTypes: Array,
    eventLevels: Array,
    eventHosts: Array,
    ranks: Array,
    achievements: Array,
    ratings: Array,
    athleteReport: Object,
    summaryReport: Object,
    filters: Object,
});

const hostForm = useForm({
    id: null,
    full_name: '',
    rank: '',
    city: '',
    contacts: '',
    extra_info: '',
});

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

const hostEditId = computed(() => hostForm.id ?? null);
const achievementEditId = computed(() => achievementForm.id ?? null);

const filterAthleteId = computed({
    get: () => props.filters?.athlete_id ?? '',
    set: (value) => {
        router.get(route('admin.portfolio'), { athlete_id: value || null }, { preserveState: true, replace: true });
    }
});

const saveHost = () => {
    if (hostForm.id) {
        hostForm.patch(route('admin.portfolio.hosts.update', hostForm.id), {
            onSuccess: () => hostForm.reset(),
        });
        return;
    }

    hostForm.post(route('admin.portfolio.hosts.store'), {
        onSuccess: () => hostForm.reset(),
    });
};

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
};

const editHost = (host) => {
    hostForm.id = host.id;
    hostForm.full_name = host.full_name ?? '';
    hostForm.rank = host.rank ?? '';
    hostForm.city = host.city ?? '';
    hostForm.contacts = host.contacts ?? '';
    hostForm.extra_info = host.extra_info ?? '';
};

const removeHost = (hostId) => {
    if (!confirm('Удалить ведущего?')) return;
    hostForm.delete(route('admin.portfolio.hosts.destroy', hostId));
};

const editAchievement = (item) => {
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
</script>

<template>
    <Head title="Портфолио и мероприятия" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Портфолио и мероприятия</h2>
        </template>

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
            <div class="xl:col-span-1 space-y-6">
                <div class="bg-white p-6 rounded-xl shadow-sm">
                    <h3 class="font-bold mb-4">{{ hostEditId ? 'Редактировать ведущего' : 'Добавить ведущего' }}</h3>
                    <form @submit.prevent="saveHost" class="space-y-3">
                        <input v-model="hostForm.full_name" placeholder="ФИО ведущего" class="w-full border-gray-300 rounded-lg" required>
                        <input v-model="hostForm.rank" placeholder="Спорт. разряд" class="w-full border-gray-300 rounded-lg">
                        <input v-model="hostForm.city" placeholder="Город" class="w-full border-gray-300 rounded-lg">
                        <input v-model="hostForm.contacts" placeholder="Контакты" class="w-full border-gray-300 rounded-lg">
                        <textarea v-model="hostForm.extra_info" placeholder="Доп. информация" class="w-full border-gray-300 rounded-lg" rows="2"></textarea>
                        <button class="w-full bg-indigo-600 text-white py-2 rounded-lg font-bold">
                            {{ hostEditId ? 'Обновить ведущего' : 'Сохранить ведущего' }}
                        </button>
                        <button v-if="hostEditId" type="button" @click="hostForm.reset()" class="w-full bg-gray-100 text-gray-700 py-2 rounded-lg font-bold">
                            Отмена редактирования
                        </button>
                    </form>
                </div>

                <div class="bg-white p-6 rounded-xl shadow-sm">
                    <h3 class="font-bold mb-4">{{ achievementEditId ? 'Редактирование достижения' : 'Новое достижение' }}</h3>
                    <form @submit.prevent="saveAchievement" class="space-y-3">
                        <select v-model="achievementForm.athlete_id" class="w-full border-gray-300 rounded-lg" required>
                            <option value="">Спортсмен</option>
                            <option v-for="a in athletes" :key="a.id" :value="a.id">
                                {{ a.last_name_nom }} {{ a.first_name_nom }} {{ a.middle_name_nom }}
                            </option>
                        </select>
                        <input v-model="achievementForm.event_name" placeholder="Название мероприятия" class="w-full border-gray-300 rounded-lg" required>
                        <select v-model="achievementForm.event_type_id" class="w-full border-gray-300 rounded-lg" required>
                            <option value="">Тип мероприятия</option>
                            <option v-for="t in eventTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
                        </select>
                        <div class="grid grid-cols-2 gap-2">
                            <input v-model="achievementForm.event_place" placeholder="Место проведения" class="w-full border-gray-300 rounded-lg">
                            <input v-model="achievementForm.event_date" type="date" class="w-full border-gray-300 rounded-lg">
                        </div>
                        <input v-model="achievementForm.event_period" placeholder="Период (если нужно)" class="w-full border-gray-300 rounded-lg">
                        <select v-model="achievementForm.event_level_id" class="w-full border-gray-300 rounded-lg">
                            <option value="">Уровень</option>
                            <option v-for="l in eventLevels" :key="l.id" :value="l.id">{{ l.name }}</option>
                        </select>
                        <select v-model="achievementForm.event_host_id" class="w-full border-gray-300 rounded-lg">
                            <option value="">Ведущий</option>
                            <option v-for="h in eventHosts" :key="h.id" :value="h.id">{{ h.full_name }}</option>
                        </select>
                        <div class="grid grid-cols-2 gap-2">
                            <input v-model="achievementForm.result_label" placeholder="Результат (текст)" class="w-full border-gray-300 rounded-lg">
                            <input v-model="achievementForm.result_place" type="number" min="1" max="3" placeholder="Место 1-3" class="w-full border-gray-300 rounded-lg">
                        </div>
                        <select v-model="achievementForm.result_rank_id" class="w-full border-gray-300 rounded-lg">
                            <option value="">Присвоенный разряд</option>
                            <option v-for="r in ranks" :key="r.id" :value="r.id">{{ r.name }}</option>
                        </select>
                        <input v-model="achievementForm.certificate_id" placeholder="ID сертификата" class="w-full border-gray-300 rounded-lg">
                        <textarea v-model="achievementForm.result_description" placeholder="Описание результата" class="w-full border-gray-300 rounded-lg" rows="2"></textarea>
                        <input type="file" @input="achievementForm.evidence_file = $event.target.files[0]" class="w-full text-sm">
                        <button class="w-full bg-green-600 text-white py-2 rounded-lg font-bold">
                            {{ achievementEditId ? 'Обновить достижение' : 'Сохранить достижение' }}
                        </button>
                        <button v-if="achievementEditId" type="button" @click="resetAchievementForm" class="w-full bg-gray-100 text-gray-700 py-2 rounded-lg font-bold">
                            Отмена редактирования
                        </button>
                    </form>
                </div>

                <div class="bg-white p-6 rounded-xl shadow-sm">
                    <h3 class="font-bold mb-4">Ведущие мероприятий</h3>
                    <div class="space-y-2 max-h-64 overflow-y-auto">
                        <div v-for="h in eventHosts" :key="h.id" class="border rounded-lg p-3">
                            <div class="font-medium">{{ h.full_name }}</div>
                            <div class="text-xs text-gray-500">{{ h.city || '—' }} / {{ h.rank || '—' }}</div>
                            <div class="mt-2 flex gap-2">
                                <button @click="editHost(h)" class="text-xs px-2 py-1 rounded bg-indigo-100 text-indigo-700">Изменить</button>
                                <button @click="removeHost(h.id)" class="text-xs px-2 py-1 rounded bg-red-100 text-red-700">Удалить</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="xl:col-span-2 bg-white p-6 rounded-xl shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-bold">Сводная таблица достижений</h3>
                    <div class="flex items-center gap-2">
                        <select v-model="filterAthleteId" class="border-gray-300 rounded-lg">
                            <option value="">Все спортсмены</option>
                            <option v-for="a in athletes" :key="a.id" :value="a.id">
                                {{ a.last_name_nom }} {{ a.first_name_nom }}
                            </option>
                        </select>
                        <a :href="route('admin.portfolio.export.summary')" class="text-xs px-3 py-2 rounded bg-gray-100 text-gray-700">
                            CSV (сводный)
                        </a>
                        <a :href="route('admin.portfolio.export.summary.pdf')" class="text-xs px-3 py-2 rounded bg-gray-100 text-gray-700">
                            PDF (сводный)
                        </a>
                        <a
                            :href="filterAthleteId ? route('admin.portfolio.export.athlete', { athlete_id: filterAthleteId }) : '#'"
                            :class="[
                                'text-xs px-3 py-2 rounded',
                                filterAthleteId ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-50 text-gray-400 pointer-events-none'
                            ]"
                        >
                            CSV (спортсмен)
                        </a>
                        <a
                            :href="filterAthleteId ? route('admin.portfolio.export.athlete.pdf', { athlete_id: filterAthleteId }) : '#'"
                            :class="[
                                'text-xs px-3 py-2 rounded',
                                filterAthleteId ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-50 text-gray-400 pointer-events-none'
                            ]"
                        >
                            PDF (спортсмен)
                        </a>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs uppercase text-gray-500">Спортсмен</th>
                                <th class="px-3 py-2 text-left text-xs uppercase text-gray-500">Мероприятие</th>
                                <th class="px-3 py-2 text-left text-xs uppercase text-gray-500">Тип/Уровень</th>
                                <th class="px-3 py-2 text-left text-xs uppercase text-gray-500">Дата</th>
                                <th class="px-3 py-2 text-left text-xs uppercase text-gray-500">Результат</th>
                                <th class="px-3 py-2 text-left text-xs uppercase text-gray-500">Действия</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="item in achievements" :key="item.id">
                                <td class="px-3 py-2 text-sm">{{ item.athlete?.last_name_nom }} {{ item.athlete?.first_name_nom }}</td>
                                <td class="px-3 py-2 text-sm">
                                    <div class="font-medium">{{ item.event_name }}</div>
                                    <div class="text-xs text-gray-500">{{ item.event_place || '—' }}</div>
                                </td>
                                <td class="px-3 py-2 text-sm">
                                    <div>{{ item.event_type?.name }}</div>
                                    <div class="text-xs text-gray-500">{{ item.event_level?.name || '—' }}</div>
                                </td>
                                <td class="px-3 py-2 text-sm">{{ item.event_date || item.event_period || '—' }}</td>
                                <td class="px-3 py-2 text-sm">
                                    <div>{{ item.result_label || '—' }}</div>
                                    <div class="text-xs text-gray-500">Место: {{ item.result_place || '—' }}</div>
                                </td>
                                <td class="px-3 py-2 text-sm">
                                    <div class="flex gap-2">
                                        <button @click="editAchievement(item)" class="text-xs px-2 py-1 rounded bg-indigo-100 text-indigo-700">Изменить</button>
                                        <button @click="removeAchievement(item.id)" class="text-xs px-2 py-1 rounded bg-red-100 text-red-700">Удалить</button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="achievements.length === 0">
                                <td colspan="6" class="px-3 py-8 text-center text-sm text-gray-400">Пока нет данных о достижениях</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-8 grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="border rounded-xl p-4">
                        <h4 class="font-bold mb-3">Отчет по выбранному спортсмену</h4>
                        <div v-if="athleteReport" class="space-y-1 text-sm">
                            <div>Всего достижений: <b>{{ athleteReport.total_achievements }}</b></div>
                            <div>1 мест: <b>{{ athleteReport.places_1 }}</b></div>
                            <div>2 мест: <b>{{ athleteReport.places_2 }}</b></div>
                            <div>3 мест: <b>{{ athleteReport.places_3 }}</b></div>
                        </div>
                        <div v-else class="text-sm text-gray-500">Выбери спортсмена в фильтре сверху.</div>
                    </div>

                    <div class="border rounded-xl p-4">
                        <h4 class="font-bold mb-3">Сводный отчет по всем</h4>
                        <div class="space-y-1 text-sm">
                            <div>Всего достижений: <b>{{ summaryReport.total_achievements }}</b></div>
                            <div>Уникальных спортсменов: <b>{{ summaryReport.unique_athletes }}</b></div>
                            <div>1 мест: <b>{{ summaryReport.places_1 }}</b></div>
                            <div>2 мест: <b>{{ summaryReport.places_2 }}</b></div>
                            <div>3 мест: <b>{{ summaryReport.places_3 }}</b></div>
                        </div>
                    </div>
                </div>

                <div class="mt-8">
                    <h4 class="font-bold mb-3">Рейтинг спортсменов (по баллам)</h4>
                    <div class="overflow-x-auto border rounded-xl">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs uppercase text-gray-500">Спортсмен</th>
                                    <th class="px-3 py-2 text-left text-xs uppercase text-gray-500">Баллы</th>
                                    <th class="px-3 py-2 text-left text-xs uppercase text-gray-500">Достижения</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="row in ratings" :key="row.athlete_id">
                                    <td class="px-3 py-2 text-sm">{{ row.full_name }}</td>
                                    <td class="px-3 py-2 text-sm font-semibold">{{ row.points }}</td>
                                    <td class="px-3 py-2 text-sm">{{ row.achievements_count }}</td>
                                </tr>
                                <tr v-if="ratings.length === 0">
                                    <td colspan="3" class="px-3 py-6 text-center text-sm text-gray-400">Нет данных для рейтинга</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
