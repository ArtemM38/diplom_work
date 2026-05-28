<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import FormErrorsAlert from '@/Components/FormErrorsAlert.vue';
import InputError from '@/Components/InputError.vue';
import { fieldClass } from '@/utils/formErrors';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';
import debounce from 'lodash/debounce';

const props = defineProps({
    athletes: Object,
    selectedAthlete: Object,
    history: [Object, Array],
    filters: Object,
    canManageDiscount: { type: Boolean, default: false },
});

const search = ref(props.filters?.search || '');
const athleteId = ref(props.filters?.athlete_id || '');
const userActive = ref(props.filters?.user_active || 'all');
const operation = ref(props.filters?.operation || 'all');
const dateFrom = ref(props.filters?.date_from || '');
const dateTo = ref(props.filters?.date_to || '');
const historySort = ref(props.filters?.history_sort || 'desc');
const balanceFilter = ref(props.filters?.balance || 'all');

const athletesList = computed(() => props.athletes?.data ?? []);
const historyList = computed(() => (props.history?.data ? props.history.data : props.history ?? []));

const showMobileList = computed(() => !athleteId.value);

const form = useForm({
    amount: 0,
    operation: 'add',
    reason: '',
});

const discountForm = useForm({
    discount_percent: null,
});

watch(() => props.selectedAthlete, (athlete) => {
    discountForm.discount_percent = athlete?.discount_percent ?? null;
}, { immediate: true });

const reload = () => {
    router.get(route('admin.finance'), {
        search: search.value || null,
        athlete_id: athleteId.value || null,
        user_active: userActive.value,
        operation: operation.value,
        date_from: dateFrom.value || null,
        date_to: dateTo.value || null,
        history_sort: historySort.value,
        balance: balanceFilter.value,
    }, { preserveState: true, replace: true });
};

watch(search, debounce(reload, 300));
watch([athleteId, userActive, balanceFilter], reload);
watch([operation, dateFrom, dateTo, historySort], reload);

const selectAthlete = (id) => {
    athleteId.value = athleteId.value === id ? '' : id;
};

const clearAthlete = () => {
    athleteId.value = '';
};

const save = () => {
    if (!athleteId.value) return;
    form.patch(route('admin.finance.update', athleteId.value));
};

