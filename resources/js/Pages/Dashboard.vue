<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DateInput from '@/Components/DateInput.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';
import { storageUrl } from '@/utils/storageUrl';

const props = defineProps({
    athlete: Object,
    guardian: Object,
    guardianAthletes: Array,
    userRole: String,
    userRoles: Array,
    athleteSchedule: Array,
    athleteGroups: Array,
    athleteGuardians: Array,
    scheduleFilters: Object,
});

const isAthlete = computed(() => props.userRoles?.includes('athlete') || props.userRole === 'athlete');
const isGuardian = computed(() => props.userRoles?.includes('guardian') || props.userRole === 'guardian');

const isExpiring = (date) => {
    if (!date) return false;
    const expiry = new Date(date);
    const today = new Date();
    const diffDays = Math.ceil((expiry - today) / (1000 * 60 * 60 * 24));
    return diffDays < 14;
};

const childFullName = (child) =>
    `${child.last_name_nom} ${child.first_name_nom} ${child.middle_name_nom || ''}`.trim();

const from = ref(props.scheduleFilters?.from || '');
const to = ref(props.scheduleFilters?.to || '');
const groupId = ref(props.scheduleFilters?.group_id || '');

watch([from, to, groupId], () => {
    if (!isAthlete.value) return;
    router.get(
        route('dashboard'),
        { from: from.value || null, to: to.value || null, group_id: groupId.value || null },
        { preserveState: true, replace: true },
    );
});
</script>

