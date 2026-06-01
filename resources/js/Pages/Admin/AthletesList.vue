<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DateInput from '@/Components/DateInput.vue';
import Pagination from '@/Components/Pagination.vue';
import { Head, router, Link } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';
import debounce from 'lodash/debounce';

const props = defineProps({
    athletes: Object,
    filters: Object,
    canEditAthlete: { type: Boolean, default: false },
});

const athletesList = computed(() => props.athletes?.data ?? []);
const filtersOpen = ref(false);

const search = ref(props.filters.search || '');
const gender = ref(props.filters.gender || '');
const sortAge = ref(props.filters.sort_age || '');
const startedFrom = ref(props.filters.started_from || '');
const startedTo = ref(props.filters.started_to || '');

const updateSearch = debounce(() => {
    router.get(route('admin.athletes'), {
        search: search.value,
        gender: gender.value,
        sort_age: sortAge.value || null,
        started_from: startedFrom.value || null,
        started_to: startedTo.value || null,
    }, {
        preserveState: true,
        replace: true,
    });
}, 300);

watch([search, gender, sortAge, startedFrom, startedTo], updateSearch);

const getDocClass = (doc) => {
    if (doc.is_expired) return 'bg-red-100 text-red-700 border-red-200';
    if (doc.is_warning) return 'bg-yellow-100 text-yellow-700 border-yellow-200';
    return 'bg-green-100 text-green-700 border-green-200';
};

const docLabel = (type) => {
    const map = { medical: 'Мед', insurance: 'Стр', identity: 'Удл' };
    return map[type] || type.substring(0, 3).toUpperCase();
};

const applyPeriodPreset = (days) => {
    const to = new Date();
    const from = new Date();
    from.setDate(to.getDate() - days);
    startedTo.value = to.toISOString().slice(0, 10);
    startedFrom.value = from.toISOString().slice(0, 10);
};

const resetFilters = () => {
    search.value = '';
    gender.value = '';
    sortAge.value = '';
    startedFrom.value = '';
    startedTo.value = '';
};

const hasActiveFilters = computed(() =>
    !!(search.value || gender.value || sortAge.value || startedFrom.value || startedTo.value),
);
</script>

