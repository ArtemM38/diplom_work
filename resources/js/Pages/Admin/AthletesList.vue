<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, Link } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import debounce from 'lodash/debounce';

const props = defineProps({
    athletes: Array,
    filters: Object
});

const search = ref(props.filters.search);
const gender = ref(props.filters.gender);
const sortAge = ref(props.filters.sort_age || '');
const startedFrom = ref(props.filters.started_from || '');
const startedTo = ref(props.filters.started_to || '');

// Автоматический поиск при вводе (с задержкой 300мс)
const updateSearch = debounce(() => {
    router.get(route('admin.athletes'), { 
        search: search.value, 
        gender: gender.value,
        sort_age: sortAge.value || null,
        started_from: startedFrom.value || null,
        started_to: startedTo.value || null,
    }, { 
        preserveState: true, 
        replace: true 
    });
}, 300);

watch([search, gender, sortAge, startedFrom, startedTo], updateSearch);

const getDocClass = (doc) => {
    if (doc.is_expired) return 'bg-red-100 text-red-700 border-red-200';
    if (doc.is_warning) return 'bg-yellow-100 text-yellow-700 border-yellow-200';
    return 'bg-green-100 text-green-700 border-green-200';
};
</script>

<template>
    <Head title="Реестр спортсменов" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Реестр спортсменов</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                
                <!-- Фильтры -->
                <div class="mb-6 flex flex-wrap gap-4 items-center justify-between bg-white p-4 rounded-lg shadow-sm">
                    <div class="flex gap-4 items-center flex-wrap">
                        <input 
                            v-model="search" 
                            type="text" 
                            placeholder="Поиск по фамилии..." 
                            class="border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 w-64"
                        />
                        <select v-model="gender" class="border-gray-300 rounded-md shadow-sm focus:ring-indigo-500">
                            <option :value="null">Все полы</option>
                            <option value="male">Мужской</option>
                            <option value="female">Женский</option>
                        </select>
                        <select v-model="sortAge" class="border-gray-300 rounded-md shadow-sm focus:ring-indigo-500">
                            <option value="">Возраст: по умолчанию</option>
                            <option value="asc">Возраст: от младших</option>
                            <option value="desc">Возраст: от старших</option>
                        </select>
                        <input v-model="startedFrom" type="date" class="border-gray-300 rounded-md shadow-sm focus:ring-indigo-500" />
                        <input v-model="startedTo" type="date" class="border-gray-300 rounded-md shadow-sm focus:ring-indigo-500" />
                    </div>
                    <div class="text-sm text-gray-500">
                        Найдено: {{ athletes.length }} спортсменов
                    </div>
                </div>

                <!-- Таблица -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Спортсмен</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Возраст</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Разряд</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Документы</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Инвентарь</th>
                                <th class="px-6 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="athlete in athletes" :key="athlete.id" class="hover:bg-gray-50 transition">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10">
                                            <img class="h-10 w-10 rounded-full object-cover" 
                                                 :src="athlete.photo ? '/storage/'+athlete.photo : `https://ui-avatars.com/api/?name=${athlete.full_name}`" />
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900">{{ athlete.full_name }}</div>
                                            <div class="text-sm text-gray-500">{{ athlete.phone }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">{{ athlete.age_label }}</div>
                                    <div class="text-xs text-gray-400">{{ athlete.birth_date }}</div>
                                    <div class="text-xs text-gray-400">Начал(а): {{ athlete.started_at || '—' }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-indigo-100 text-indigo-800">
                                        {{ athlete.current_rank }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex gap-1">
                                        <span v-for="doc in athlete.documents" :key="doc.type" 
                                              :title="'Действует до ' + doc.expiry_date"
                                              class="px-2 py-0.5 text-[10px] border rounded uppercase font-bold"
                                              :class="getDocClass(doc)">
                                            {{ doc.type.substring(0, 3) }}
                                        </span>
                                        <span v-if="!athlete.documents.length" class="text-xs text-gray-300 italic">Нет данных</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                 {{ athlete.inventory_count }} ед.
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <Link :href="route('admin.athletes.show', athlete.id)" class="text-slate-600 hover:text-slate-900 mr-3">Просмотр</Link>
                                    <Link :href="route('athlete.edit', athlete.id)" class="text-indigo-600 hover:text-indigo-900 mr-3">Редактировать</Link>
                                    <button class="text-gray-400 hover:text-gray-600">PDF</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <div v-if="athletes.length === 0" class="p-8 text-center text-gray-500 italic">
                        Ничего не найдено...
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>