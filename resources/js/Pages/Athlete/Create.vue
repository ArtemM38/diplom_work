<script setup>
import { useForm, Head, Link } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import debounce from 'lodash/debounce';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import FormErrorsAlert from '@/Components/FormErrorsAlert.vue';
import { fieldClass } from '@/utils/formErrors';

const props = defineProps({
    ranks: Array,
    referee_categories: Array,
    existingGuardians: Array, // Список родителей из БД
    isParentRegistering: Boolean,
    prefilledName: {
        type: Object,
        default: null,
    },
    guardianRelation: {
        type: String,
        default: '',
    },
    editingAthlete: {
        type: Object,
        default: null,
    },
    submitRoute: {
        type: String,
        default: null,
    },
    submitMethod: {
        type: String,
        default: 'post',
    },
    cancelRoute: {
        type: String,
        default: null,
    },
});

const form = useForm({
    // Основное
    last_name_nom: '',
    first_name_nom: '',
    middle_name_nom: '',
    phone: '',
    birth_date: '',
    gender: 'male',
    registration_address: '',
    photo: null,

    occupation_type: 'study',
    school_name: '',
    school_director_dat: '',
    school_class: '',
    kindergarten_name: '',
    work_place: '',
    work_position: '',

    // Динамические списки
    ranks: [], // [{ rank_id: '', assigned_at: '' }]
    referees: [], // [{ referee_category_id: '', assigned_at: '' }]
    guardian_id: null,
    relation: '',

    // Инвентарь (соответствует именам в миграции)
    inventory: {
        weapon_case: false,
        jo: false,
        boken: false,
        tanto: false,
        tshirt: false,
        olympic_jacket: false,
        cap: false,
        backpack: false,
        shoe_bag: false,
        budo_passport: false,
        qual_book: false,
        referee_book: false,

    },

    // Документы
    doc_medical_file: null,
    doc_medical_issue: '',
    doc_medical_expiry: '',

    doc_insurance_file: null,
    doc_insurance_issue: '',
    doc_insurance_expiry: '',

    doc_identity_kind: 'passport',
    doc_identity_file: null,
    doc_identity_series: '',
    doc_identity_number: '',
    doc_identity_issued_by: '',
    doc_identity_issue_date: '',
});

// Функции для динамических полей
const addRank = () => form.ranks.push({ rank_id: '', assigned_at: '' });
const removeRank = (index) => form.ranks.splice(index, 1);

const addReferee = () => form.referees.push({ referee_category_id: '', assigned_at: '' });
const removeReferee = (index) => form.referees.splice(index, 1);

const formatPhone = (value) => {
    const digits = (value || '').replace(/\D/g, '').slice(0, 11);
    const normalized = digits.startsWith('8') ? `7${digits.slice(1)}` : digits;
    const d = normalized.startsWith('7') ? normalized.slice(1) : normalized;
    let out = '+7';
    if (d.length > 0) out += ` (${d.slice(0, 3)}`;
    if (d.length >= 3) out += ')';
    if (d.length > 3) out += ` ${d.slice(3, 6)}`;
    if (d.length > 6) out += `-${d.slice(6, 8)}`;
    if (d.length > 8) out += `-${d.slice(8, 10)}`;
    return out;
};

const onPhoneInput = (event) => {
    form.phone = formatPhone(event.target.value);
};

const addressSuggestions = ref([]);
const showAddressSuggestions = ref(false);
const suppressAddressSuggestOnce = ref(false);
const addressBoxRef = ref(null);

const fetchAddressSuggestions = debounce(async (value) => {
    if (!value || value.length < 3) {
        addressSuggestions.value = [];
        return;
    }

    try {
        const response = await fetch(`${route('address.suggest')}?query=${encodeURIComponent(value)}`);
        const data = await response.json();
        addressSuggestions.value = data.suggestions || [];
    } catch {
        addressSuggestions.value = [];
    }
}, 250);

