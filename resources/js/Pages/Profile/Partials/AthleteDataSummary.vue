<script setup>
import AvatarZoomable from '@/Components/AvatarZoomable.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import FormedDocuments from './FormedDocuments.vue';

const props = defineProps({
    athlete: { type: Object, required: true },
    userAvatarUrl: { type: String, default: null },
    userName: { type: String, default: '' },
    documentTemplates: { type: Array, default: () => [] },
});

const avatarForm = useForm({ avatar: null });
const avatarPreview = ref(null);
const avatarInput = ref(null);

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
    qual_book: 'Зачётная книжка',
    referee_book: 'Книжка судьи',
};

const docTypeLabels = {
    medical: 'Медицинская справка',
    insurance: 'Страховка',
    identity: 'Удостоверение личности',
};

const fullName = computed(() =>
    `${props.athlete.last_name_nom} ${props.athlete.first_name_nom} ${props.athlete.middle_name_nom || ''}`.trim(),
);

const displayAvatarSrc = computed(
    () => avatarPreview.value || props.userAvatarUrl || (props.athlete.photo ? `/storage/${props.athlete.photo}` : null),
);

const genderLabel = computed(() => (props.athlete.gender === 'male' ? 'Мужской' : 'Женский'));

const occupationLabel = computed(() => {
    if (props.athlete.occupation_type === 'study') return 'Учёба';
    if (props.athlete.occupation_type === 'work') return 'Работа';
    if (props.athlete.occupation_type === 'kindergarten') return 'Детский сад';
    return '—';
});

const rankHistories = computed(() => props.athlete.rank_histories ?? props.athlete.rankHistories ?? []);
const refereeHistories = computed(() => props.athlete.referee_histories ?? props.athlete.refereeHistories ?? []);
const documents = computed(() => props.athlete.documents ?? []);
const groups = computed(() => props.athlete.groups ?? []);
const guardians = computed(() => props.athlete.guardians ?? []);

const inventoryList = computed(() => {
    const inv = props.athlete.inventory;
    if (!inv) return [];
    return Object.keys(inventoryLabels).filter((key) => Number(inv[key]) === 1 || inv[key] === true);
});

const docLabel = (type) => docTypeLabels[type] || type;

const onAvatarChange = (e) => {
    const file = e.target.files?.[0];
    if (!file) return;
    avatarForm.avatar = file;
    avatarPreview.value = URL.createObjectURL(file);
};

const uploadAvatar = () => {
    avatarForm.post(route('profile.avatar.update'), {
        forceFormData: true,
        onSuccess: () => {
            avatarForm.reset();
            avatarPreview.value = null;
            if (avatarInput.value) avatarInput.value.value = '';
        },
    });
};
</script>

