<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useForm, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import debounce from 'lodash/debounce';

const props = defineProps({ groups: Array, filters: Object });
const search = ref(props.filters?.search || '');

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
    router.patch(route('admin.groups.update', group.id), {
        name: draft.name,
        type: draft.type,
        tariff_amount: draft.tariff_amount,
        status: draft.status,
    }, {
        onSuccess: () => cancelEdit(),
    });
};

const removeGroup = (groupId) => {
    if (confirm('Удалить группу полностью?')) {
        router.delete(route('admin.groups.destroy', groupId));
    }
};

watch(search, debounce((value) => {
    router.get(route('admin.groups'), { search: value }, { preserveState: true, replace: true });
}, 300));
</script>

<template>
    <AuthenticatedLayout>
        <template #header>Управление группами</template>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Форма создания -->
            <div class="bg-white p-6 rounded-xl shadow-sm h-fit">
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
            <div class="md:col-span-2 space-y-4">
                <input v-model="search" type="text" placeholder="Поиск групп..."
                    class="w-full border-gray-300 rounded-lg" />
                <div v-for="group in groups" :key="group.id"
                    class="bg-white p-6 rounded-xl shadow-sm flex justify-between items-center gap-4">
                    <div class="flex-1">
                        <template v-if="editingId === group.id">
                            <input v-model="draft.name" class="text-lg font-bold text-slate-800 border-gray-200 rounded w-full" />
                            <p class="text-sm text-gray-500 mt-2">
                                <input v-model="draft.type" class="border-gray-200 rounded w-40 mr-2" />
                                <input v-model="draft.tariff_amount" type="number" class="border-gray-200 rounded w-32 mr-2" />
                                руб/тренировка
                            </p>
                        </template>
                        <template v-else>
                            <h4 class="text-lg font-bold text-slate-800">{{ group.name }}</h4>
                            <p class="text-sm text-gray-500">{{ group.type }} • {{ group.tariff_amount }} руб/мес</p>
                        </template>
                        <span class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full mt-2 inline-block">
                            Спортсменов: {{ group.athletes_count }}
                        </span>
                    </div>
                    <div class="flex gap-2">
                        <template v-if="editingId === group.id">
                            <button @click="updateGroup(group)" class="bg-emerald-100 text-emerald-700 px-3 py-2 rounded-lg">Сохранить</button>
                            <button @click="cancelEdit" class="bg-gray-100 text-gray-700 px-3 py-2 rounded-lg">Отмена</button>
                        </template>
                        <button v-else @click="startEdit(group)" class="bg-indigo-100 text-indigo-700 px-3 py-2 rounded-lg">Редактировать</button>
                        <button @click="removeGroup(group.id)" class="bg-red-100 text-red-700 px-3 py-2 rounded-lg">Удалить</button>
                        <Link :href="route('admin.groups.show', group.id)"
                            class="bg-slate-100 hover:bg-slate-200 p-2 rounded-lg transition">
                            Управлять составом →
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>