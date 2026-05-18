<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';

const props = defineProps({
    isGuardian: Boolean,
    children: Array,
    selectedAthlete: Object,
    history: Object,
    filters: Object,
});

const athleteId = ref(props.filters?.athlete_id || '');
const operation = ref(props.filters?.operation || 'all');
const dateFrom = ref(props.filters?.date_from || '');
const dateTo = ref(props.filters?.date_to || '');
const historySort = ref(props.filters?.history_sort || 'desc');

const historyList = computed(() => props.history?.data ?? []);

const pageTitle = computed(() => (props.isGuardian ? 'Финансы ребёнка' : 'Мои финансы'));
const headerTitle = computed(() => pageTitle.value);

const formatMoney = (value) => {
    const num = Number(value ?? 0);
    return new Intl.NumberFormat('ru-RU', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(num);
};

const reload = () => {
    router.get(route('finance'), {
        athlete_id: athleteId.value || null,
        operation: operation.value,
        date_from: dateFrom.value || null,
        date_to: dateTo.value || null,
        history_sort: historySort.value,
    }, { preserveState: true, replace: true });
};

const selectChild = (id) => {
    athleteId.value = id;
    reload();
};

watch([operation, dateFrom, dateTo, historySort], reload);
</script>

<template>
    <Head :title="pageTitle" />
    <AuthenticatedLayout>
        <template #header>{{ headerTitle }}</template>

        <div class="max-w-4xl mx-auto space-y-6">
            <div v-if="isGuardian && children?.length > 1" class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4">
                <p class="text-sm text-slate-500 mb-3">Выберите ребёнка</p>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="child in children"
                        :key="child.id"
                        type="button"
                        @click="selectChild(child.id)"
                        class="px-4 py-2 rounded-xl text-sm font-medium border transition"
                        :class="selectedAthlete?.id === child.id
                            ? 'border-indigo-500 bg-indigo-50 text-indigo-800'
                            : 'border-slate-200 text-slate-700 hover:border-indigo-300'"
                    >
                        {{ child.full_name }}
                    </button>
                </div>
            </div>

            <div class="bg-gradient-to-br from-indigo-600 to-violet-600 rounded-2xl shadow-lg text-white p-6 md:p-8">
                <p class="text-indigo-100 text-sm">{{ isGuardian ? 'Баланс счёта ребёнка' : 'Текущий баланс' }}</p>
                <p class="text-3xl md:text-4xl font-bold mt-1 tracking-tight">{{ formatMoney(selectedAthlete?.balance) }} ₽</p>
                <p class="text-indigo-100 text-sm mt-2">{{ selectedAthlete?.full_name }}</p>
            </div>

            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6">
                <div class="flex flex-wrap items-end justify-between gap-3 mb-4">
                    <h3 class="text-lg font-bold text-slate-900">История операций</h3>
                    <div class="flex flex-wrap gap-2">
                        <select v-model="operation" class="border-slate-300 rounded-xl text-sm">
                            <option value="all">Все операции</option>
                            <option value="add">Пополнения</option>
                            <option value="subtract">Списания</option>
                        </select>
                        <input v-model="dateFrom" type="date" class="border-slate-300 rounded-xl text-sm" />
                        <input v-model="dateTo" type="date" class="border-slate-300 rounded-xl text-sm" />
                        <select v-model="historySort" class="border-slate-300 rounded-xl text-sm">
                            <option value="desc">Сначала новые</option>
                            <option value="asc">Сначала старые</option>
                        </select>
                    </div>
                </div>

                <p v-if="!historyList.length" class="text-sm text-slate-400 py-6 text-center">Записей пока нет</p>

                <div v-else class="space-y-3">
                    <div
                        v-for="item in historyList"
                        :key="item.id"
                        class="rounded-xl border border-slate-100 p-4 text-sm hover:border-slate-200 transition"
                    >
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="text-slate-600">{{ item.created_at }}</span>
                            <span
                                class="font-bold text-base"
                                :class="item.change_amount < 0 ? 'text-red-600' : 'text-emerald-600'"
                            >
                                {{ item.change_amount > 0 ? '+' : '' }}{{ formatMoney(item.change_amount) }} ₽
                            </span>
                        </div>
                        <p class="text-slate-800 mt-1">{{ item.reason }}</p>
                        <p class="text-xs text-slate-500 mt-1">
                            Баланс: {{ formatMoney(item.balance_before) }} → {{ formatMoney(item.balance_after) }} ₽
                        </p>
                    </div>
                </div>

                <Pagination v-if="history?.links" class="mt-4" :links="history.links" :meta="history" />
            </div>

            <p class="text-center text-xs text-slate-400">
                Режим просмотра. Для пополнения или списания обратитесь к администратору клуба.
            </p>
        </div>
    </AuthenticatedLayout>
</template>
