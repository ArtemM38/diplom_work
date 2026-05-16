<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';
import debounce from 'lodash/debounce';

const props = defineProps({
    athletes: Object,
    selectedAthlete: Object,
    history: [Object, Array],
    filters: Object,
});

const search = ref(props.filters?.search || '');
const athleteId = ref(props.filters?.athlete_id || '');
const userActive = ref(props.filters?.user_active || 'all');
const operation = ref(props.filters?.operation || 'all');
const dateFrom = ref(props.filters?.date_from || '');
const dateTo = ref(props.filters?.date_to || '');
const historySort = ref(props.filters?.history_sort || 'desc');

const athletesList = computed(() => props.athletes?.data ?? []);
const historyList = computed(() => (props.history?.data ? props.history.data : props.history ?? []));

const form = useForm({
    amount: 0,
    operation: 'add',
    reason: '',
});

const reload = () => {
    router.get(route('admin.finance'), {
        search: search.value || null,
        athlete_id: athleteId.value || null,
        user_active: userActive.value,
        operation: operation.value,
        date_from: dateFrom.value || null,
        date_to: dateTo.value || null,
        history_sort: historySort.value,
    }, { preserveState: true, replace: true });
};

watch(search, debounce(reload, 300));
watch([athleteId, userActive], reload);
watch([operation, dateFrom, dateTo, historySort], reload);

const selectAthlete = (id) => {
    athleteId.value = athleteId.value === id ? '' : id;
};

const save = () => {
    if (!athleteId.value) return;
    form.patch(route('admin.finance.update', athleteId.value));
};
</script>

<template>
    <Head title="Финансы" />
    <AuthenticatedLayout>
        <template #header>Финансы спортсменов</template>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <div class="lg:col-span-4 bg-white p-6 rounded-xl shadow-sm border border-slate-100">
                <input v-model="search" class="w-full border-gray-300 rounded-lg mb-3" placeholder="Поиск спортсмена..." />
                <select v-model="userActive" class="w-full border-gray-300 rounded-lg mb-3 text-sm">
                    <option value="all">Все аккаунты</option>
                    <option value="active">Активные пользователи</option>
                    <option value="inactive">Неактивные пользователи</option>
                </select>
                <div class="space-y-2 max-h-[520px] overflow-y-auto">
                    <button
                        v-for="item in athletesList"
                        :key="item.id"
                        @click="selectAthlete(item.id)"
                        class="w-full text-left p-3 rounded-lg border transition"
                        :class="athleteId === item.id ? 'border-indigo-400 bg-indigo-50' : 'border-gray-200 hover:border-indigo-300'"
                    >
                        <div class="font-semibold">{{ item.full_name }}</div>
                        <div class="text-xs text-gray-500 flex justify-between gap-2">
                            <span>Баланс: {{ item.balance }}</span>
                            <span v-if="item.user_active === false" class="text-red-500">неактивен</span>
                            <span v-else-if="item.user_active === true" class="text-emerald-600">активен</span>
                        </div>
                    </button>
                </div>
                <Pagination class="mt-3" :links="athletes.links" :meta="athletes" />
            </div>

            <div class="lg:col-span-8 space-y-4">
                <div v-if="!selectedAthlete" class="bg-white p-6 rounded-xl shadow-sm text-gray-500 border border-slate-100">
                    Выберите спортсмена для редактирования баланса.
                </div>
                <template v-else>
                    <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-100">
                        <h3 class="font-bold mb-4">{{ selectedAthlete.full_name }}</h3>
                        <div class="grid md:grid-cols-4 gap-3">
                            <div>
                                <label class="text-xs text-gray-500">Текущий баланс</label>
                                <input :value="selectedAthlete.balance" type="number" step="0.01" class="w-full border-gray-300 rounded-lg bg-gray-100" readonly />
                            </div>
                            <div>
                                <label class="text-xs text-gray-500">Операция</label>
                                <select v-model="form.operation" class="w-full border-gray-300 rounded-lg">
                                    <option value="add">Пополнить</option>
                                    <option value="subtract">Списать</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-xs text-gray-500">Сумма</label>
                                <input v-model="form.amount" type="number" step="0.01" min="0.01" class="w-full border-gray-300 rounded-lg" />
                            </div>
                            <div>
                                <label class="text-xs text-gray-500">Причина</label>
                                <input v-model="form.reason" class="w-full border-gray-300 rounded-lg" />
                            </div>
                        </div>
                        <button @click="save" class="mt-4 bg-indigo-600 text-white px-4 py-2 rounded-lg font-semibold">Сохранить</button>
                    </div>

                    <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-100">
                        <div class="flex flex-wrap items-end justify-between gap-3 mb-4">
                            <h3 class="font-bold">История баланса</h3>
                            <div class="flex flex-wrap gap-2">
                                <select v-model="operation" class="border-gray-300 rounded-lg text-sm">
                                    <option value="all">Все операции</option>
                                    <option value="add">Пополнения</option>
                                    <option value="subtract">Списания</option>
                                </select>
                                <input v-model="dateFrom" type="date" class="border-gray-300 rounded-lg text-sm" />
                                <input v-model="dateTo" type="date" class="border-gray-300 rounded-lg text-sm" />
                                <select v-model="historySort" class="border-gray-300 rounded-lg text-sm">
                                    <option value="desc">Сначала новые</option>
                                    <option value="asc">Сначала старые</option>
                                </select>
                            </div>
                        </div>
                        <div v-if="!historyList.length" class="text-sm text-gray-400">Записей нет</div>
                        <div v-else class="space-y-2">
                            <div v-for="item in historyList" :key="item.id" class="border rounded-lg p-3 text-sm">
                                <div class="flex justify-between">
                                    <span>{{ item.created_at }}</span>
                                    <b :class="item.change_amount < 0 ? 'text-red-600' : 'text-emerald-600'">{{ item.change_amount }}</b>
                                </div>
                                <div class="text-xs text-gray-600">{{ item.reason }}</div>
                                <div class="text-xs text-gray-500">Баланс: {{ item.balance_before }} → {{ item.balance_after }}</div>
                            </div>
                        </div>
                        <Pagination v-if="history?.links" class="mt-4" :links="history.links" :meta="history" />
                    </div>
                </template>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