<template>
    <Head title="Реестр спортсменов" />

    <AuthenticatedLayout>
        <template #header>Реестр спортсменов</template>

        <div class="max-w-7xl mx-auto space-y-4 min-w-0">
            <!-- Фильтры -->
            <div class="bg-white p-4 sm:p-5 rounded-xl shadow-sm border border-slate-100">
                <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                    <p class="text-sm font-medium text-slate-700">Фильтры</p>
                    <div class="flex items-center gap-2">
                        <span class="text-xs text-slate-500">
                            Найдено: <b>{{ athletes.total ?? athletesList.length }}</b>
                        </span>
                        <button
                            type="button"
                            class="lg:hidden text-xs px-2 py-1 rounded border border-slate-200 text-indigo-600"
                            @click="filtersOpen = !filtersOpen"
                        >
                            {{ filtersOpen ? 'Скрыть' : 'Показать' }}
                        </button>
                    </div>
                </div>

                <div
                    class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3"
                    :class="filtersOpen ? '' : 'hidden lg:grid'"
                >
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Поиск по фамилии..."
                        class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 sm:col-span-2 lg:col-span-1"
                    />
                    <select v-model="gender" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500">
                        <option value="">Пол: любой</option>
                        <option value="male">Мужской</option>
                        <option value="female">Женский</option>
                    </select>
                    <select v-model="sortAge" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500">
                        <option value="">Возраст: по умолчанию</option>
                        <option value="asc">Возраст: от младших</option>
                        <option value="desc">Возраст: от старших</option>
                    </select>
                    <DateInput v-model="startedFrom" label="Дата регистрации с" input-class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500" />
                    <DateInput v-model="startedTo" label="Дата регистрации по" input-class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500" />
                    <div class="flex flex-wrap gap-2 sm:col-span-2 lg:col-span-3">
                        <button type="button" @click="applyPeriodPreset(7)" class="flex-1 sm:flex-none px-3 py-2 text-xs rounded-lg border">7 дней</button>
                        <button type="button" @click="applyPeriodPreset(30)" class="flex-1 sm:flex-none px-3 py-2 text-xs rounded-lg border">30 дней</button>
                        <button type="button" @click="applyPeriodPreset(90)" class="flex-1 sm:flex-none px-3 py-2 text-xs rounded-lg border">90 дней</button>
                        <button
                            v-if="hasActiveFilters"
                            type="button"
                            @click="resetFilters"
                            class="w-full sm:w-auto px-3 py-2 text-xs rounded-lg bg-slate-100"
                        >
                            Сбросить
                        </button>
                    </div>
                </div>
            </div>

            <!-- Мобильные карточки -->
            <div class="md:hidden space-y-3">
                <article
                    v-for="athlete in athletesList"
                    :key="athlete.id"
                    class="bg-white rounded-xl border border-slate-100 shadow-sm p-4"
                >
                    <div class="flex gap-3">
                        <img
                            class="h-12 w-12 rounded-full object-cover shrink-0"
                            :src="athlete.photo ? '/storage/' + athlete.photo : `https://ui-avatars.com/api/?name=${athlete.full_name}`"
                            alt=""
                        />
                        <div class="min-w-0 flex-1">
                            <h3 class="font-semibold text-slate-900 break-anywhere leading-snug">{{ athlete.full_name }}</h3>
                            <p v-if="athlete.phone" class="text-sm text-slate-500 mt-0.5">{{ athlete.phone }}</p>
                            <div class="flex flex-wrap items-center gap-2 mt-2">
                                <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-indigo-100 text-indigo-800">
                                    {{ athlete.current_rank }}
                                </span>
                                <span class="text-xs text-slate-600">{{ athlete.age_label }}</span>
                            </div>
                        </div>
                    </div>

                    <dl class="mt-3 grid grid-cols-2 gap-x-3 gap-y-2 text-xs">
                        <div>
                            <dt class="text-slate-400">Дата рождения</dt>
                            <dd class="font-medium text-slate-700">{{ athlete.birth_date || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-400">Начало занятий</dt>
                            <dd class="font-medium text-slate-700">{{ athlete.started_at || '—' }}</dd>
                        </div>
                        <div class="col-span-2">
                            <dt class="text-slate-400 mb-1">Документы</dt>
                            <dd class="flex flex-wrap gap-1">
                                <span
                                    v-for="doc in athlete.documents"
                                    :key="doc.type"
                                    :title="'Действует до ' + doc.expiry_date"
                                    class="px-2 py-0.5 text-[10px] border rounded font-bold"
                                    :class="getDocClass(doc)"
                                >
                                    {{ docLabel(doc.type) }}
                                </span>
                                <span v-if="!athlete.documents.length" class="text-slate-400 italic">Нет данных</span>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-slate-400">Инвентарь</dt>
                            <dd class="font-medium text-slate-700">{{ athlete.inventory_count }} ед.</dd>
                        </div>
                    </dl>

                    <div class="mt-4 flex flex-col gap-2">
                        <Link
                            :href="route('admin.athletes.show', athlete.id)"
                            class="w-full text-center py-2.5 rounded-lg bg-slate-100 text-slate-800 text-sm font-medium"
                        >
                            Просмотр карточки
                        </Link>
                        <Link
                            v-if="canEditAthlete"
                            :href="route('athlete.edit', athlete.id)"
                            class="w-full text-center py-2.5 rounded-lg bg-indigo-600 text-white text-sm font-medium"
                        >
                            Редактировать
                        </Link>
                    </div>
                </article>

                <div v-if="athletesList.length === 0" class="p-8 text-center text-gray-500 italic bg-white rounded-xl border">
                    Ничего не найдено...
                </div>
                <Pagination :links="athletes.links" :meta="athletes" />
            </div>

            <!-- Десктоп: таблица -->
            <div class="hidden md:block bg-white overflow-hidden shadow-sm rounded-xl border border-slate-100 app-table-wrap">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Спортсмен</th>
                            <th class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Возраст</th>
                            <th class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Разряд</th>
                            <th class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Документы</th>
                            <th class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Инвентарь</th>
                            <th class="px-4 lg:px-6 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr v-for="athlete in athletesList" :key="athlete.id" class="hover:bg-gray-50 transition">
                            <td class="px-4 lg:px-6 py-4">
                                <div class="flex items-center min-w-[200px]">
                                    <img
                                        class="h-10 w-10 rounded-full object-cover shrink-0"
                                        :src="athlete.photo ? '/storage/' + athlete.photo : `https://ui-avatars.com/api/?name=${athlete.full_name}`"
                                        alt=""
                                    />
                                    <div class="ml-3 min-w-0">
                                        <div class="text-sm font-medium text-gray-900 truncate">{{ athlete.full_name }}</div>
                                        <div class="text-sm text-gray-500 truncate">{{ athlete.phone }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 lg:px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">{{ athlete.age_label }}</div>
                                <div class="text-xs text-gray-400">{{ athlete.birth_date }}</div>
                                <div class="text-xs text-gray-400">Начал(а): {{ athlete.started_at || '—' }}</div>
                            </td>
                            <td class="px-4 lg:px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-indigo-100 text-indigo-800">
                                    {{ athlete.current_rank }}
                                </span>
                            </td>
                            <td class="px-4 lg:px-6 py-4">
                                <div class="flex flex-wrap gap-1">
                                    <span
                                        v-for="doc in athlete.documents"
                                        :key="doc.type"
                                        :title="'Действует до ' + doc.expiry_date"
                                        class="px-2 py-0.5 text-[10px] border rounded uppercase font-bold"
                                        :class="getDocClass(doc)"
                                    >
                                        {{ doc.type.substring(0, 3) }}
                                    </span>
                                    <span v-if="!athlete.documents.length" class="text-xs text-gray-300 italic">Нет данных</span>
                                </div>
                            </td>
                            <td class="px-4 lg:px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ athlete.inventory_count }} ед.
                            </td>
                            <td class="px-4 lg:px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <Link :href="route('admin.athletes.show', athlete.id)" class="text-slate-600 hover:text-slate-900 mr-3">Просмотр</Link>
                                <Link v-if="canEditAthlete" :href="route('athlete.edit', athlete.id)" class="text-indigo-600 hover:text-indigo-900 mr-3">Редактировать</Link>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div v-if="athletesList.length === 0" class="p-8 text-center text-gray-500 italic">
                    Ничего не найдено...
                </div>
                <Pagination :links="athletes.links" :meta="athletes" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
