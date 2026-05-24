<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue';
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
const showCreate = ref(false);
const showHosts = ref(false);

const form = useForm({
    name: '',
    cost: 0,
    event_type_id: '',
    event_level_id: '',
    event_place: '',
    event_host_id: '',
    event_date: '',
    event_period: '',
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

watch(search, debounce(() => {
    router.get(route('admin.events'), { search: search.value || null }, { preserveState: true, replace: true });
}, 300));

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
</script>

<template>
    <Head title="Мероприятия" />
    <AuthenticatedLayout>
        <template #header>Мероприятия</template>

        <div class="space-y-6">
            <div class="flex flex-wrap gap-3 items-center justify-between">
                <input v-model="search" type="text" placeholder="Поиск мероприятия..." class="border-gray-300 rounded-lg w-64" />
                <div class="flex gap-2">
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
                    <div class="md:col-span-2">
                        <label class="text-xs text-slate-500">Наименование</label>
                        <input v-model="form.name" required class="w-full border-gray-300 rounded-lg" />
                    </div>
                    <div>
                        <label class="text-xs text-slate-500">Стоимость (₽)</label>
                        <input v-model="form.cost" type="number" min="0" step="0.01" required class="w-full border-gray-300 rounded-lg" />
                    </div>
                    <div>
                        <label class="text-xs text-slate-500">Тип</label>
                        <select v-model="form.event_type_id" required class="w-full border-gray-300 rounded-lg">
                            <option value="">—</option>
                            <option v-for="t in eventTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
                        </select>
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
                        <label class="text-xs text-slate-500">Дата</label>
                        <input v-model="form.event_date" type="date" class="w-full border-gray-300 rounded-lg" />
                    </div>
                    <div>
                        <label class="text-xs text-slate-500">Период (если несколько дней)</label>
                        <input v-model="form.event_period" class="w-full border-gray-300 rounded-lg" placeholder="01.03–05.03.2026" />
                    </div>
                    <div class="md:col-span-3">
                        <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded-lg font-semibold" :disabled="form.processing">Создать</button>
                    </div>
                </form>
            </div>

            <div v-if="showHosts && !readOnly" class="bg-white p-6 rounded-xl border border-slate-100">
                <h3 class="font-bold mb-4">Ведущие мероприятий</h3>
                <form @submit.prevent="saveHost" class="grid md:grid-cols-6 gap-3 mb-4">
                    <input v-model="hostForm.full_name" placeholder="ФИО" required class="border-gray-300 rounded-lg md:col-span-2" />
                    <input v-model="hostForm.birth_date" type="date" placeholder="Дата рождения" class="border-gray-300 rounded-lg" />
                    <input v-model="hostForm.rank" placeholder="Спорт. разряд" class="border-gray-300 rounded-lg" />
                    <input v-model="hostForm.city" placeholder="Город" class="border-gray-300 rounded-lg" />
                    <input v-model="hostForm.extra_info" placeholder="Доп. информация" class="border-gray-300 rounded-lg md:col-span-2" />
                    <button type="submit" class="bg-emerald-600 text-white rounded-lg px-3 text-sm font-medium">{{ hostForm.id ? 'Сохранить' : 'Добавить' }}</button>
                </form>
                <div class="space-y-2">
                    <div v-for="host in eventHosts" :key="host.id" class="flex justify-between items-center p-3 rounded-lg border border-slate-100 text-sm">
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

            <div class="bg-white rounded-xl border border-slate-100 overflow-hidden">
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
                            <td class="px-4 py-3">{{ ev.event_date || ev.event_period || '—' }}</td>
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