watch(() => form.registration_address, (value) => {
    if (suppressAddressSuggestOnce.value) {
        suppressAddressSuggestOnce.value = false;
        return;
    }
    fetchAddressSuggestions(value);
});

const pickAddress = (value) => {
    suppressAddressSuggestOnce.value = true;
    form.registration_address = value;
    addressSuggestions.value = [];
    showAddressSuggestions.value = false;
};

const onDocumentPointerDown = (event) => {
    const root = addressBoxRef.value;
    if (!root) return;
    if (!root.contains(event.target)) {
        showAddressSuggestions.value = false;
    }
};

const targetRoute = computed(() => props.submitRoute || route('athlete.store'));
const targetCancelRoute = computed(() => props.cancelRoute || route('dashboard'));
const today = computed(() => new Date().toISOString().slice(0, 10));

const hasFile = (file) => file instanceof File;

const docMedicalWarnings = computed(() => {
    if (!hasFile(form.doc_medical_file)) return [];
    const items = [];
    if (!form.doc_medical_issue) items.push('укажите дату выдачи');
    if (!form.doc_medical_expiry) items.push('укажите срок действия');
    if (form.doc_medical_issue && form.doc_medical_expiry && form.doc_medical_expiry < form.doc_medical_issue) {
        items.push('срок действия не может быть раньше даты выдачи');
    }
    return items;
});

const docInsuranceWarnings = computed(() => {
    if (!hasFile(form.doc_insurance_file)) return [];
    const items = [];
    if (!form.doc_insurance_issue) items.push('укажите дату выдачи');
    if (!form.doc_insurance_expiry) items.push('укажите срок действия');
    if (form.doc_insurance_issue && form.doc_insurance_expiry && form.doc_insurance_expiry < form.doc_insurance_issue) {
        items.push('срок действия не может быть раньше даты выдачи');
    }
    return items;
});

const docIdentityWarnings = computed(() => {
    if (!hasFile(form.doc_identity_file)) return [];
    const items = [];
    if (!form.doc_identity_series?.trim()) items.push('укажите серию');
    if (!form.doc_identity_number?.trim()) items.push('укажите номер');
    if (!form.doc_identity_issued_by?.trim()) items.push('укажите, кем выдан');
    if (!form.doc_identity_issue_date) items.push('укажите дату выдачи');
    return items;
});

const docWarnings = computed(() => [
    ...docMedicalWarnings.value.map((t) => ({ block: 'medical', text: `Медицинская справка: ${t}` })),
    ...docInsuranceWarnings.value.map((t) => ({ block: 'insurance', text: `Страховой полис: ${t}` })),
    ...docIdentityWarnings.value.map((t) => ({ block: 'identity', text: `Удостоверение личности: ${t}` })),
]);

const onDocFileInput = (field, event) => {
    const file = event.target.files?.[0] ?? null;
    form[field] = file;
};

const fileLabel = (file) => (file instanceof File ? file.name : '');

const submit = () => {
    if (docWarnings.value.length) {
        document.getElementById('athlete-documents')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        return;
    }

    form.submit(props.submitMethod, targetRoute.value, {
        forceFormData: true,
        onSuccess: () => alert('Данные успешно сохранены'),
    });
};

