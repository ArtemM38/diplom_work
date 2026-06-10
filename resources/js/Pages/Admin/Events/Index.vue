<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import DateInput from '@/Components/DateInput.vue';
import { formatDisplayDate } from '@/utils/formatDate';
import FormErrorsAlert from '@/Components/FormErrorsAlert.vue';
import InputError from '@/Components/InputError.vue';
import { fieldClass } from '@/utils/formErrors';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import debounce from 'lodash/debounce';

const props = defineProps({
    events: Object,
    eventTypes: Array,
    eventLevels: Array,
    eventHosts: Array,
    readOnly: Boolean,
    filters: Object,
});

const search = ref(props.filters?.search || '');
const dateFrom = ref(props.filters?.date_from || '');
const dateTo = ref(props.filters?.date_to || '');
const eventTypeId = ref(props.filters?.event_type_id || '');
const eventLevelId = ref(props.filters?.event_level_id || '');
const showCreate = ref(false);
const showHosts = ref(false);

const filterParams = () => ({
    search: search.value || null,
    date_from: dateFrom.value || null,
    date_to: dateTo.value || null,
    event_type_id: eventTypeId.value || null,
    event_level_id: eventLevelId.value || null,
});

const applyFilters = () => {
    router.get(route('admin.events'), filterParams(), { preserveState: true, replace: true });
};

const form = useForm({
    name: '',
    cost: 0,
    event_type_id: '',
    event_level_id: '',
    event_place: '',
    event_host_id: '',
    event_date: '',
    event_date_to: '',
    status: 'planned',
});

const hostForm = useForm({
    id: null,
    full_name: '',
    birth_date: '',
    rank: '',
    city: '',
    extra_info: '',
});

watch(search, debounce(applyFilters, 300));

const submitEvent = () => {
    form.post(route('admin.events.store'), {
        onSuccess: () => {
            form.reset();
            showCreate.value = false;
        },
    });
};

const saveHost = () => {
    if (hostForm.id) {
        hostForm.patch(route('admin.events.hosts.update', hostForm.id), { onSuccess: () => hostForm.reset() });
        return;
    }
    hostForm.post(route('admin.events.hosts.store'), { onSuccess: () => hostForm.reset() });
};

const editHost = (host) => {
    hostForm.id = host.id;
    hostForm.full_name = host.full_name ?? '';
    hostForm.birth_date = host.birth_date ?? '';
    hostForm.rank = host.rank ?? '';
    hostForm.city = host.city ?? '';
    hostForm.extra_info = host.extra_info ?? '';
};

const removeHost = (id) => {
    if (!confirm('Удалить ведущего?')) return;
    router.delete(route('admin.events.hosts.destroy', id));
};

const statusLabel = (s) => (s === 'completed' ? 'Проведено' : 'Запланировано');
const eventsList = () => props.events?.data ?? [];

const showCitySuggestions = ref(false);
const citySuggestions = ref([]);

const fetchCitySuggestions = debounce(async (value) => {
    if (!value || value.length < 2) {
        citySuggestions.value = [];
        return;
    }
    try {
        const response = await fetch(`${route('address.suggest-city')}?query=${encodeURIComponent(value)}`);
        const data = await response.json();
        citySuggestions.value = data.suggestions || [];
    } catch {
        citySuggestions.value = [];
    }
}, 300);

const onCityInput = (event) => {
    hostForm.city = event.target.value;
    showCitySuggestions.value = true;
    fetchCitySuggestions(hostForm.city);
};

const pickCity = (value) => {
    hostForm.city = value;
    showCitySuggestions.value = false;
    citySuggestions.value = [];
};
</script>

