<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AvatarZoomable from '@/Components/AvatarZoomable.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    athlete: Object,
    age: Number,
    ageLabel: String,
});

const docTypeLabels = {
    medical: 'Медицинская справка',
    insurance: 'Страховка',
    identity: 'Удостоверение личности',
};

const docLabel = (type) => docTypeLabels[type] || type;

const inventoryLabels = {
    weapon_case: 'Чехол для оружия',
    jo: 'Дзё',
    boken: 'Бокен',
    tanto: 'Танто',
    tshirt: 'Футболка',
    olympic_jacket: 'Олимпийка',
    cap: 'Бейсболка',
    backpack: 'Рюкзак',
    shoe_bag: 'Мешок для сменки',
    budo_passport: 'Будо-паспорт',
    qual_book: 'Зачетная книжка',
    referee_book: 'Книжка судьи',
};

const formatInventory = (inventory) => {
    if (!inventory) return 'Нет данных';
    const keys = Object.keys(inventoryLabels).filter((key) => Number(inventory[key]) === 1 || inventory[key] === true);
    return keys.length ? keys.map((key) => inventoryLabels[key]).join(', ') : 'Нет выданного инвентаря';
};

const editingGuardianId = ref(null);
const editGuardianForms = ref({});

const initEditForm = (guardian) => {
    editGuardianForms.value[guardian.id] = {
        full_name: guardian.full_name,
        phone: guardian.phone || '',
        relation: guardian.relation || 'Отец',
    };
};

const saveGuardian = (guardianId) => {
    const data = editGuardianForms.value[guardianId];
    router.patch(route('admin.athletes.guardians.update', [props.athlete.id, guardianId]), data, {
        onSuccess: () => {
            editingGuardianId.value = null;
        },
    });
};

const fullName = `${props.athlete.last_name_nom} ${props.athlete.first_name_nom} ${props.athlete.middle_name_nom || ''}`.trim();

const photoSrc = computed(() => (props.athlete.photo ? `/storage/${props.athlete.photo}` : null));
</script>