onMounted(() => {
    document.addEventListener('pointerdown', onDocumentPointerDown);

    if (!props.editingAthlete && props.prefilledName) {
        form.last_name_nom = props.prefilledName.last_name_nom ?? '';
        form.first_name_nom = props.prefilledName.first_name_nom ?? '';
        form.middle_name_nom = props.prefilledName.middle_name_nom ?? '';
        return;
    }

    if (!props.editingAthlete) {
        return;
    }

    form.last_name_nom = props.editingAthlete.last_name_nom ?? '';
    form.first_name_nom = props.editingAthlete.first_name_nom ?? '';
    form.middle_name_nom = props.editingAthlete.middle_name_nom ?? '';
    form.phone = props.editingAthlete.phone ?? '';
    form.birth_date = props.editingAthlete.birth_date ?? '';
    form.gender = props.editingAthlete.gender ?? 'male';
    form.registration_address = props.editingAthlete.registration_address ?? '';
    form.school_name = props.editingAthlete.school_name ?? '';
    form.school_director_dat = props.editingAthlete.school_director_dat ?? '';
    form.school_class = props.editingAthlete.school_class ?? '';
    form.work_place = props.editingAthlete.work_place ?? '';
    form.work_position = props.editingAthlete.work_position ?? '';
    form.ranks = (props.editingAthlete.rank_histories || []).map((item) => ({
        rank_id: item.rank_id,
        assigned_at: item.assigned_at,
    }));
    form.referees = (props.editingAthlete.referee_histories || []).map((item) => ({
        referee_category_id: item.referee_category_id,
        assigned_at: item.assigned_at,
    }));
    form.inventory = {
        ...form.inventory,
        ...(props.editingAthlete.inventory || {}),
    };
    form.guardian_id = props.editingAthlete.guardians?.[0]?.id ?? null;
    form.relation = props.editingAthlete.guardians?.[0]?.relation ?? '';
    form.occupation_type = props.editingAthlete.occupation_type || (props.editingAthlete.school_name ? 'study' : props.editingAthlete.work_place ? 'work' : 'study');
});

onUnmounted(() => {
    document.removeEventListener('pointerdown', onDocumentPointerDown);
});
</script>

