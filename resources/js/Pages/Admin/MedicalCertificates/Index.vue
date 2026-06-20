<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import debounce from 'lodash/debounce';

const props = defineProps({
    documents: Array,
    summary: Object,
    filters: Object,
});

const search = ref(props.filters?.search || '');
const filter = ref(props.filters?.filter || 'all');
const docType = ref(props.filters?.type || 'all');

const reload = () => {
    router.get(route('admin.medical-certificates'), {
        search: search.value || null,
        filter: filter.value !== 'all' ? filter.value : null,
        type: docType.value !== 'all' ? docType.value : null,
    }, { preserveState: true, replace: true });
};

watch(search, debounce(reload, 300));
watch(filter, reload);
watch(docType, reload);

const statusClass = (status) => {
    if (status === 'expired') return 'bg-red-100 text-red-800 border-red-200';
    if (status === 'warning') return 'bg-amber-100 text-amber-800 border-amber-200';
    return 'bg-emerald-100 text-emerald-800 border-emerald-200';
};

const statusLabel = (status, daysLeft) => {
    if (status === 'expired') return `Просрочена (${Math.abs(daysLeft)} дн. назад)`;
    if (status === 'warning') return `Истекает через ${daysLeft} дн.`;
    return 'Действует';
};

const statusLabelShort = (status, daysLeft) => {
    if (status === 'expired') return `Просрочена`;
    if (status === 'warning') return `${daysLeft} дн.`;
    return 'OK';
};
</script>

