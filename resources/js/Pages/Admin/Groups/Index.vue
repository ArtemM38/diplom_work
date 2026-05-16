<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { useForm, Link, router } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';
import debounce from 'lodash/debounce';

const props = defineProps({
    groups: Object,
    filters: Object,
    canCreateGroups: { type: Boolean, default: true },
    tariffOnlyMode: { type: Boolean, default: false },
});
const search = ref(props.filters?.search || '');
const showArchived = ref(props.filters?.show_archived || false);
const groupsList = computed(() => props.groups?.data ?? []);

const form = useForm({
    name: '',
    type: 'Учебная',
    tariff_amount: 0,
});

const submit = () => {
    form.post(route('admin.groups.store'), {
        onSuccess: () => form.reset(),
    });
};

const editingId = ref(null);
const draft = useForm({
    name: '',
    type: '',
    tariff_amount: 0,
    status: 'active',
});

const startEdit = (group) => {
    editingId.value = group.id;
    draft.name = group.name;
    draft.type = group.type;
    draft.tariff_amount = group.tariff_amount;
    draft.status = group.status || 'active';
};

const cancelEdit = () => {
    editingId.value = null;
    draft.reset();
};

const updateGroup = (group) => {
    const payload = props.tariffOnlyMode
        ? { tariff_amount: draft.tariff_amount }
        : {
            name: draft.name,
            type: draft.type,
            tariff_amount: draft.tariff_amount,
            status: draft.status,
        };
    router.patch(route('admin.groups.update', group.id), payload, {
        onSuccess: () => cancelEdit(),
    });
};

const startTariffEdit = (group) => {
    editingId.value = group.id;
    draft.tariff_amount = group.tariff_amount;
};

const removeGroup = (group) => {
    const msg = group.status === 'archived' || group.deleted_at
        ? 'Группа уже в архиве.'
        : 'Удалить группу? Если были тренировки или списания, группа будет перенесена в архив.';
    if (confirm(msg)) {
        router.delete(route('admin.groups.destroy', group.id));
    }
};

watch(search, debounce(() => {
    router.get(route('admin.groups'), { search: search.value, show_archived: showArchived.value ? '1' : null }, { preserveState: true, replace: true });
}, 300));

watch(showArchived, () => {
    router.get(route('admin.groups'), { search: search.value, show_archived: showArchived.value ? '1' : null }, { preserveState: true, replace: true });
});
</script>

<template>
    <AuthenticatedLayout>
        <template #header>Управление группами</template>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Форма создания -->
            <div v-if="canCreateGroups" class="bg-white p-6 rounded-xl shadow-sm h-fit">
                <h3 class="font-bold mb-4">Создать новую группу</h3>
                <form @submit.prevent="submit" class="space-y-4">
                    <div>
                        <label class="text-sm">Название</label>
                        <input v-model="form.name" type="text" class="w-full border-gray-300 rounded-lg">
                    </div>
                    <div>
                        <label class="text-sm">Тип</label>
                        <select v-model="form.type" class="w-full border-gray-300 rounded-lg">
                            <option value="Учебная">Учебная (У)</option>
                            <option value="Спортивная">Спортивная (С)</option>
                            <option value="Индивидуальная">Индивидуальная</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-sm">Стоимость за тренировку (руб)</label>
                        <input v-model="form.tariff_amount" type="number" class="w-full border-gray-300 rounded-lg">
                    </div>
                    <button class="w-full bg-indigo-600 text-white py-2 rounded-lg font-bold">Создать</button>
                </form>
            </div>

            <!-- Список групп -->
            <div :class="canCreateGroups ? 'md:col-span-2' : 'md:col-span-3'" class="space-y-4">
                <input v-model="search" type="text" placeholder="Поиск групп..."
                    class="w-full border-gray-300 rounded-lg" />
                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <input type="checkbox" v-model="showArchived" />
                    Показать архив
                </label>
                <div v-for="group in groupsList" :key="group.id"
                    class="bg-white p-6 rounded-xl shadow-sm flex justify-between items-center gap-4">
                    <div class="flex-1">
                        <template v-if="editingId === group.id && !tariffOnlyMode">
                            <input v-model="draft.name" class="text-lg font-bold text-slate-800 border-gray-200 rounded w-full" />
                            <p class="text-sm text-gray-500 mt-2">
                                <input v-model="draft.type" class="border-gray-200 rounded w-40 mr-2" />
                                <input v-model="draft.tariff_amount" type="number" class="border-gray-200 rounded w-32 mr-2" />
                                руб/тренировка
                            </p>
                        </template>
                        <template v-else-if="editingId === group.id && tariffOnlyMode">
                            <h4 class="text-lg font-bold text-slate-800">{{ group.name }}</h4>
                            <p class="text-sm text-gray-500 mt-2 flex items-center gap-2">
                                <input v-model="draft.tariff_amount" type="number" class="border-gray-200 rounded w-32" />
                                руб/тренировка
                            </p>
                        </template>
                        <template v-else>
                            <h4 class="text-lg font-bold text-slate-800">{{ group.name }}</h4>
                            <p class="text-sm text-gray-500">{{ group.type }} • {{ group.tariff_amount }} руб/тренировка</p>
                        </template>
                        <span class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full mt-2 inline-block mr-1">
                            Спортсменов: {{ group.athletes_count }}
                        </span>
                        <span v-if="group.status === 'archived' || group.deleted_at" class="text-xs bg-amber-100 text-amber-800 px-2 py-0.5 rounded-full mt-2 inline-block">
                            Архив
                        </span>
                    </div>
                    <div class="flex gap-2">
                        <template v-if="editingId === group.id">
                            <button @click="updateGroup(group)" class="bg-emerald-100 text-emerald-700 px-3 py-2 rounded-lg">Сохранить</button>
                            <button @click="cancelEdit" class="bg-gray-100 text-gray-700 px-3 py-2 rounded-lg">Отмена</button>
                        </template>
                        <button
                            v-else
                            @click="tariffOnlyMode ? startTariffEdit(group) : startEdit(group)"
                            class="bg-indigo-100 text-indigo-700 px-3 py-2 rounded-lg"
                        >
                            {{ tariffOnlyMode ? 'Изменить стоимость' : 'Редактировать' }}
                        </button>
                        <button v-if="!tariffOnlyMode && group.status !== 'archived' && !group.deleted_at" @click="removeGroup(group)" class="bg-red-100 text-red-700 px-3 py-2 rounded-lg">Удалить</button>
                        <Link v-if="!tariffOnlyMode" :href="route('admin.groups.show', group.id)"
                            class="bg-slate-100 hover:bg-slate-200 p-2 rounded-lg transition">
                            Управлять составом →
                        </Link>
                    </div>
                </div>
                <Pagination :links="groups.links" :meta="groups" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>