const saveDiscount = () => {
    if (!athleteId.value) return;
    discountForm.patch(route('admin.finance.update', athleteId.value), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Финансы" />
    <AuthenticatedLayout>
        <template #header>Финансы</template>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 lg:gap-6 min-w-0">
            <!-- Список спортсменов -->
            <div
                class="lg:col-span-4 bg-white p-4 sm:p-6 rounded-xl shadow-sm border border-slate-100 min-w-0"
                :class="showMobileList ? '' : 'hidden lg:block'"
            >
                <h3 class="font-bold text-slate-800 mb-3 lg:hidden">Выберите спортсмена</h3>
                <input v-model="search" class="w-full border-gray-300 rounded-lg mb-3" placeholder="Поиск спортсмена..." />
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-1 gap-2 mb-3">
                    <select v-model="userActive" class="w-full border-gray-300 rounded-lg text-sm">
                        <option value="all">Все аккаунты</option>
                        <option value="active">Активные</option>
                        <option value="inactive">Неактивные</option>
                    </select>
                    <select v-model="balanceFilter" class="w-full border-gray-300 rounded-lg text-sm">
                        <option value="all">Любой баланс</option>
                        <option value="negative">Отрицательный баланс</option>
                    </select>
                </div>
                <div class="space-y-2 max-h-[min(60vh,520px)] lg:max-h-[520px] overflow-y-auto">
                    <button
                        v-for="item in athletesList"
                        :key="item.id"
                        type="button"
                        @click="selectAthlete(item.id)"
                        class="w-full text-left p-3 rounded-lg border transition"
                        :class="[
                            athleteId === item.id ? 'border-indigo-400 bg-indigo-50 ring-1 ring-indigo-200' : 'border-gray-200 hover:border-indigo-300',
                            item.balance < 0 ? 'border-red-200 bg-red-50/50' : '',
                        ]"
                    >
                        <div class="font-semibold text-slate-900 break-anywhere leading-snug">{{ item.full_name }}</div>
                        <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs">
                            <span :class="item.balance < 0 ? 'text-red-600 font-semibold' : 'text-slate-600'">
                                Баланс: {{ item.balance }} ₽
                            </span>
                            <span v-if="item.user_active === false" class="text-red-500">неактивен</span>
                            <span v-else-if="item.user_active === true" class="text-emerald-600">активен</span>
                        </div>
                    </button>
                </div>
                <Pagination class="mt-3" :links="athletes.links" :meta="athletes" />
            </div>

            <!-- Детали -->
            <div class="lg:col-span-8 space-y-4 min-w-0" :class="showMobileList ? 'hidden lg:block' : ''">
                <div v-if="!selectedAthlete" class="bg-white p-6 rounded-xl shadow-sm text-gray-500 border border-slate-100 text-center">
                    <p class="text-sm">Выберите спортсмена из списка слева</p>
                    <button
                        type="button"
                        class="mt-4 lg:hidden inline-flex px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium"
                        @click="clearAthlete"
                    >
                        Открыть список
                    </button>
                </div>

                <template v-else>
                    <!-- Мобильная шапка выбранного -->
                    <div class="lg:hidden bg-white rounded-xl border border-indigo-100 p-4 shadow-sm">
                        <button
                            type="button"
                            class="text-sm text-indigo-600 font-medium mb-2"
                            @click="clearAthlete"
                        >
                            ← К списку спортсменов
                        </button>
                        <h2 class="text-lg font-bold text-slate-900 break-anywhere">{{ selectedAthlete.full_name }}</h2>
                        <p
                            class="mt-1 text-sm font-semibold"
                            :class="selectedAthlete.balance < 0 ? 'text-red-600' : 'text-emerald-700'"
                        >
                            Баланс: {{ selectedAthlete.balance }} ₽
                        </p>
                    </div>

                    <div class="bg-white p-4 sm:p-6 rounded-xl shadow-sm border border-slate-100">
                        <h3 class="font-bold mb-4 hidden lg:block">{{ selectedAthlete.full_name }}</h3>
                        <p class="lg:hidden text-xs text-slate-500 mb-3">Операция с балансом</p>
                        <FormErrorsAlert :errors="form.errors" class="mb-4" />
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                            <div>
                                <label class="text-xs text-gray-500">Текущий баланс</label>
                                <input :value="selectedAthlete.balance" type="number" step="0.01" class="w-full border-gray-300 rounded-lg bg-gray-100 mt-1" readonly />
                            </div>
                            <div>
                                <label class="text-xs text-gray-500">Операция</label>
                                <select v-model="form.operation" :class="fieldClass(form.errors, 'operation', 'w-full rounded-lg mt-1')">
                                    <option value="add">Пополнить</option>
                                    <option value="subtract">Списать</option>
                                </select>
                                <InputError :message="form.errors.operation" />
                            </div>
                            <div>
                                <label class="text-xs text-gray-500">Сумма</label>
                                <input v-model="form.amount" type="number" step="0.01" min="0.01" :class="fieldClass(form.errors, 'amount', 'w-full rounded-lg mt-1')" />
                                <InputError :message="form.errors.amount" />
                            </div>
                            <div class="sm:col-span-2 lg:col-span-1">
                                <label class="text-xs text-gray-500">Причина *</label>
                                <input v-model="form.reason" :class="fieldClass(form.errors, 'reason', 'w-full rounded-lg mt-1')" placeholder="Комментарий" required />
                                <InputError :message="form.errors.reason" />
                            </div>
                        </div>
                        <button
                            type="button"
                            @click="save"
                            class="mt-4 w-full sm:w-auto bg-indigo-600 text-white px-6 py-2.5 rounded-lg font-semibold"
                        >
                            Сохранить
                        </button>
                    </div>

                    <div v-if="canManageDiscount" class="bg-white p-4 sm:p-6 rounded-xl shadow-sm border border-slate-100">
                        <h3 class="font-bold mb-2">Скидка на тренировки</h3>
                        <p class="text-sm text-slate-500 mb-3">От 10% до 100%. Стоимость в группах пересчитывается автоматически.</p>
                        <div class="flex flex-col sm:flex-row sm:flex-wrap sm:items-end gap-3">
                            <div class="w-full sm:w-36">
                                <label class="text-xs text-gray-500">Скидка, %</label>
                                <input
                                    v-model.number="discountForm.discount_percent"
                                    type="number"
                                    min="10"
                                    max="100"
                                    placeholder="Нет"
                                    class="w-full border-gray-300 rounded-lg mt-1"
                                />
                            </div>
                            <button
                                type="button"
                                @click="saveDiscount"
                                class="w-full sm:w-auto bg-amber-600 text-white px-4 py-2.5 rounded-lg text-sm font-semibold"
                                :disabled="discountForm.processing"
                            >
                                Применить скидку
                            </button>
                            <button
                                type="button"
                                @click="discountForm.discount_percent = null; saveDiscount()"
                                class="w-full sm:w-auto border border-slate-300 px-4 py-2.5 rounded-lg text-sm"
                            >
                                Сбросить
                            </button>
                        </div>
                        <div v-if="selectedAthlete.groups?.length" class="mt-4 space-y-2">
                            <p class="text-sm font-medium text-slate-700">Стоимость по группам</p>
                            <div
                                v-for="g in selectedAthlete.groups"
                                :key="g.id"
                                class="text-sm text-slate-600 rounded-lg bg-slate-50 p-3 flex flex-col gap-1"
                            >
                                <span class="font-medium text-slate-800">{{ g.name }}</span>
                                <span class="text-xs sm:text-sm">
                                    тариф {{ g.tariff_amount }} ₽ →
                                    <b>{{ g.training_price }} ₽</b> за тренировку
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white p-4 sm:p-6 rounded-xl shadow-sm border border-slate-100">
                        <h3 class="font-bold mb-3">История баланса</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mb-4">
                            <select v-model="operation" class="w-full border-gray-300 rounded-lg text-sm">
                                <option value="all">Все операции</option>
                                <option value="add">Пополнения</option>
                                <option value="subtract">Списания</option>
                            </select>
                            <select v-model="historySort" class="w-full border-gray-300 rounded-lg text-sm">
                                <option value="desc">Сначала новые</option>
                                <option value="asc">Сначала старые</option>
                            </select>
                            <input v-model="dateFrom" type="date" class="w-full border-gray-300 rounded-lg text-sm" />
                            <input v-model="dateTo" type="date" class="w-full border-gray-300 rounded-lg text-sm" />
                        </div>
                        <div v-if="!historyList.length" class="text-sm text-gray-400 py-4 text-center">Записей нет</div>
                        <div v-else class="space-y-2">
                            <div
                                v-for="item in historyList"
                                :key="item.id"
                                class="border rounded-lg p-3 text-sm"
                            >
                                <div class="flex flex-wrap justify-between gap-2">
                                    <span class="text-slate-600 text-xs sm:text-sm">{{ item.created_at }}</span>
                                    <b
                                        class="text-base shrink-0"
                                        :class="item.change_amount < 0 ? 'text-red-600' : 'text-emerald-600'"
                                    >
                                        {{ item.change_amount > 0 ? '+' : '' }}{{ item.change_amount }} ₽
                                    </b>
                                </div>
                                <div v-if="item.reason" class="text-xs text-gray-600 mt-1 break-anywhere">{{ item.reason }}</div>
                                <div class="text-xs text-gray-500 mt-1">
                                    {{ item.balance_before }} → {{ item.balance_after }} ₽
                                </div>
                            </div>
                        </div>
                        <Pagination v-if="history?.links" class="mt-4" :links="history.links" :meta="history" />
                    </div>
                </template>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
