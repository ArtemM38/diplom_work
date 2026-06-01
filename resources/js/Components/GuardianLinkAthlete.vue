<script setup>
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import { useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import debounce from 'lodash/debounce';
import axios from 'axios';

const athleteSearch = ref('');
const athletes = ref([]);
const searchLoading = ref(false);
const selectedAthlete = ref(null);

const form = useForm({
    athlete_id: null,
});

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

const attach = () => {
    form.post(route('guardian.athletes.attach'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 space-y-4 text-left">
        <div>
            <h3 class="text-lg font-bold text-slate-900">Привязать ребёнка</h3>
            <p class="text-sm text-slate-500 mt-1">
                Найдите спортсмена по фамилии, имени или email зарегистрированного аккаунта
            </p>
        </div>

        <div>
            <InputLabel value="Поиск спортсмена" />
            <TextInput
                v-model="athleteSearch"
                type="search"
                class="w-full mt-1"
                placeholder="Начните вводить ФИО или email…"
                autocomplete="off"
            />
            <InputError :message="form.errors.athlete_id" class="mt-2" />
        </div>

        <div
            v-if="selectedAthlete"
            class="flex items-start justify-between gap-2 rounded-xl border border-indigo-200 bg-indigo-50 p-3"
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
            <button type="button" class="shrink-0 text-xs text-slate-600 underline" @click="clearAthlete">
                Сменить
            </button>
        </div>

        <div v-else class="max-h-52 overflow-y-auto rounded-xl border border-slate-200 divide-y divide-slate-100">
            <p v-if="searchLoading" class="p-3 text-sm text-slate-500">Поиск…</p>
            <p v-else-if="!athletes.length" class="p-3 text-sm text-slate-500">
                Спортсмены не найдены. Дождитесь, пока ребёнок зарегистрируется и заполнит анкету.
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

        <PrimaryButton
            class="w-full justify-center"
            :disabled="form.processing || !form.athlete_id"
            @click="attach"
        >
            {{ form.processing ? 'Привязка…' : 'Привязать выбранного спортсмена' }}
        </PrimaryButton>
    </div>
</template>