<template>
    <Head title="Мед. справки и страховые полисы" />
    <AuthenticatedLayout>
        <template #header>
            <span class="break-anywhere">Контроль мед. справок и страховых полисов</span>
        </template>

        <div class="max-w-6xl mx-auto space-y-4 sm:space-y-6 min-w-0">
            <div class="grid grid-cols-3 gap-2 sm:gap-4">
                <button
                    type="button"
                    @click="filter = 'expired'"
                    class="p-3 sm:p-4 rounded-xl border text-left transition"
                    :class="filter === 'expired' ? 'border-red-400 bg-red-50' : 'border-slate-200 bg-white'"
                >
                    <p class="text-xl sm:text-2xl font-bold text-red-700">{{ summary.expired }}</p>
                    <p class="text-xs sm:text-sm text-slate-600">Просрочено</p>
                </button>
                <button
                    type="button"
                    @click="filter = 'warning'"
                    class="p-3 sm:p-4 rounded-xl border text-left transition"
                    :class="filter === 'warning' ? 'border-amber-400 bg-amber-50' : 'border-slate-200 bg-white'"
                >
                    <p class="text-xl sm:text-2xl font-bold text-amber-700">{{ summary.warning }}</p>
                    <p class="text-xs sm:text-sm text-slate-600">Истекает ≤ 3 дн.</p>
                </button>
                <button
                    type="button"
                    @click="filter = 'ok'"
                    class="p-3 sm:p-4 rounded-xl border text-left transition"
                    :class="filter === 'ok' ? 'border-emerald-400 bg-emerald-50' : 'border-slate-200 bg-white'"
                >
                    <p class="text-xl sm:text-2xl font-bold text-emerald-700">{{ summary.ok }}</p>
                    <p class="text-xs sm:text-sm text-slate-600">В порядке</p>
                </button>
            </div>

            <div class="bg-white p-4 rounded-xl border border-slate-100 space-y-3 sm:space-y-0 sm:flex sm:flex-wrap sm:gap-3 sm:items-center">
                <input
                    v-model="search"
                    type="text"
                    placeholder="Поиск по фамилии..."
                    class="border-gray-300 rounded-lg w-full sm:flex-1 sm:min-w-[180px] min-h-[2.75rem]"
                />
                <select v-model="docType" class="border-gray-300 rounded-lg w-full sm:w-auto sm:min-w-[200px] min-h-[2.75rem]">
                    <option value="all">Все документы</option>
                    <option value="medical">Медицинская справка</option>
                    <option value="insurance">Страховой полис</option>
                </select>
                <button
                    type="button"
                    @click="filter = 'all'; docType = 'all'"
                    class="w-full sm:w-auto text-sm text-indigo-600 font-medium py-2 sm:py-0"
                >
                    Сбросить фильтры
                </button>
            </div>

            <!-- Мобильные карточки -->
            <div class="md:hidden space-y-3">
                <article
                    v-for="doc in documents"
                    :key="doc.id"
                    class="bg-white rounded-xl border border-slate-100 p-4 shadow-sm"
                >
                    <div class="flex items-start justify-between gap-2 mb-2">
                        <h3 class="font-semibold text-slate-900 break-anywhere flex-1 min-w-0">{{ doc.full_name }}</h3>
                        <span
                            class="shrink-0 text-[10px] px-2 py-0.5 rounded-full border font-semibold"
                            :class="statusClass(doc.status)"
                            :title="statusLabel(doc.status, doc.days_left)"
                        >
                            {{ statusLabelShort(doc.status, doc.days_left) }}
                        </span>
                    </div>
                    <dl class="grid grid-cols-2 gap-x-3 gap-y-2 text-xs">
                        <div class="col-span-2">
                            <dt class="text-slate-400">Документ</dt>
                            <dd class="font-medium text-slate-700">{{ doc.type_label }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-400">Телефон</dt>
                            <dd class="font-medium text-slate-700 break-anywhere">{{ doc.phone || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-400">Выдана</dt>
                            <dd class="font-medium text-slate-700">{{ doc.issue_date || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-400">Действует до</dt>
                            <dd class="font-medium text-slate-700">{{ doc.expiry_date }}</dd>
                        </div>
                        <div class="col-span-2">
                            <dt class="text-slate-400">Статус</dt>
                            <dd class="font-medium text-slate-700">{{ statusLabel(doc.status, doc.days_left) }}</dd>
                        </div>
                    </dl>
                    <Link
                        :href="route('admin.athletes.show', doc.athlete_id)"
                        class="mt-3 block w-full text-center py-2.5 rounded-lg bg-indigo-50 text-indigo-700 text-sm font-medium"
                    >
                        Карточка спортсмена
                    </Link>
                </article>
                <div
                    v-if="!documents?.length"
                    class="p-8 text-center text-slate-400 bg-white rounded-xl border border-slate-100"
                >
                    Нет записей по выбранному фильтру
                </div>
            </div>

            <!-- Десктоп: таблица -->
            <div class="hidden md:block bg-white rounded-xl border border-slate-100 overflow-hidden app-table-wrap">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="px-4 py-3 text-left">Спортсмен</th>
                            <th class="px-4 py-3 text-left">Документ</th>
                            <th class="px-4 py-3 text-left">Телефон</th>
                            <th class="px-4 py-3 text-left">Выдана</th>
                            <th class="px-4 py-3 text-left">Действует до</th>
                            <th class="px-4 py-3 text-left">Статус</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="doc in documents" :key="doc.id" class="border-t border-slate-100 hover:bg-slate-50">
                            <td class="px-4 py-3 font-medium break-anywhere max-w-[180px]">{{ doc.full_name }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ doc.type_label }}</td>
                            <td class="px-4 py-3 text-slate-600 whitespace-nowrap">{{ doc.phone || '—' }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">{{ doc.issue_date || '—' }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">{{ doc.expiry_date }}</td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-1 rounded-lg text-xs font-semibold border whitespace-nowrap" :class="statusClass(doc.status)">
                                    {{ statusLabel(doc.status, doc.days_left) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <Link :href="route('admin.athletes.show', doc.athlete_id)" class="text-indigo-600 hover:underline">Карточка</Link>
                            </td>
                        </tr>
                        <tr v-if="!documents?.length">
                            <td colspan="7" class="px-4 py-8 text-center text-slate-400">Нет записей по выбранному фильтру</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
