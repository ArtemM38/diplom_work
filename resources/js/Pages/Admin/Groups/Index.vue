<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import FormErrorsAlert from '@/Components/FormErrorsAlert.vue';
import InputError from '@/Components/InputError.vue';
import { fieldClass } from '@/utils/formErrors';
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

const isArchived = (group) => group.status === 'archived' || group.deleted_at;

const removeGroup = (group) => {
    if (isArchived(group)) {
        return;
    }
    const msg = 'Удалить группу? Если были тренировки или списания, группа будет перенесена в архив.';
    if (confirm(msg)) {
        router.delete(route('admin.groups.destroy', group.id));
    }
};

const restoreGroup = (group) => {
    if (confirm(`Восстановить группу «${group.name}» из архива?`)) {
        router.post(route('admin.groups.restore', group.id));
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
                    <FormErrorsAlert :errors="form.errors" />
                    <div>
                        <label class="text-sm">Название</label>
                        <input v-model="form.name" type="text" :class="fieldClass(form.errors, 'name', 'w-full rounded-lg')" />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div>
                        <label class="text-sm">Тип</label>
                        <select v-model="form.type" :class="fieldClass(form.errors, 'type', 'w-full rounded-lg')">
                            <option value="Учебная">Учебная</option>
                            <option value="Семинар">Семинар</option>
                            <option value="Аттестация">Аттестация</option>
                            <option value="Спортивные сборы">Спортивные сборы</option>
                            <option value="Соревнования">Соревнования</option>
                            <option value="Интенсивные тренировки">Интенсивные тренировки</option>
                            <option value="Индивидуальные тренировки">Индивидуальные тренировки</option>
                        </select>
                        <InputError :message="form.errors.type" />
                    </div>
                    <div>
                        <label class="text-sm">Стоимость за тренировку (руб)</label>
                        <input v-model="form.tariff_amount" type="number" min="0" step="0.01" :class="fieldClass(form.errors, 'tariff_amount', 'w-full rounded-lg')" />
                        <InputError :message="form.errors.tariff_amount" />
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
                    class="bg-white p-4 sm:p-6 rounded-xl shadow-sm flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
                    <div class="flex-1 min-w-0">
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
                    <div class="flex flex-col sm:flex-row flex-wrap gap-2 w-full sm:w-auto shrink-0">
                        <template v-if="isArchived(group)">
                            <button
                                v-if="!tariffOnlyMode"
                                type="button"
                                @click="restoreGroup(group)"
                                class="bg-emerald-100 text-emerald-800 px-3 py-2 rounded-lg text-sm w-full sm:w-auto font-medium"
                            >
                                Восстановить
                            </button>
                        </template>
                        <template v-else>
                            <template v-if="editingId === group.id">
                                <button @click="updateGroup(group)" class="bg-emerald-100 text-emerald-700 px-3 py-2 rounded-lg text-sm w-full sm:w-auto">Сохранить</button>
                                <button @click="cancelEdit" class="bg-gray-100 text-gray-700 px-3 py-2 rounded-lg text-sm w-full sm:w-auto">Отмена</button>
                            </template>
                            <button
                                v-else
                                @click="tariffOnlyMode ? startTariffEdit(group) : startEdit(group)"
                                class="bg-indigo-100 text-indigo-700 px-3 py-2 rounded-lg text-sm w-full sm:w-auto"
                            >
                                {{ tariffOnlyMode ? 'Изменить стоимость' : 'Редактировать' }}
                            </button>
                            <button v-if="!tariffOnlyMode" @click="removeGroup(group)" class="bg-red-100 text-red-700 px-3 py-2 rounded-lg text-sm w-full sm:w-auto">Удалить</button>
                            <Link v-if="!tariffOnlyMode" :href="route('admin.groups.show', group.id)"
                                class="bg-slate-100 hover:bg-slate-200 px-3 py-2 rounded-lg transition text-sm text-center w-full sm:w-auto">
                                Управлять составом →
                            </Link>
                        </template>
                    </div>
                </div>
                <Pagination :links="groups.links" :meta="groups" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>