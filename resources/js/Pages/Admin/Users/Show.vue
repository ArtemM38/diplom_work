<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    profileUser: Object,
    athlete: Object,
    guardian: Object,
    children: Array,
});

const childName = (child) => `${child.last_name_nom} ${child.first_name_nom} ${child.middle_name_nom || ''}`.trim();
</script>

<template>
    <Head :title="`Профиль: ${profileUser.display_name}`" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('admin.coaches')" class="text-indigo-600 text-sm">← Аккаунты</Link>
                <span>Профиль пользователя</span>
            </div>
        </template>

        <div class="max-w-3xl mx-auto space-y-6">
            <div class="bg-white rounded-2xl border border-slate-100 p-6 shadow-sm">
                <h1 class="text-2xl font-bold text-slate-900">{{ profileUser.display_name }}</h1>
                <p class="text-slate-500 mt-1">{{ profileUser.email }}</p>
                <p class="mt-2 text-sm"><span class="text-slate-500">Роли:</span> {{ profileUser.role_labels }}</p>
                <p class="text-sm mt-1">
                    <span class="text-slate-500">Статус:</span>
                    <span :class="profileUser.is_active ? 'text-emerald-600' : 'text-red-600'">
                        {{ profileUser.is_active ? 'Активен' : 'Неактивен' }}
                    </span>
                </p>
            </div>

            <div v-if="guardian" class="bg-white rounded-2xl border p-6">
                <h2 class="font-bold mb-3">Законный представитель</h2>
                <p>{{ guardian.full_name }} · {{ guardian.relation }}</p>
                <p class="text-sm text-slate-500">{{ guardian.phone }}</p>
            </div>

            <div v-if="children?.length" class="bg-white rounded-2xl border p-6">
                <h2 class="font-bold mb-3">Дети</h2>
                <div v-for="child in children" :key="child.id" class="flex justify-between py-2 border-b last:border-0">
                    <span>{{ childName(child) }}</span>
                    <Link :href="route('admin.athletes.show', child.id)" class="text-indigo-600 text-sm">Карточка</Link>
                </div>
            </div>

            <div v-if="athlete" class="bg-white rounded-2xl border p-6">
                <h2 class="font-bold mb-3">Спортсмен</h2>
                <p>{{ childName(athlete) }}</p>
                <Link :href="route('admin.athletes.show', athlete.id)" class="text-indigo-600 text-sm mt-2 inline-block">Открыть карточку</Link>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
