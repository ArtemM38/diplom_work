<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    ranks: Array,
    groups: Array,
    filters: Object,
});

const dateFrom = ref(props.filters?.date_from || '');
const dateTo = ref(props.filters?.date_to || '');
const rankId = ref(props.filters?.rank_id || '');
const groupId = ref(props.filters?.group_id || '');

const query = (extra = {}) => {
    const params = {
        date_from: dateFrom.value,
        date_to: dateTo.value,
        ...extra,
    };
    if (rankId.value) params.rank_id = rankId.value;
    if (groupId.value) params.group_id = groupId.value;
    return params;
};

const download = (routeName, format) => {
    const params = query({ format });
    const qs = new URLSearchParams(params).toString();
    window.location.href = `${route(routeName)}?${qs}`;
};
</script>

<template>
    <Head title="Отчёты" />
    <AuthenticatedLayout>
        <template #header>Отчёты</template>

        <div class="max-w-4xl space-y-6">
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 sm:p-6 space-y-4">
                <h3 class="font-bold text-slate-800">Параметры периода</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs text-slate-500 uppercase font-medium">С</label>
                        <input v-model="dateFrom" type="date" class="w-full mt-1 border-slate-300 rounded-xl" />
                    </div>
                    <div>
                        <label class="text-xs text-slate-500 uppercase font-medium">По</label>
                        <input v-model="dateTo" type="date" class="w-full mt-1 border-slate-300 rounded-xl" />
                    </div>
                    <div>
                        <label class="text-xs text-slate-500 uppercase font-medium">Разряд (текущий)</label>
                        <select v-model="rankId" class="w-full mt-1 border-slate-300 rounded-xl">
                            <option value="">Все</option>
                            <option v-for="r in ranks" :key="r.id" :value="r.id">{{ r.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-slate-500 uppercase font-medium">Группа</label>
                        <select v-model="groupId" class="w-full mt-1 border-slate-300 rounded-xl">
                            <option value="">Все</option>
                            <option v-for="g in groups" :key="g.id" :value="g.id">{{ g.name }}</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 sm:p-6">
                    <h3 class="font-bold text-slate-800 mb-2">Спортсмены</h3>
                    <p class="text-sm text-slate-500 mb-4">
                        Участие в мероприятиях за период, разряды и группы. Фильтры разряда и группы применяются к списку спортсменов.
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <button
                            type="button"
                            class="px-4 py-2 rounded-xl bg-emerald-600 text-white text-sm font-medium hover:bg-emerald-700"
                            @click="download('admin.reports.athletes', 'csv')"
                        >
                            CSV
                        </button>
                        <button
                            type="button"
                            class="px-4 py-2 rounded-xl bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700"
                            @click="download('admin.reports.athletes', 'pdf')"
                        >
                            PDF
                        </button>
                    </div>
                </div>

                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 sm:p-6">
                    <h3 class="font-bold text-slate-800 mb-2">Мероприятия</h3>
                    <p class="text-sm text-slate-500 mb-4">
                        Все мероприятия за период с участниками и результатами.
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <button
                            type="button"
                            class="px-4 py-2 rounded-xl bg-emerald-600 text-white text-sm font-medium hover:bg-emerald-700"
                            @click="download('admin.reports.events', 'csv')"
                        >
                            CSV
                        </button>
                        <button
                            type="button"
                            class="px-4 py-2 rounded-xl bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700"
                            @click="download('admin.reports.events', 'pdf')"
                        >
                            PDF
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