<template>
    <Head title="Личный кабинет" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ isGuardian ? 'Мой ребёнок' : 'Мой профиль' }}
            </h2>
        </template>

        <div class="py-6 max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <!-- Guardian block -->
            <template v-if="isGuardian">
                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
                    <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
                        <div>
                            <h3 class="text-lg font-bold text-slate-900">Ваши данные</h3>
                            <p class="text-sm text-slate-500">{{ guardian?.full_name }} · {{ guardian?.relation }}</p>
                            <p class="text-sm text-slate-600">{{ guardian?.phone }}</p>
                        </div>
                        <Link :href="route('profile.edit')" class="text-indigo-600 text-sm font-medium hover:underline">Редактировать</Link>
                    </div>
                </div>

                <div v-if="!guardianAthletes?.length" class="bg-amber-50 border border-amber-100 rounded-2xl p-6 text-sm text-amber-900">
                    <p class="font-semibold">Спортсмен ещё не привязан</p>
                    <p class="mt-1 text-amber-800">
                        Обратитесь к администратору клуба — он привяжет ваш профиль к карточке ребёнка после регистрации спортсмена.
                    </p>
                </div>

                <div v-for="child in guardianAthletes" :key="child.id" class="bg-white overflow-hidden shadow-sm rounded-2xl border border-slate-100">
                    <div class="p-6 flex flex-col md:flex-row md:items-center gap-6">
                        <div class="w-24 h-24 rounded-2xl bg-indigo-100 flex items-center justify-center text-2xl font-bold text-indigo-700 overflow-hidden shrink-0">
                            <img v-if="child.photo" :src="storageUrl(child.photo)" class="w-full h-full object-cover" alt="" />
                            <span v-else>{{ child.first_name_nom?.[0] }}</span>
                        </div>
                        <div class="flex-1">
                            <h3 class="text-2xl font-bold text-slate-900">{{ childFullName(child) }}</h3>
                            <p class="text-slate-500 text-sm mt-1">Дата рождения: {{ child.birth_date || '—' }}</p>
                            <p class="text-slate-600 text-sm mt-1">Телефон: {{ child.phone || '—' }}</p>
                            <div class="mt-2 inline-block bg-indigo-600 text-white text-xs px-3 py-1 rounded-full">
                                {{ child.rank_histories?.[0]?.rank?.name || 'Без разряда' }}
                            </div>
                            <div class="flex flex-wrap gap-2 mt-3">
                                <span v-for="g in child.groups" :key="g.id" class="text-xs px-2 py-1 bg-slate-100 rounded-full">{{ g.name }}</span>
                            </div>
                        </div>
                        <Link
                            :href="route('athlete.edit', child.id)"
                            class="shrink-0 px-4 py-2 rounded-xl bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700"
                        >
                            Редактировать данные ребёнка
                        </Link>
                    </div>

                    <div class="grid md:grid-cols-2 gap-4 px-6 pb-6">
                        <div class="rounded-xl bg-slate-50 p-4 text-sm">
                            <h4 class="font-semibold mb-2">Документы</h4>
                            <div v-for="doc in child.documents" :key="doc.id" class="flex justify-between mb-1">
                                <span class="capitalize">{{ doc.type === 'medical' ? 'Медсправка' : doc.type }}</span>
                                <span :class="isExpiring(doc.expiry_date) ? 'text-red-600 font-bold' : 'text-green-600'">до {{ doc.expiry_date || '—' }}</span>
                            </div>
                            <p v-if="!child.documents?.length" class="text-slate-400">Нет документов</p>
                        </div>
                        <div class="rounded-xl bg-slate-50 p-4 text-sm space-y-1">
                            <h4 class="font-semibold mb-2">Контакты</h4>
                            <p><b>Адрес:</b> {{ child.registration_address || '—' }}</p>
                            <p><b>Школа:</b> {{ child.school_name || '—' }}</p>
                            <p><b>Класс:</b> {{ child.school_class || '—' }}</p>
                        </div>
                    </div>
                </div>
            </template>

            <!-- Athlete block -->
            <template v-if="isAthlete && athlete">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-2xl p-6 flex flex-col sm:flex-row items-start sm:items-center gap-6 border border-slate-100">
                    <img
                        :src="storageUrl(athlete?.photo) || 'https://ui-avatars.com/api/?name=' + athlete?.first_name_nom"
                        class="w-24 h-24 rounded-full object-cover border-4 border-indigo-100"
                        alt=""
                    />
                    <div>
                        <h3 class="text-2xl font-bold">{{ athlete?.last_name_nom }} {{ athlete?.first_name_nom }}</h3>
                        <p class="text-gray-500">Дата рождения: {{ athlete?.birth_date || '—' }}</p>
                        <div class="mt-2 inline-block bg-indigo-600 text-white text-xs px-3 py-1 rounded-full">
                            {{ athlete?.rank_histories?.[0]?.rank?.name || 'Без разряда' }}
                        </div>
                        <div class="mt-3">
                            <Link :href="route('athlete.edit', athlete.id)" class="text-indigo-600 hover:underline text-sm">Редактировать профиль</Link>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="bg-white p-6 shadow-sm sm:rounded-2xl border border-slate-100">
                        <h4 class="font-bold mb-4 border-b pb-2">Мои группы</h4>
                        <div v-if="athleteGroups?.length" class="flex flex-wrap gap-2">
                            <span v-for="g in athleteGroups" :key="g.id" class="text-sm px-3 py-1 bg-indigo-50 text-indigo-800 rounded-full">{{ g.name }}</span>
                        </div>
                        <p v-else class="text-sm text-gray-400">Вы не состоите в группах</p>
                    </div>
                    <div class="bg-white p-6 shadow-sm sm:rounded-2xl border border-slate-100">
                        <div class="flex justify-between items-center mb-4 border-b pb-2">
                            <h4 class="font-bold">Законные представители</h4>
                        </div>
                        <div v-if="athleteGuardians?.length" class="space-y-3">
                            <div v-for="g in athleteGuardians" :key="g.id" class="text-sm border rounded-lg p-3">
                                <p class="font-semibold text-slate-800">{{ g.full_name }}</p>
                                <p class="text-slate-500">{{ g.relation }} · {{ g.phone }}</p>
                            </div>
                        </div>
                        <p v-else class="text-sm text-gray-400">Не указаны</p>
                    </div>
                </div>

                <div class="bg-white p-6 shadow-sm sm:rounded-2xl border border-slate-100 flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h4 class="font-bold">Портфолио</h4>
                        <p class="text-sm text-slate-500 mt-1">Достижения и результаты соревнований</p>
                    </div>
                    <Link :href="route('athlete.portfolio')" class="px-4 py-2 bg-indigo-600 text-white rounded-xl text-sm font-medium hover:bg-indigo-700">
                        Открыть портфолио
                    </Link>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="bg-white p-6 shadow-sm sm:rounded-2xl border border-slate-100">
                        <h4 class="font-bold mb-4 border-b pb-2">Статус документов</h4>
                        <div v-for="doc in athlete?.documents" :key="doc.id" class="flex justify-between items-center mb-2">
                            <span class="capitalize">{{ doc.type === 'medical' ? 'Медсправка' : 'Документ' }}</span>
                            <span :class="isExpiring(doc.expiry_date) ? 'text-red-600 font-bold' : 'text-green-600'">до {{ doc.expiry_date }}</span>
                        </div>
                        <div v-if="!athlete?.documents?.length" class="text-gray-400 text-sm">Документы не загружены</div>
                    </div>
                    <div class="bg-white p-6 shadow-sm sm:rounded-2xl border border-slate-100">
                        <h4 class="font-bold mb-4 border-b pb-2">Информация</h4>
                        <div class="text-sm text-gray-700 space-y-1">
                            <div><b>Телефон:</b> {{ athlete?.phone || '—' }}</div>
                            <div><b>Адрес:</b> {{ athlete?.registration_address || '—' }}</div>
                            <div><b>Школа:</b> {{ athlete?.school_name || '—' }}</div>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-6 shadow-sm sm:rounded-2xl border border-slate-100">
                    <h4 class="font-bold mb-4 border-b pb-2">Моё расписание</h4>
                    <div class="grid md:grid-cols-3 gap-3 mb-4">
                        <DateInput v-model="from" label="Расписание с даты" input-class="w-full border-gray-300 rounded-lg" />
                        <DateInput v-model="to" label="Расписание по дату" input-class="w-full border-gray-300 rounded-lg" />
                        <select v-model="groupId" class="border-gray-300 rounded-lg">
                            <option value="">Все группы</option>
                            <option v-for="g in athleteGroups" :key="g.id" :value="g.id">{{ g.name }}</option>
                        </select>
                    </div>
                    <div v-if="!athleteSchedule?.length" class="text-sm text-gray-400">Тренировок по фильтру не найдено</div>
                    <div v-else class="space-y-2">
                        <div v-for="item in athleteSchedule" :key="item.id" class="border rounded-lg p-3 text-sm space-y-1">
                            <div class="font-semibold">{{ item.lesson_date }} {{ item.start_time?.substring(0, 5) }}-{{ item.end_time?.substring(0, 5) }} · {{ item.group }}</div>
                            <div v-if="item.location" class="text-gray-700">Зал: {{ item.location }}</div>
                            <div v-if="item.location_address" class="text-gray-500 text-xs break-words">{{ item.location_address }}</div>
                            <div class="text-gray-500 text-xs">Тренер: {{ item.coach || '—' }}</div>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-6 shadow-sm sm:rounded-2xl border border-slate-100">
                    <h4 class="font-bold mb-2 border-b pb-2">Формируемые документы</h4>
                    <p class="text-sm text-slate-500 mb-3">
                        Все заявления и справки доступны в разделе
                        <a :href="route('profile.edit')" class="text-indigo-600 font-medium hover:underline">Профиль</a>.
                    </p>
                </div>
            </template>
        </div>
    </AuthenticatedLayout>
</template>
