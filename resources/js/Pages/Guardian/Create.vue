<script setup>
import { useForm, Head } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import FormErrorsAlert from '@/Components/FormErrorsAlert.vue';
import { fieldClass } from '@/utils/formErrors';
import { ref, watch } from 'vue';
import debounce from 'lodash/debounce';
import axios from 'axios';

const props = defineProps({
    prefilledFullName: {
        type: String,
        default: '',
    },
    hasGuardianProfile: {
        type: Boolean,
        default: false,
    },
});

const form = useForm({
    full_name: props.prefilledFullName || '',
    phone: '',
    relation: 'Отец',
    athlete_id: null,
});

const athleteSearch = ref('');
const athletes = ref([]);
const searchLoading = ref(false);
const selectedAthlete = ref(null);

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

const fetchAthletes = debounce(async (query) => {
    searchLoading.value = true;
    try {
        const { data } = await axios.get(route('guardian.athletes.search'), {
            params: { q: query },
        });
        athletes.value = data;
    } finally {
        searchLoading.value = false;
    }
}, 300);

watch(athleteSearch, (value) => {
    fetchAthletes(value);
});

fetchAthletes('');

const selectAthlete = (athlete) => {
    selectedAthlete.value = athlete;
    form.athlete_id = athlete.id;
};

const clearAthlete = () => {
    selectedAthlete.value = null;
    form.athlete_id = null;
};

const submit = () => {
    form.transform((data) => ({
        ...data,
        link_later: false,
    })).post(route('guardian.store'));
};

const submitWithoutAthlete = () => {
    form.transform((data) => ({
        ...data,
        athlete_id: null,
        link_later: true,
    })).post(route('guardian.store'));
};
</script>

<template>
    <Head title="Данные представителя" />
    <div class="py-12 bg-gray-100 min-h-screen px-4">
        <div class="max-w-lg mx-auto bg-white p-6 sm:p-8 shadow-xl rounded-2xl">
            <div class="text-center mb-6">
                <h2 class="text-2xl font-bold text-gray-800">Анкета родителя</h2>
                <p class="text-sm text-gray-500 mt-1">
                    Укажите контакты и выберите зарегистрированного спортсмена (ребёнка)
                </p>
            </div>

            <form @submit.prevent="submit" class="space-y-5">
                <FormErrorsAlert :errors="form.errors" />

                <div>
                    <InputLabel value="Ваше ФИО" />
                    <TextInput
                        v-model="form.full_name"
                        class="w-full"
                        required
                        placeholder="Иванов Иван Иванович"
                        :invalid="!!form.errors.full_name"
                    />
                    <InputError :message="form.errors.full_name" />
                </div>
                <div>
                    <InputLabel value="Ваш телефон" />
                    <TextInput
                        v-model="form.phone"
                        class="w-full"
                        required
                        placeholder="+7 (___) ___-__-__"
                        :invalid="!!form.errors.phone"
                        @input="onPhoneInput"
                    />
                    <InputError :message="form.errors.phone" />
                </div>
                <div>
                    <InputLabel value="Кем вы являетесь ребёнку?" />
                    <select
                        v-model="form.relation"
                        :class="fieldClass(form.errors, 'relation', 'w-full rounded-lg shadow-sm')"
                    >
                        <option value="Отец">Отец</option>
                        <option value="Мать">Мать</option>
                        <option value="Опекун">Опекун</option>
                    </select>
                    <InputError :message="form.errors.relation" />
                </div>

                <div class="border-t border-slate-100 pt-5">
                    <InputLabel value="Спортсмен (зарегистрированный аккаунт)" />
                    <p class="text-xs text-slate-500 mb-2">
                        Найдите ребёнка по фамилии, имени или email аккаунта спортсмена
                    </p>
                    <TextInput
                        v-model="athleteSearch"
                        type="search"
                        class="w-full"
                        placeholder="Поиск спортсмена…"
                        autocomplete="off"
                    />
                    <InputError :message="form.errors.athlete_id" class="mt-2" />

                    <div
                        v-if="selectedAthlete"
                        class="mt-3 flex items-start justify-between gap-2 rounded-xl border border-indigo-200 bg-indigo-50 p-3"
                    >
                        <div class="min-w-0">
                            <p class="font-semibold text-indigo-900 break-words">{{ selectedAthlete.full_name }}</p>
                            <p v-if="selectedAthlete.birth_date" class="text-xs text-indigo-700 mt-1">
                                Дата рождения: {{ selectedAthlete.birth_date }}
                            </p>
                            <p v-if="selectedAthlete.email" class="text-xs text-indigo-600 mt-1 break-all">
                                {{ selectedAthlete.email }}
                            </p>
                        </div>
                        <button
                            type="button"
                            class="shrink-0 text-xs text-slate-600 underline"
                            @click="clearAthlete"
                        >
                            Сменить
                        </button>
                    </div>

                    <div v-else class="mt-2 max-h-56 overflow-y-auto rounded-xl border border-slate-200 divide-y divide-slate-100">
                        <p v-if="searchLoading" class="p-3 text-sm text-slate-500">Поиск…</p>
                        <p v-else-if="!athletes.length" class="p-3 text-sm text-slate-500">
                            Спортсмены не найдены. Убедитесь, что ребёнок уже зарегистрировался и заполнил анкету.
                        </p>
                        <button
                            v-for="athlete in athletes"
                            :key="athlete.id"
                            type="button"
                            class="w-full text-left p-3 hover:bg-slate-50 transition"
                            @click="selectAthlete(athlete)"
                        >
                            <p class="font-medium text-slate-800 break-words">{{ athlete.full_name }}</p>
                            <p class="text-xs text-slate-500 mt-0.5">
                                <span v-if="athlete.birth_date">р. {{ athlete.birth_date }}</span>
                                <span v-if="athlete.email"> · {{ athlete.email }}</span>
                            </p>
                        </button>
                    </div>
                </div>

                <PrimaryButton class="w-full justify-center py-3 text-lg bg-blue-600" :disabled="form.processing || !form.athlete_id">
                    {{ hasGuardianProfile ? 'Привязать спортсмена' : 'Завершить регистрацию' }}
                </PrimaryButton>

                <div class="relative flex items-center py-1">
                    <div class="flex-grow border-t border-slate-200"></div>
                    <span class="flex-shrink mx-3 text-xs text-slate-400">или</span>
                    <div class="flex-grow border-t border-slate-200"></div>
                </div>

                <button
                    type="button"
                    class="w-full py-3 px-4 rounded-xl border-2 border-dashed border-slate-300 text-slate-700 text-sm font-medium hover:border-indigo-400 hover:text-indigo-700 hover:bg-indigo-50/50 transition disabled:opacity-50"
                    :disabled="form.processing"
                    @click="submitWithoutAthlete"
                >
                    Ребёнок ещё не зарегистрировался
                </button>
                <p class="text-xs text-center text-slate-500">
                    Сохраним ваш профиль. Привязать ребёнка можно позже в личном кабинете.
                </p>
            </form>
        </div>
    </div>
</template>
