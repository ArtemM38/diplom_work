<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    athlete: Object,
    userRole: String,
    athleteSchedule: Array,
    athleteGroups: Array,
    scheduleFilters: Object,
});

const isExpiring = (date) => {
    if (!date) return false;
    const expiry = new Date(date);
    const today = new Date();
    const diffTime = expiry - today;
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    return diffDays < 14;
};

const from = ref(props.scheduleFilters?.from || '');
const to = ref(props.scheduleFilters?.to || '');
const groupId = ref(props.scheduleFilters?.group_id || '');

watch([from, to, groupId], () => {
    if (props.userRole !== 'athlete') return;
    router.get(route('dashboard'), { from: from.value || null, to: to.value || null, group_id: groupId.value || null }, { preserveState: true, replace: true });
});
</script>

<template>
    <Head title="Личный кабинет" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Мой профиль</h2>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 flex items-center gap-6">
                    <img :src="athlete?.photo ? '/storage/' + athlete.photo : 'https://ui-avatars.com/api/?name=' + athlete?.first_name_nom"
                        class="w-24 h-24 rounded-full object-cover border-4 border-indigo-100">
                    <div>
                        <h3 class="text-2xl font-bold">{{ athlete?.last_name_nom }} {{ athlete?.first_name_nom }}</h3>
                        <p class="text-gray-500">Дата рождения: {{ athlete?.birth_date || '—' }}</p>
                        <div class="mt-2 inline-block bg-indigo-600 text-white text-xs px-3 py-1 rounded-full">
                            {{ athlete?.rank_histories?.[0]?.rank?.name || 'Без разряда' }}
                        </div>
                        <div class="mt-3" v-if="athlete?.id">
                            <Link :href="route('athlete.edit', athlete.id)" class="text-indigo-600 hover:underline text-sm">Редактировать профиль</Link>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                        <h4 class="font-bold mb-4 border-b pb-2">Статус документов</h4>
                        <div v-for="doc in athlete?.documents" :key="doc.id" class="flex justify-between items-center mb-2">
                            <span class="capitalize">{{ doc.type === 'medical' ? 'Медсправка' : 'Документ' }}</span>
                            <span :class="isExpiring(doc.expiry_date) ? 'text-red-600 font-bold' : 'text-green-600'">до {{ doc.expiry_date }}</span>
                        </div>
                        <div v-if="!athlete?.documents?.length" class="text-gray-400 text-sm">Документы не загружены</div>
                    </div>
                    <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                        <h4 class="font-bold mb-4 border-b pb-2">Информация</h4>
                        <div class="text-sm text-gray-700 space-y-1">
                            <div><b>Телефон:</b> {{ athlete?.phone || '—' }}</div>
                            <div><b>Адрес:</b> {{ athlete?.registration_address || '—' }}</div>
                            <div><b>Школа:</b> {{ athlete?.school_name || '—' }}</div>
                        </div>
                    </div>
                </div>

                <div v-if="userRole === 'athlete'" class="bg-white p-6 shadow-sm sm:rounded-lg">
                    <h4 class="font-bold mb-4 border-b pb-2">Мое расписание</h4>
                    <div class="grid md:grid-cols-3 gap-3 mb-4">
                        <input v-model="from" type="date" class="border-gray-300 rounded-lg" />
                        <input v-model="to" type="date" class="border-gray-300 rounded-lg" />
                        <select v-model="groupId" class="border-gray-300 rounded-lg">
                            <option value="">Все группы</option>
                            <option v-for="g in athleteGroups" :key="g.id" :value="g.id">{{ g.name }}</option>
                        </select>
                    </div>
                    <div v-if="!athleteSchedule?.length" class="text-sm text-gray-400">Тренировок по фильтру не найдено</div>
                    <div v-else class="space-y-2">
                        <div v-for="item in athleteSchedule" :key="item.id" class="border rounded-lg p-3 text-sm">
                            <div class="font-semibold">{{ item.lesson_date }} {{ item.start_time?.substring(0,5) }}-{{ item.end_time?.substring(0,5) }}</div>
                            <div class="text-gray-600">{{ item.group }} | {{ item.location }} | Тренер: {{ item.coach }}</div>
                        </div>
                    </div>
                </div>

                <div v-if="userRole === 'athlete'" class="bg-white p-6 shadow-sm sm:rounded-lg">
                    <h4 class="font-bold mb-4 border-b pb-2">Типовые заявления и согласия</h4>
                    <div class="grid md:grid-cols-2 gap-3">
                        <div v-for="n in [1,2,3,4]" :key="n" class="border rounded-lg p-3 flex items-center justify-between">
                            <span>Приложение {{ n }}</span>
                            <div class="flex gap-2">
                                <a :href="route('athlete.documents.pdf', n)" class="text-indigo-600 hover:underline text-sm">PDF</a>
                                <a :href="route('athlete.documents.word', n)" class="text-indigo-600 hover:underline text-sm">Word</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>