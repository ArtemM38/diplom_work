<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    athlete: Object,
    age: Number,
});
</script>

<template>
    <Head :title="`Спортсмен: ${athlete.last_name_nom} ${athlete.first_name_nom}`" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('admin.athletes')" class="text-indigo-600">← Реестр</Link>
                <span>Карточка спортсмена</span>
            </div>
        </template>

        <div class="bg-white p-6 rounded-xl shadow-sm space-y-6">
            <div class="flex items-start justify-between">
                <div>
                    <h2 class="text-2xl font-bold">{{ athlete.last_name_nom }} {{ athlete.first_name_nom }} {{ athlete.middle_name_nom }}</h2>
                    <p class="text-sm text-gray-500">Возраст: {{ age }} | Пол: {{ athlete.gender === 'male' ? 'Мужской' : 'Женский' }}</p>
                </div>
                <Link :href="route('athlete.edit', athlete.id)" class="bg-indigo-100 text-indigo-700 px-3 py-2 rounded-lg">Редактировать</Link>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                <div><b>Телефон:</b> {{ athlete.phone || '—' }}</div>
                <div><b>Дата рождения:</b> {{ athlete.birth_date || '—' }}</div>
                <div class="md:col-span-2"><b>Адрес:</b> {{ athlete.registration_address || '—' }}</div>
                <div><b>Школа:</b> {{ athlete.school_name || '—' }}</div>
                <div><b>Класс:</b> {{ athlete.school_class || '—' }}</div>
                <div><b>Работа:</b> {{ athlete.work_place || '—' }}</div>
                <div><b>Должность:</b> {{ athlete.work_position || '—' }}</div>
            </div>

            <div>
                <h3 class="font-semibold mb-2">Группы</h3>
                <div class="flex flex-wrap gap-2">
                    <span v-for="group in athlete.groups" :key="group.id" class="px-3 py-1 bg-slate-100 rounded-full text-sm">{{ group.name }}</span>
                    <span v-if="!athlete.groups?.length" class="text-sm text-gray-400">Нет групп</span>
                </div>
            </div>

            <div>
                <h3 class="font-semibold mb-2">Документы</h3>
                <div class="space-y-1 text-sm">
                    <div v-for="doc in athlete.documents" :key="doc.id">
                        {{ doc.type }}: {{ doc.issue_date || '—' }} - {{ doc.expiry_date || '—' }}
                    </div>
                    <div v-if="!athlete.documents?.length" class="text-gray-400">Нет документов</div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
