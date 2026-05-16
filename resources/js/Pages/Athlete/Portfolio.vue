<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    athleteName: String,
    achievements: Array,
    stats: Object,
});
</script>

<template>
    <Head title="Моё портфолио" />
    <AuthenticatedLayout>
        <template #header>Моё портфолио</template>

        <div class="max-w-4xl mx-auto space-y-6">
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6">
                <h3 class="text-lg font-bold text-slate-900">{{ athleteName }}</h3>
                <p class="text-sm text-slate-500 mt-1">Достижения и результаты соревнований</p>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mt-4">
                    <div class="rounded-xl bg-indigo-50 p-3 text-center">
                        <p class="text-2xl font-bold text-indigo-700">{{ stats?.total ?? 0 }}</p>
                        <p class="text-xs text-indigo-600">Всего</p>
                    </div>
                    <div class="rounded-xl bg-amber-50 p-3 text-center">
                        <p class="text-2xl font-bold text-amber-700">{{ stats?.places_1 ?? 0 }}</p>
                        <p class="text-xs text-amber-600">1 место</p>
                    </div>
                    <div class="rounded-xl bg-slate-100 p-3 text-center">
                        <p class="text-2xl font-bold text-slate-700">{{ stats?.places_2 ?? 0 }}</p>
                        <p class="text-xs text-slate-600">2 место</p>
                    </div>
                    <div class="rounded-xl bg-orange-50 p-3 text-center">
                        <p class="text-2xl font-bold text-orange-700">{{ stats?.places_3 ?? 0 }}</p>
                        <p class="text-xs text-orange-600">3 место</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="text-left p-3">Мероприятие</th>
                            <th class="text-left p-3">Дата</th>
                            <th class="text-left p-3">Тип / уровень</th>
                            <th class="text-left p-3">Результат</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in achievements" :key="item.id" class="border-t border-slate-100">
                            <td class="p-3 font-medium text-slate-800">{{ item.event_name }}</td>
                            <td class="p-3 text-slate-600">{{ item.event_date || '—' }}</td>
                            <td class="p-3 text-slate-600">{{ item.event_type || '—' }} · {{ item.event_level || '—' }}</td>
                            <td class="p-3">
                                <span v-if="item.result_place" class="text-indigo-700 font-semibold">{{ item.result_place }} место</span>
                                <span v-else class="text-slate-500">—</span>
                                <span v-if="item.result_rank" class="block text-xs text-slate-500">{{ item.result_rank }}</span>
                            </td>
                        </tr>
                        <tr v-if="!achievements?.length">
                            <td colspan="4" class="p-8 text-center text-slate-400">Пока нет записей в портфолио</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