<template>
    <Head title="Мероприятия" />
    <AuthenticatedLayout>
        <template #header>Мероприятия</template>

        <div class="space-y-6">
            <div class="flex flex-wrap gap-3 items-end justify-between">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 flex-1 min-w-0">
                    <input v-model="search" type="text" placeholder="Поиск мероприятия..." class="border-gray-300 rounded-lg w-full" />
                    <DateInput v-model="dateFrom" label="Дата с" input-class="w-full border-gray-300 rounded-lg" />
                    <DateInput v-model="dateTo" label="Дата по" input-class="w-full border-gray-300 rounded-lg" />
                    <div>
                        <label class="text-xs text-slate-500">Тип</label>
                        <select v-model="eventTypeId" class="w-full border-gray-300 rounded-lg" @change="applyFilters">
                            <option value="">Все</option>
                            <option v-for="t in eventTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-slate-500">Уровень</label>
                        <select v-model="eventLevelId" class="w-full border-gray-300 rounded-lg" @change="applyFilters">
                            <option value="">Все</option>
                            <option v-for="l in eventLevels" :key="l.id" :value="l.id">{{ l.name }}</option>
                        </select>
                    </div>
                </div>
                <button type="button" @click="applyFilters" class="px-4 py-2 border rounded-lg text-sm shrink-0">Применить</button>
                <div class="flex gap-2 shrink-0">
                    <button v-if="!readOnly" type="button" @click="showHosts = !showHosts" class="px-4 py-2 border rounded-lg text-sm">
                        {{ showHosts ? 'Скрыть ведущих' : 'Ведущие' }}
                    </button>
                    <button v-if="!readOnly" type="button" @click="showCreate = !showCreate" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-semibold">
                        + Новое мероприятие
                    </button>
                </div>
            </div>

            <div v-if="showCreate && !readOnly" class="bg-white p-6 rounded-xl border border-indigo-100 shadow-sm">
                <h3 class="font-bold mb-4">Создать мероприятие</h3>
                <form @submit.prevent="submitEvent" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div class="md:col-span-3">
                        <FormErrorsAlert :errors="form.errors" />
                    </div>
                    <div class="md:col-span-2">
                        <label class="text-xs text-slate-500">Наименование *</label>
                        <input v-model="form.name" required :class="fieldClass(form.errors, 'name', 'w-full rounded-lg')" />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div>
                        <label class="text-xs text-slate-500">Стоимость (₽) *</label>
                        <input v-model="form.cost" type="number" min="0" step="1" required :class="fieldClass(form.errors, 'cost', 'w-full rounded-lg')" />
                        <InputError :message="form.errors.cost" />
                    </div>
                    <div>
                        <label class="text-xs text-slate-500">Тип *</label>
                        <select v-model="form.event_type_id" required :class="fieldClass(form.errors, 'event_type_id', 'w-full rounded-lg')">
                            <option value="">—</option>
                            <option v-for="t in eventTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
                        </select>
                        <InputError :message="form.errors.event_type_id" />
                    </div>
                    <div>
                        <label class="text-xs text-slate-500">Уровень</label>
                        <select v-model="form.event_level_id" class="w-full border-gray-300 rounded-lg">
                            <option value="">—</option>
                            <option v-for="l in eventLevels" :key="l.id" :value="l.id">{{ l.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-slate-500">Ведущий</label>
                        <select v-model="form.event_host_id" class="w-full border-gray-300 rounded-lg">
                            <option value="">—</option>
                            <option v-for="h in eventHosts" :key="h.id" :value="h.id">{{ h.full_name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-slate-500">Место проведения</label>
                        <input v-model="form.event_place" class="w-full border-gray-300 rounded-lg" />
                    </div>
                    <div>
                        <DateInput
                            v-model="form.event_date"
                            label="Дата начала *"
                            required
                            :errors="form.errors"
                            error-key="event_date"
                            :error="form.errors.event_date"
                            input-class="w-full rounded-lg"
                        />
                    </div>
                    <div>
                        <DateInput
                            v-model="form.event_date_to"
                            label="Дата окончания"
                            :errors="form.errors"
                            error-key="event_date_to"
                            :error="form.errors.event_date_to"
                            input-class="w-full rounded-lg"
                        />
                        <p class="text-[10px] text-slate-400 mt-1">Оставьте пустым для однодневного мероприятия</p>
                    </div>
                    <div class="md:col-span-3">
                        <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded-lg font-semibold" :disabled="form.processing">Создать</button>
                    </div>
                </form>
            </div>

            <div v-if="showHosts && !readOnly" class="bg-white p-6 rounded-xl border border-slate-100">
                <h3 class="font-bold mb-4">Ведущие мероприятий</h3>
                <form @submit.prevent="saveHost" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3 mb-4">
                    <input v-model="hostForm.full_name" placeholder="ФИО" required class="border-gray-300 rounded-lg md:col-span-2" />
                    <DateInput v-model="hostForm.birth_date" label="Дата рождения ведущего" input-class="w-full border-gray-300 rounded-lg" />
                    <input v-model="hostForm.rank" placeholder="Спорт. разряд" class="border-gray-300 rounded-lg" />
                    <div class="relative">
                        <input
                            :value="hostForm.city"
                            placeholder="Город (DaData)"
                            class="border-gray-300 rounded-lg w-full"
                            autocomplete="off"
                            @input="onCityInput"
                            @focus="showCitySuggestions = true"
                            @blur="setTimeout(() => (showCitySuggestions = false), 120)"
                        />
                        <ul
                            v-if="showCitySuggestions && citySuggestions.length"
                            class="absolute z-20 mt-1 w-full bg-white border border-slate-200 rounded-lg shadow-lg max-h-48 overflow-auto text-sm"
                        >
                            <li
                                v-for="(item, idx) in citySuggestions"
                                :key="idx"
                                class="px-3 py-2 hover:bg-indigo-50 cursor-pointer"
                                @mousedown.prevent="pickCity(item.value)"
                            >
                                {{ item.value }}
                            </li>
                        </ul>
                    </div>
                    <input v-model="hostForm.extra_info" placeholder="Доп. информация" class="border-gray-300 rounded-lg md:col-span-2" />
                    <button type="submit" class="bg-emerald-600 text-white rounded-lg px-3 text-sm font-medium">{{ hostForm.id ? 'Сохранить' : 'Добавить' }}</button>
                </form>
                <div class="space-y-2">
                    <div v-for="host in eventHosts" :key="host.id" class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-2 p-3 rounded-lg border border-slate-100 text-sm">
                        <div>
                            <b>{{ host.full_name }}</b>
                            <span class="text-slate-500 ml-2">{{ host.rank }} · {{ host.city }}</span>
                            <span v-if="host.birth_date" class="text-slate-400 ml-1">({{ host.birth_date }})</span>
                        </div>
                        <div class="flex gap-2">
                            <button type="button" @click="editHost(host)" class="text-indigo-600 text-xs">Изменить</button>
                            <button type="button" @click="removeHost(host.id)" class="text-red-600 text-xs">Удалить</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-slate-100 overflow-hidden app-table-wrap">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left">Мероприятие</th>
                            <th class="px-4 py-3 text-left">Тип</th>
                            <th class="px-4 py-3 text-left">Уровень</th>
                            <th class="px-4 py-3 text-left">Дата</th>
                            <th class="px-4 py-3 text-left">Участники</th>
                            <th class="px-4 py-3 text-left">Статус</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="ev in eventsList()" :key="ev.id" class="border-t hover:bg-slate-50">
                            <td class="px-4 py-3 font-medium">{{ ev.name }}</td>
                            <td class="px-4 py-3">{{ ev.event_type?.name }}</td>
                            <td class="px-4 py-3">{{ ev.event_level?.name || '—' }}</td>
                            <td class="px-4 py-3">{{ ev.event_date_range_display || ev.event_date_display || formatDisplayDate(ev.event_date) || '—' }}</td>
                            <td class="px-4 py-3">{{ ev.participants_count }}</td>
                            <td class="px-4 py-3">
                                <span class="text-xs px-2 py-0.5 rounded-full" :class="ev.status === 'completed' ? 'bg-emerald-100 text-emerald-800' : 'bg-blue-100 text-blue-800'">
                                    {{ statusLabel(ev.status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <Link :href="route('admin.events.show', ev.id)" class="text-indigo-600 font-medium">Открыть →</Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <Pagination class="p-4" :links="events.links" :meta="events" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