<template>
    <div class="space-y-6">
        <div class="rounded-2xl border border-slate-200 bg-gradient-to-br from-slate-900 via-indigo-950 to-slate-900 text-white overflow-hidden shadow-sm">
            <div class="p-6 flex flex-col sm:flex-row sm:items-start gap-5">
                <AvatarZoomable
                    :src="displayAvatarSrc"
                    :name="fullName"
                    size="lg"
                    shape="rounded"
                    class="shrink-0 !border-white/30 !ring-white/20"
                />
                <div class="flex-1 min-w-0 space-y-3">
                    <div>
                        <h3 class="text-2xl font-bold">{{ fullName }}</h3>
                        <p class="text-indigo-200 text-sm mt-1">
                            Пол: {{ genderLabel }} · Деятельность: {{ occupationLabel }}
                        </p>
                        <p v-if="athlete.birth_date" class="text-indigo-100/90 text-sm mt-1">
                            Дата рождения: {{ athlete.birth_date }}
                        </p>
                        <p v-if="athlete.phone" class="text-indigo-100/90 text-sm">
                            Телефон: {{ athlete.phone }}
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <span
                            v-for="group in groups"
                            :key="group.id"
                            class="px-2.5 py-0.5 rounded-full bg-white/15 text-xs"
                        >Группа: {{ group.name }}</span>
                        <span v-if="!groups.length" class="text-indigo-300 text-xs">Группа не назначена</span>
                    </div>
                </div>
                <div class="flex flex-col gap-2 shrink-0 w-full sm:w-auto">
                    <Link
                        :href="route('athlete.edit', athlete.id)"
                        class="inline-flex items-center justify-center px-4 py-2 rounded-xl bg-white text-indigo-900 text-sm font-semibold hover:bg-indigo-50 transition text-center"
                    >
                        Редактировать анкету
                    </Link>
                    <form @submit.prevent="uploadAvatar" class="flex flex-col gap-2">
                        <input
                            ref="avatarInput"
                            type="file"
                            accept="image/*"
                            class="hidden"
                            @change="onAvatarChange"
                        />
                        <button
                            type="button"
                            @click="avatarInput?.click()"
                            class="inline-flex items-center justify-center px-4 py-2 rounded-xl border border-white/30 bg-white/10 text-sm font-semibold hover:bg-white/20 transition"
                        >
                            Выбрать фото
                        </button>
                        <button
                            v-if="avatarForm.avatar"
                            type="submit"
                            class="inline-flex items-center justify-center px-4 py-2 rounded-xl bg-indigo-500 text-sm font-semibold hover:bg-indigo-400 disabled:opacity-50 transition"
                            :disabled="avatarForm.processing"
                        >
                            {{ avatarForm.processing ? 'Загрузка…' : 'Сохранить аватар' }}
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h4 class="text-lg font-semibold text-slate-900 mb-4">Контакты и обучение</h4>
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
                <div><dt class="text-slate-500">Телефон</dt><dd class="font-medium text-slate-900">{{ athlete.phone || '—' }}</dd></div>
                <div><dt class="text-slate-500">Дата рождения</dt><dd class="font-medium text-slate-900">{{ athlete.birth_date || '—' }}</dd></div>
                <div class="sm:col-span-2"><dt class="text-slate-500">Адрес регистрации</dt><dd class="font-medium text-slate-900">{{ athlete.registration_address || '—' }}</dd></div>
                <template v-if="athlete.occupation_type === 'study' || athlete.school_name">
                    <div><dt class="text-slate-500">Школа / ОО</dt><dd class="font-medium text-slate-900">{{ athlete.school_name || '—' }}</dd></div>
                    <div><dt class="text-slate-500">Класс</dt><dd class="font-medium text-slate-900">{{ athlete.school_class || '—' }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-slate-500">Директор (дат. падеж)</dt><dd class="font-medium text-slate-900">{{ athlete.school_director_dat || '—' }}</dd></div>
                </template>
                <template v-if="athlete.occupation_type === 'work' || athlete.work_place">
                    <div><dt class="text-slate-500">Место работы</dt><dd class="font-medium text-slate-900">{{ athlete.work_place || '—' }}</dd></div>
                    <div><dt class="text-slate-500">Должность</dt><dd class="font-medium text-slate-900">{{ athlete.work_position || '—' }}</dd></div>
                </template>
            </dl>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h4 class="text-lg font-semibold text-slate-900 mb-3">Разряды и судейство</h4>
                <ul class="space-y-2 text-sm">
                    <li
                        v-for="item in rankHistories"
                        :key="`rank-${item.id}`"
                        class="flex justify-between gap-2 border-b border-slate-50 pb-2"
                    >
                        <span>{{ item.rank?.name || '—' }}</span>
                        <span class="text-slate-400 shrink-0">{{ item.assigned_at || '—' }}</span>
                    </li>
                    <li
                        v-for="item in refereeHistories"
                        :key="`ref-${item.id}`"
                        class="flex justify-between gap-2 border-b border-slate-50 pb-2"
                    >
                        <span>Судья: {{ item.referee_category?.name || '—' }}</span>
                        <span class="text-slate-400 shrink-0">{{ item.assigned_at || '—' }}</span>
                    </li>
                    <li v-if="!rankHistories.length && !refereeHistories.length" class="text-slate-400">Нет данных</li>
                </ul>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h4 class="text-lg font-semibold text-slate-900 mb-3">Инвентарь</h4>
                <ul v-if="inventoryList.length" class="text-sm space-y-1 text-slate-800">
                    <li v-for="key in inventoryList" :key="key">· {{ inventoryLabels[key] }}</li>
                </ul>
                <p v-else class="text-sm text-slate-400">Нет выданного инвентаря</p>
            </div>
        </div>

        <div v-if="guardians.length" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h4 class="text-lg font-semibold text-slate-900 mb-3">Законные представители</h4>
            <ul class="space-y-2 text-sm">
                <li v-for="g in guardians" :key="g.id" class="rounded-xl bg-slate-50 px-4 py-3">
                    <p class="font-medium text-slate-900">{{ g.full_name }}</p>
                    <p class="text-slate-500">{{ g.relation }} · {{ g.phone || 'без телефона' }}</p>
                </li>
            </ul>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h4 class="text-lg font-semibold text-slate-900 mb-3">Документы</h4>
            <ul v-if="documents.length" class="space-y-2 text-sm">
                <li
                    v-for="doc in documents"
                    :key="doc.id"
                    class="flex flex-wrap items-center justify-between gap-2 rounded-xl bg-slate-50 p-3"
                >
                    <div>
                        <span class="font-medium text-slate-900">{{ docLabel(doc.type) }}</span>
                        <span class="text-slate-500 block text-xs mt-0.5">
                            {{ doc.issue_date || '—' }} — {{ doc.expiry_date || 'без срока' }}
                        </span>
                        <span v-if="doc.type === 'identity' && doc.series" class="text-xs text-slate-500">
                            {{ doc.series }} {{ doc.number }}
                        </span>
                    </div>
                    <a
                        v-if="doc.file_path"
                        :href="`/storage/${doc.file_path}`"
                        target="_blank"
                        class="text-indigo-600 text-sm font-medium hover:underline shrink-0"
                    >
                        Открыть файл
                    </a>
                </li>
            </ul>
            <p v-else class="text-sm text-slate-400">Документы не загружены</p>
        </div>

        <FormedDocuments
            v-if="documentTemplates.length"
            :templates="documentTemplates"
            :athlete-id="athlete.id"
        />
    </div>
</template>
