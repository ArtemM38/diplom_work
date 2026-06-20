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
</script>

<template>
    <Head title="Мед. справки и страховые полисы" />
    <AuthenticatedLayout>
        <template #header>Контроль мед. справок и страховых полисов</template>

        <div class="max-w-6xl mx-auto space-y-6">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <button
                    type="button"
                    @click="filter = 'expired'"
                    class="p-4 rounded-xl border text-left transition"
                    :class="filter === 'expired' ? 'border-red-400 bg-red-50' : 'border-slate-200 bg-white'"
                >
                    <p class="text-2xl font-bold text-red-700">{{ summary.expired }}</p>
                    <p class="text-sm text-slate-600">Просрочено</p>
                </button>
                <button
                    type="button"
                    @click="filter = 'warning'"
                    class="p-4 rounded-xl border text-left transition"
                    :class="filter === 'warning' ? 'border-amber-400 bg-amber-50' : 'border-slate-200 bg-white'"
                >
                    <p class="text-2xl font-bold text-amber-700">{{ summary.warning }}</p>
                    <p class="text-sm text-slate-600">Истекает в течение 3 дней</p>
                </button>
                <button
                    type="button"
                    @click="filter = 'ok'"
                    class="p-4 rounded-xl border text-left transition"
                    :class="filter === 'ok' ? 'border-emerald-400 bg-emerald-50' : 'border-slate-200 bg-white'"
                >
                    <p class="text-2xl font-bold text-emerald-700">{{ summary.ok }}</p>
                    <p class="text-sm text-slate-600">В порядке</p>
                </button>
            </div>

            <div class="bg-white p-4 rounded-xl border border-slate-100 flex flex-wrap gap-3 items-center">
                <input v-model="search" type="text" placeholder="Поиск по фамилии..." class="border-gray-300 rounded-lg flex-1 min-w-[200px]" />
                <select v-model="docType" class="border-gray-300 rounded-lg min-w-[220px]">
                    <option value="all">Все документы</option>
                    <option value="medical">Медицинская справка</option>
                    <option value="insurance">Страховой полис</option>
                </select>
                <button
                    type="button"
                    @click="filter = 'all'; docType = 'all'"
                    class="text-sm text-indigo-600 font-medium"
                >
                    Сбросить фильтры
                </button>
            </div>

            <div class="bg-white rounded-xl border border-slate-100 overflow-hidden app-table-wrap">
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
                            <td class="px-4 py-3 font-medium">{{ doc.full_name }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ doc.type_label }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ doc.phone || '—' }}</td>
                            <td class="px-4 py-3">{{ doc.issue_date || '—' }}</td>
                            <td class="px-4 py-3">{{ doc.expiry_date }}</td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-1 rounded-lg text-xs font-semibold border" :class="statusClass(doc.status)">
                                    {{ statusLabel(doc.status, doc.days_left) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
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