<template>

    <Head title="Регистрация спортсмена" />

    <div class="min-h-screen bg-gray-100 py-8 px-4 overflow-x-hidden">
        <div class="max-w-6xl mx-auto min-w-0">
            <div class="text-center mb-6">
                <h1 class="text-2xl font-bold text-slate-900">{{ editingAthlete ? 'Редактирование карточки спортсмена' : 'Регистрация спортсмена' }}</h1>
                <p class="text-sm text-slate-500 mt-1">Заполните профиль спортсмена</p>
            </div>
        </div>
        <div class="py-2">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 min-w-0">
            <form @submit.prevent="submit" class="space-y-8 min-w-0">
                <FormErrorsAlert :errors="form.errors" />

                <!-- БЛОК 1: Основная информация -->
                <div class="bg-white p-6 shadow rounded-lg min-w-0 overflow-visible">
                    <h2 class="text-xl font-bold mb-6 text-blue-900 border-b pb-2 break-words">1. Личные данные</h2>

                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 min-w-0">
                        <div class="md:col-span-1">
                            <InputLabel value="Фото спортсмена" />
                            <input
                                type="file"
                                accept="image/*"
                                @input="form.photo = $event.target.files[0]"
                                :class="fieldClass(form.errors, 'photo', 'mt-1 block w-full text-sm rounded-md border')"
                            />
                            <InputError class="mt-1" :message="form.errors.photo" />
                        </div>

                        <div class="md:col-span-3 grid grid-cols-1 md:grid-cols-3 gap-4 min-w-0">
                            <div class="min-w-0">
                                <InputLabel value="Фамилия (Им.п)" />
                                <TextInput v-model="form.last_name_nom" class="w-full" required :invalid="!!form.errors.last_name_nom" />
                                <InputError class="mt-1" :message="form.errors.last_name_nom" />
                            </div>
                            <div>
                                <InputLabel value="Имя (Им.п)" />
                                <TextInput v-model="form.first_name_nom" class="w-full" required :invalid="!!form.errors.first_name_nom" />
                                <InputError class="mt-1" :message="form.errors.first_name_nom" />
                            </div>
                            <div>
                                <InputLabel value="Отчество (Им.п)" />
                                <TextInput v-model="form.middle_name_nom" class="w-full" :invalid="!!form.errors.middle_name_nom" />
                                <InputError class="mt-1" :message="form.errors.middle_name_nom" />
                            </div>
                        </div>
                    </div>

                    <p class="mt-4 text-xs text-slate-500">
                        ФИО заполняется только в именительном падеже. Остальные падежи рассчитываются автоматически.
                    </p>

                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-6">
                        <div>
                            <InputLabel value="Телефон" />
                            <TextInput
                                v-model="form.phone"
                                @input="onPhoneInput"
                                type="tel"
                                class="w-full"
                                placeholder="+7 (___) ___-__-__"
                                :invalid="!!form.errors.phone"
                            />
                            <InputError class="mt-1" :message="form.errors.phone" />
                        </div>
                        <div>
                            <InputLabel value="Дата рождения" />
                            <TextInput v-model="form.birth_date" type="date" :max="today" class="w-full" required :invalid="!!form.errors.birth_date" />
                            <InputError class="mt-1" :message="form.errors.birth_date" />
                        </div>
                        <div>
                            <InputLabel value="Пол" />
                            <select v-model="form.gender" :class="fieldClass(form.errors, 'gender', 'w-full rounded-md shadow-sm')">
                                <option value="male">Мужской</option>
                                <option value="female">Женский</option>
                            </select>
                        </div>
                        <div ref="addressBoxRef" class="relative">
                            <InputLabel value="Адрес регистрации" />
                            <TextInput v-model="form.registration_address" class="w-full text-slate-900"
                                @focus="showAddressSuggestions = true"
                                @blur="setTimeout(() => (showAddressSuggestions = false), 120)"
                                placeholder="Город, улица..." />
                            <div
                                v-if="showAddressSuggestions && addressSuggestions.length"
                                class="absolute top-full left-0 z-50 mt-1 w-full bg-white border rounded-lg shadow-xl max-h-96 overflow-auto"
                            >
                                    <button
                                        v-for="item in addressSuggestions"
                                        :key="item.value"
                                        type="button"
                                        @mousedown.prevent
                                        @click="pickAddress(item.value)"
                                        class="w-full text-left px-3 py-2 text-sm text-slate-900 hover:bg-gray-50"
                                    >
                                        {{ item.value }}
                                    </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- БЛОК 2: Обучение и Работа -->
                <div class="bg-white p-6 shadow rounded-lg">
                    <h2 class="text-xl font-bold mb-4 text-blue-900 border-b pb-2">2. Учёба или работа</h2>
                    <InputError class="mb-3" :message="form.errors.occupation_type" />
                    <div class="flex flex-wrap gap-4 mb-6">
                        <label class="flex items-center gap-2 cursor-pointer px-4 py-3 rounded-xl border-2 transition"
                            :class="form.occupation_type === 'study' ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200'">
                            <input type="radio" v-model="form.occupation_type" value="study" class="text-indigo-600" />
                            <span class="font-medium">Учёба</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer px-4 py-3 rounded-xl border-2 transition"
                            :class="form.occupation_type === 'work' ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200'">
                            <input type="radio" v-model="form.occupation_type" value="work" class="text-indigo-600" />
                            <span class="font-medium">Работа</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer px-4 py-3 rounded-xl border-2 transition"
                            :class="form.occupation_type === 'kindergarten' ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200'">
                            <input type="radio" v-model="form.occupation_type" value="kindergarten" class="text-indigo-600" />
                            <span class="font-medium">Детский сад</span>
                        </label>
                    </div>
                    <div v-if="form.occupation_type === 'study'" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <InputLabel value="Наименование ОО (Школа/ВУЗ)" />
                            <TextInput v-model="form.school_name" class="w-full" />
                        </div>
                        <div>
                            <InputLabel value="ФИО Директора" />
                            <TextInput v-model="form.school_director_dat" class="w-full" />
                        </div>
                        <div>
                            <InputLabel value="Класс/курс" />
                            <TextInput v-model="form.school_class" class="w-full" />
                        </div>
                    </div>
                    <div v-else-if="form.occupation_type === 'work'" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <InputLabel value="Место работы" />
                            <TextInput v-model="form.work_place" class="w-full" :class="{ 'border-red-500': form.errors.work_place }" />
                            <p v-if="form.errors.work_place" class="text-red-600 text-xs mt-1">{{ form.errors.work_place }}</p>
                        </div>
                        <div>
                            <InputLabel value="Должность" />
                            <TextInput v-model="form.work_position" class="w-full" :class="{ 'border-red-500': form.errors.work_position }" />
                            <p v-if="form.errors.work_position" class="text-red-600 text-xs mt-1">{{ form.errors.work_position }}</p>
                        </div>
                    </div>
                    <div v-else-if="form.occupation_type === 'kindergarten'" class="grid grid-cols-1 gap-4">
                        <div>
                            <InputLabel value="Наименование детского сада" />
                            <TextInput v-model="form.kindergarten_name" class="w-full" :class="{ 'border-red-500': form.errors.kindergarten_name }" />
                            <p v-if="form.errors.kindergarten_name" class="text-red-600 text-xs mt-1">{{ form.errors.kindergarten_name }}</p>
                        </div>
                    </div>
                </div>

                <!-- БЛОК 3: Квалификации -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Спортивный разряд -->
                    <div class="bg-white p-6 shadow rounded-lg">
                        <div class="flex justify-between items-center mb-4">
                            <h2 class="text-lg font-bold">Спортивный разряд</h2>
                            <button type="button" @click="addRank"
                                class="text-sm bg-green-600 text-white px-3 py-1 rounded">+ Добавить</button>
                        </div>
                        <div v-for="(item, index) in form.ranks" :key="index" class="flex gap-2 mb-3 items-end">
                            <div class="flex-1">
                                <InputLabel value="Разряд" />
                                <select v-model="item.rank_id" class="w-full border-gray-300 rounded-md text-sm">
                                    <option v-for="rank in props.ranks" :key="rank.id" :value="rank.id">{{ rank.name }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <InputLabel value="Дата" />
                                <TextInput type="date" :max="today" v-model="item.assigned_at" class="w-full text-sm" />
                            </div>
                            <button @click="removeRank(index)" class="bg-red-100 text-red-600 p-2 rounded">✕</button>
                        </div>
                    </div>

                    <!-- Судейская категория -->
                    <div class="bg-white p-6 shadow rounded-lg">
                        <div class="flex justify-between items-center mb-4">
                            <h2 class="text-lg font-bold">Судейская категория</h2>
                            <button type="button" @click="addReferee"
                                class="text-sm bg-green-600 text-white px-3 py-1 rounded">+ Добавить</button>
                        </div>
                        <div v-for="(item, index) in form.referees" :key="index" class="flex gap-2 mb-3 items-end">
                            <div class="flex-1">
                                <InputLabel value="Категория" />
                                <select v-model="item.referee_category_id"
                                    class="w-full border-gray-300 rounded-md text-sm">
                                    <option v-for="cat in props.referee_categories" :key="cat.id" :value="cat.id">{{
                                        cat.name }}</option>
                                </select>
                            </div>
                            <div>
                                <InputLabel value="Дата" />
                                <TextInput type="date" :max="today" v-model="item.assigned_at" class="w-full text-sm" />
                            </div>
                            <button @click="removeReferee(index)" class="bg-red-100 text-red-600 p-2 rounded">✕</button>
                        </div>
                    </div>
                </div>

                <!-- БЛОК 4: Документы и сканы -->
                <div id="athlete-documents" class="bg-white p-6 shadow rounded-lg min-w-0 overflow-hidden">
                    <h2 class="text-xl font-bold mb-6 text-blue-900 border-b pb-2">3. Документы (сканы)</h2>

                    <div
                        v-if="docWarnings.length"
                        class="mb-4 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900"
                        role="alert"
                    >
                        <p class="font-semibold mb-1">Заполните данные для прикреплённых файлов:</p>
                        <ul class="list-disc list-inside space-y-0.5">
                            <li v-for="(warn, idx) in docWarnings" :key="idx">{{ warn.text }}</li>
                        </ul>
                    </div>

                    <div class="space-y-6">
                        <!-- Медсправка -->
                        <div
                            class="grid grid-cols-1 md:grid-cols-4 gap-4 p-4 border rounded min-w-0"
                            :class="docMedicalWarnings.length ? 'border-amber-400 bg-amber-50/40' : ''"
                        >
                            <div class="font-semibold break-words">Медицинская справка</div>
                            <div class="min-w-0">
                                <InputLabel value="Дата выдачи" />
                                <TextInput type="date" v-model="form.doc_medical_issue" class="w-full" />
                                <InputError class="mt-1" :message="form.errors.doc_medical_issue" />
                            </div>
                            <div class="min-w-0">
                                <InputLabel value="Срок действия" />
                                <TextInput type="date" v-model="form.doc_medical_expiry" class="w-full" />
                                <InputError class="mt-1" :message="form.errors.doc_medical_expiry" />
                            </div>
                            <div class="min-w-0">
                                <InputLabel value="Скан (PDF/JPG)" />
                                <input
                                    type="file"
                                    accept=".pdf,.jpg,.jpeg,.png"
                                    class="block w-full text-sm"
                                    @input="onDocFileInput('doc_medical_file', $event)"
                                />
                                <p v-if="fileLabel(form.doc_medical_file)" class="text-xs text-emerald-700 mt-1">
                                    Файл: {{ fileLabel(form.doc_medical_file) }}
                                </p>
                                <p v-for="(msg, i) in docMedicalWarnings" :key="i" class="text-xs text-amber-700 mt-1 font-medium">
                                    {{ msg }}
                                </p>
                                <InputError class="mt-1" :message="form.errors.doc_medical_file" />
                            </div>
                        </div>

                        <!-- Страховка -->
                        <div
                            class="grid grid-cols-1 md:grid-cols-4 gap-4 p-4 border rounded min-w-0"
                            :class="docInsuranceWarnings.length ? 'border-amber-400 bg-amber-50/40' : ''"
                        >
                            <div class="font-semibold break-words">Страховой полис</div>
                            <div class="min-w-0">
                                <InputLabel value="Дата выдачи" />
                                <TextInput type="date" v-model="form.doc_insurance_issue" class="w-full" />
                                <InputError class="mt-1" :message="form.errors.doc_insurance_issue" />
                            </div>
                            <div class="min-w-0">
                                <InputLabel value="Срок действия" />
                                <TextInput type="date" v-model="form.doc_insurance_expiry" class="w-full" />
                                <InputError class="mt-1" :message="form.errors.doc_insurance_expiry" />
                            </div>
                            <div class="min-w-0">
                                <InputLabel value="Скан (PDF/JPG)" />
                                <input
                                    type="file"
                                    accept=".pdf,.jpg,.jpeg,.png"
                                    class="block w-full text-sm"
                                    @input="onDocFileInput('doc_insurance_file', $event)"
                                />
                                <p v-if="fileLabel(form.doc_insurance_file)" class="text-xs text-emerald-700 mt-1">
                                    Файл: {{ fileLabel(form.doc_insurance_file) }}
                                </p>
                                <p v-for="(msg, i) in docInsuranceWarnings" :key="i" class="text-xs text-amber-700 mt-1 font-medium">
                                    {{ msg }}
                                </p>
                                <InputError class="mt-1" :message="form.errors.doc_insurance_file" />
                            </div>
                        </div>

                        <!-- Паспорт/Свид-во -->
                        <div
                            class="grid grid-cols-1 md:grid-cols-4 gap-4 p-4 border rounded bg-gray-50 min-w-0"
                            :class="docIdentityWarnings.length ? 'border-amber-400 !bg-amber-50/40' : ''"
                        >
                            <div class="font-semibold break-words md:col-span-4">Удостоверение личности</div>
                            <div class="md:col-span-4 flex flex-wrap gap-4 text-sm">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input v-model="form.doc_identity_kind" type="radio" value="passport" class="rounded border-gray-300" />
                                    Паспорт
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input v-model="form.doc_identity_kind" type="radio" value="birth_certificate" class="rounded border-gray-300" />
                                    Свидетельство о рождении
                                </label>
                                <InputError :message="form.errors.doc_identity_kind" />
                            </div>
                            <div class="space-y-2 min-w-0">
                                <TextInput v-model="form.doc_identity_series" placeholder="Серия"
                                    class="w-full text-sm" />
                                <TextInput v-model="form.doc_identity_number" placeholder="Номер"
                                    class="w-full text-sm" />
                                <InputError :message="form.errors.doc_identity_series" />
                                <InputError :message="form.errors.doc_identity_number" />
                            </div>
                            <div class="space-y-2 min-w-0">
                                <TextInput v-model="form.doc_identity_issued_by" placeholder="Кем выдан"
                                    class="w-full text-sm" />
                                <TextInput type="date" v-model="form.doc_identity_issue_date" class="w-full text-sm" />
                                <InputError :message="form.errors.doc_identity_issued_by" />
                                <InputError :message="form.errors.doc_identity_issue_date" />
                            </div>
                            <div class="min-w-0">
                                <InputLabel value="Скан" />
                                <input
                                    type="file"
                                    accept=".pdf,.jpg,.jpeg,.png"
                                    class="block w-full text-sm"
                                    @input="onDocFileInput('doc_identity_file', $event)"
                                />
                                <p v-if="fileLabel(form.doc_identity_file)" class="text-xs text-emerald-700 mt-1">
                                    Файл: {{ fileLabel(form.doc_identity_file) }}
                                </p>
                                <p v-for="(msg, i) in docIdentityWarnings" :key="i" class="text-xs text-amber-700 mt-1 font-medium">
                                    {{ msg }}
                                </p>
                                <InputError class="mt-1" :message="form.errors.doc_identity_file" />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-blue-50 p-6 shadow rounded-xl border border-blue-200">
                    <h2 class="text-xl font-bold mb-2 text-blue-900">Законный представитель</h2>

                    <!-- Если залогинен сам спортсмен -->
                    <div v-if="!props.isParentRegistering">
                        <p class="text-sm text-blue-700 mb-4 italic">Если ваш родитель уже зарегистрирован, выберите его
                            ниже:</p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <select v-model="form.guardian_id" class="border-gray-300 rounded-md w-full">
                                <option :value="null">-- Выберите из списка (если есть) --</option>
                                <option v-for="g in props.existingGuardians" :key="g.id" :value="g.id">
                                    {{ g.full_name }} ({{ g.phone }})
                                </option>
                            </select>
                            <TextInput v-model="form.relation" placeholder="Кем вам приходится? (Мать/Отец)" />
                        </div>
                    </div>

                    <!-- Если залогинен родитель -->
                    <div v-else class="rounded-lg bg-white/80 px-4 py-3 text-blue-900">
                        <p class="font-medium">Вы регистрируете ребёнка как законный представитель.</p>
                        <p v-if="guardianRelation" class="text-sm mt-1 text-blue-700">
                            Степень родства: <span class="font-semibold">{{ guardianRelation }}</span>
                        </p>
                    </div>
                </div>
                <!-- Кнопка сохранения -->
                <div class="flex justify-center">
                <Link
                        v-if="editingAthlete?.id"
                        :href="targetCancelRoute"
                        class="inline-flex px-4 py-2 mr-10 rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-100">
                        Отмена / Назад
                    </Link>
                    <PrimaryButton :disabled="form.processing" class="w-full md:w-1/2 py-4 justify-center text-lg">
                        {{ form.processing ? 'Сохранение...' : (editingAthlete ? 'Сохранить изменения' : 'Завершить регистрацию профиля') }}
                    </PrimaryButton>
                </div>

            </form>
        </div>
    </div>
    </div>
</template>

<style scoped>
label {
    @apply text-sm text-gray-700 mb-1;
}

input[type="checkbox"] {
    @apply text-blue-600 focus:ring-blue-500;
}

:deep(input),
:deep(select),
:deep(textarea) {
    max-width: 100%;
    min-width: 0;
}

:deep(.font-semibold) {
    word-break: break-word;
    overflow-wrap: anywhere;
}
</style>