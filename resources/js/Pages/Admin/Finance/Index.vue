<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import debounce from 'lodash/debounce';

const props = defineProps({
    athletes: Array,
    selectedAthlete: Object,
    history: Array,
    filters: Object,
});

const search = ref(props.filters?.search || '');
const athleteId = ref(props.filters?.athlete_id || '');

const form = useForm({
    amount: 0,
    operation: 'add',
    reason: '',
});

watch(search, debounce((value) => {
    router.get(route('admin.finance'), { search: value, athlete_id: athleteId.value || null }, { preserveState: true, replace: true });
}, 300));

watch(athleteId, (value) => {
    router.get(route('admin.finance'), { search: search.value, athlete_id: value || null }, { preserveState: true, replace: true });
});

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
            <div class="lg:col-span-4 bg-white p-6 rounded-xl shadow-sm">
                <input v-model="search" class="w-full border-gray-300 rounded-lg mb-3" placeholder="Поиск спортсмена..." />
                <div class="space-y-2 max-h-[650px] overflow-y-auto">
                    <button
                        v-for="item in athletes"
                        :key="item.id"
                        @click="selectAthlete(item.id)"
                        class="w-full text-left p-3 rounded-lg border transition"
                        :class="athleteId === item.id ? 'border-indigo-400 bg-indigo-50' : 'border-gray-200 hover:border-indigo-300'"
                    >
                        <div class="font-semibold">{{ item.full_name }}</div>
                        <div class="text-xs text-gray-500">Баланс: {{ item.balance }}</div>
                    </button>
                </div>
            </div>

            <div class="lg:col-span-8 space-y-4">
                <div v-if="!selectedAthlete" class="bg-white p-6 rounded-xl shadow-sm text-gray-500">
                    Выберите спортсмена для редактирования баланса.
                </div>
                <template v-else>
                    <div class="bg-white p-6 rounded-xl shadow-sm">
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
                                <label class="text-xs text-gray-500">Причина изменения</label>
                                <input v-model="form.reason" class="w-full border-gray-300 rounded-lg" />
                            </div>
                        </div>
                        <button @click="save" class="mt-4 bg-indigo-600 text-white px-4 py-2 rounded-lg font-semibold">Сохранить</button>
                    </div>

                    <div class="bg-white p-6 rounded-xl shadow-sm">
                        <h3 class="font-bold mb-4">История баланса</h3>
                        <div v-if="!history.length" class="text-sm text-gray-400">История пока пустая</div>
                        <div v-else class="space-y-2">
                            <div v-for="item in history" :key="item.id" class="border rounded-lg p-3 text-sm">
                                <div class="flex justify-between">
                                    <span>{{ item.created_at }}</span>
                                    <b :class="item.change_amount < 0 ? 'text-red-600' : 'text-emerald-600'">
                                        {{ item.change_amount }}
                                    </b>
                                </div>
                                <div class="text-xs text-gray-600">{{ item.reason }}</div>
                                <div class="text-xs text-gray-500">Баланс: {{ item.balance_before }} → {{ item.balance_after }}</div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