<template>
    <Head :title="`Спортсмен: ${fullName}`" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center gap-3">
                <Link :href="route('admin.athletes')" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium">← Реестр</Link>
                <span class="text-gray-400">/</span>
                <span class="font-semibold text-gray-800">Карточка спортсмена</span>
            </div>
        </template>

        <div class="max-w-6xl mx-auto space-y-6">
            <!-- Hero -->
            <div class="bg-gradient-to-br from-slate-900 via-indigo-950 to-slate-900 rounded-2xl shadow-xl text-white overflow-hidden">
                <div class="p-8 flex flex-col md:flex-row md:items-center gap-6">
                    <AvatarZoomable
                        :src="photoSrc"
                        :name="fullName"
                        size="xl"
                        shape="rounded"
                        class="shrink-0 !border-white/30 !ring-white/20"
                    />
                    <div class="flex-1 min-w-0">
                        <h1 class="text-3xl font-bold tracking-tight">{{ fullName }}</h1>
                        <p class="text-indigo-200 mt-1">
                            {{ ageLabel || `${age} лет` }} · {{ athlete.gender === 'male' ? 'Мужской' : 'Женский' }}
                        </p>
                        <div class="flex flex-wrap gap-2 mt-4">
                            <span
                                v-for="group in athlete.groups"
                                :key="group.id"
                                class="px-3 py-1 rounded-full bg-white/15 text-sm"
                            >{{ group.name }}</span>
                            <span v-if="!athlete.groups?.length" class="text-indigo-300 text-sm">Без группы</span>
                        </div>
                    </div>
                    <Link
                        :href="route('athlete.edit', athlete.id)"
                        class="shrink-0 inline-flex items-center justify-center px-5 py-2.5 rounded-xl bg-white text-indigo-900 font-semibold hover:bg-indigo-50 transition"
                    >
                        Редактировать
                    </Link>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Contacts -->
                <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
                    <h2 class="text-lg font-bold text-slate-800 mb-4">Контакты и обучение</h2>
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4 text-sm">
                        <div><dt class="text-slate-500">Телефон</dt><dd class="font-medium">{{ athlete.phone || '—' }}</dd></div>
                        <div><dt class="text-slate-500">Дата рождения</dt><dd class="font-medium">{{ athlete.birth_date || '—' }}</dd></div>
                        <div class="sm:col-span-2"><dt class="text-slate-500">Адрес</dt><dd class="font-medium">{{ athlete.registration_address || '—' }}</dd></div>
                        <div><dt class="text-slate-500">Школа</dt><dd class="font-medium">{{ athlete.school_name || '—' }}</dd></div>
                        <div><dt class="text-slate-500">Класс</dt><dd class="font-medium">{{ athlete.school_class || '—' }}</dd></div>
                        <div><dt class="text-slate-500">Директор (дат.п)</dt><dd class="font-medium">{{ athlete.school_director_dat || '—' }}</dd></div>
                        <div><dt class="text-slate-500">Работа</dt><dd class="font-medium">{{ athlete.work_place || '—' }}</dd></div>
                        <div><dt class="text-slate-500">Должность</dt><dd class="font-medium">{{ athlete.work_position || '—' }}</dd></div>
                    </dl>
                </div>

                <!-- Ranks -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
                    <h2 class="text-lg font-bold text-slate-800 mb-4">Разряды</h2>
                    <ul class="space-y-2 text-sm">
                        <li v-for="item in athlete.rank_histories" :key="`rank-${item.id}`" class="flex justify-between gap-2 border-b border-slate-50 pb-2">
                            <span>{{ item.rank?.name || '—' }}</span>
                            <span class="text-slate-400">{{ item.assigned_at || '—' }}</span>
                        </li>
                        <li v-for="item in athlete.referee_histories" :key="`ref-${item.id}`" class="flex justify-between gap-2 border-b border-slate-50 pb-2">
                            <span>Судья: {{ item.referee_category?.name || '—' }}</span>
                            <span class="text-slate-400">{{ item.assigned_at || '—' }}</span>
                        </li>
                        <li v-if="!athlete.rank_histories?.length && !athlete.referee_histories?.length" class="text-slate-400">Нет данных</li>
                    </ul>
                </div>
            </div>

            <!-- Guardians (только просмотр и правка существующих) -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
                <h2 class="text-lg font-bold text-slate-800 mb-4">Законные представители</h2>

                <div v-if="!athlete.guardians?.length" class="text-slate-400 text-sm py-4 text-center">
                    Нет законных представителей
                </div>

                <div class="space-y-3">
                    <div
                        v-for="guardian in athlete.guardians"
                        :key="guardian.id"
                        class="rounded-xl border border-slate-200 p-4 hover:border-indigo-200 transition"
                    >
                        <template v-if="editingGuardianId !== guardian.id">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p class="font-semibold text-slate-900">{{ guardian.full_name }}</p>
                                    <p class="text-sm text-slate-500 mt-0.5">{{ guardian.relation }} · {{ guardian.phone || 'без телефона' }}</p>
                                </div>
                                <button
                                    type="button"
                                    class="text-sm text-indigo-600 font-medium"
                                    @click="editingGuardianId = guardian.id; initEditForm(guardian)"
                                >
                                    Изменить
                                </button>
                            </div>
                        </template>
                        <template v-else>
                            <div class="grid md:grid-cols-4 gap-3">
                                <input v-model="editGuardianForms[guardian.id].full_name" class="rounded-lg border-slate-300" />
                                <input v-model="editGuardianForms[guardian.id].phone" class="rounded-lg border-slate-300" />
                                <select v-model="editGuardianForms[guardian.id].relation" class="rounded-lg border-slate-300">
                                    <option value="Отец">Отец</option>
                                    <option value="Мать">Мать</option>
                                    <option value="Опекун">Опекун</option>
                                </select>
                                <div class="flex gap-2">
                                    <button type="button" @click="saveGuardian(guardian.id)" class="flex-1 rounded-lg bg-emerald-600 text-white py-2 text-sm font-medium">Сохранить</button>
                                    <button type="button" @click="editingGuardianId = null" class="rounded-lg border border-slate-300 px-3 text-sm">Отмена</button>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Documents & inventory -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
                    <h2 class="text-lg font-bold text-slate-800 mb-4">Документы</h2>
                    <ul class="space-y-2 text-sm">
                        <li v-for="doc in athlete.documents" :key="doc.id" class="p-3 rounded-lg bg-slate-50">
                            <span class="font-medium text-slate-900">{{ docLabel(doc.type) }}</span>
                            <p class="text-slate-500 text-xs mt-0.5">
                                Выдан: {{ doc.issue_date || '—' }} · Действует до: {{ doc.expiry_date || '—' }}
                            </p>
                            <a
                                v-if="doc.file_path"
                                :href="`/storage/${doc.file_path}`"
                                target="_blank"
                                class="inline-block mt-1 text-indigo-600 text-xs font-medium hover:underline"
                            >
                                Открыть файл
                            </a>
                        </li>
                        <li v-if="!athlete.documents?.length" class="text-slate-400">Нет документов</li>
                    </ul>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
