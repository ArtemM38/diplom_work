<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AvatarZoomable from '@/Components/AvatarZoomable.vue';
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
                <Link :href="route('admin.coaches')" class="text-indigo-600 text-sm hover:underline">← Аккаунты</Link>
                <span>Профиль пользователя</span>
            </div>
        </template>

        <div class="max-w-3xl mx-auto space-y-6 px-4 sm:px-0">
            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="h-20 bg-gradient-to-r from-indigo-600 via-indigo-500 to-violet-500" />
                <div class="px-6 pb-6">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:-mt-10">
                        <AvatarZoomable
                            :src="profileUser.avatar_url"
                            :name="profileUser.display_name"
                            size="lg"
                            shape="rounded"
                        />
                        <div class="flex-1 min-w-0 sm:pt-8">
                            <h1 class="text-2xl mt-4 font-bold text-slate-900">{{ profileUser.display_name }}</h1>
                            <p class="text-slate-500 mt-1">{{ profileUser.email }}</p>
                            <p class="mt-2 text-sm">
                                <span class="text-slate-500">Роли:</span> {{ profileUser.role_labels }}
                            </p>
                            <p class="text-sm mt-1">
                                <span class="text-slate-500">Статус:</span>
                                <span :class="profileUser.is_active ? 'text-emerald-600 font-medium' : 'text-red-600 font-medium'">
                                    {{ profileUser.is_active ? 'Активен' : 'Неактивен' }}
                                </span>
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <div v-if="guardian" class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm">
                <h2 class="font-bold text-slate-900 mb-3">Законный представитель</h2>
                <p>{{ guardian.full_name }} · {{ guardian.relation }}</p>
                <p class="text-sm text-slate-500">{{ guardian.phone }}</p>
            </div>

            <div v-if="children?.length" class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm">
                <h2 class="font-bold text-slate-900 mb-3">Дети</h2>
                <div
                    v-for="child in children"
                    :key="child.id"
                    class="flex justify-between py-2 border-b border-slate-100 last:border-0"
                >
                    <span>{{ childName(child) }}</span>
                    <Link :href="route('admin.athletes.show', child.id)" class="text-indigo-600 text-sm hover:underline">Карточка</Link>
                </div>
            </div>

            <div v-if="athlete" class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm">
                <h2 class="font-bold text-slate-900 mb-3">Спортсмен</h2>
                <p>{{ childName(athlete) }}</p>
                <Link :href="route('admin.athletes.show', athlete.id)" class="text-indigo-600 text-sm mt-2 inline-block hover:underline">Открыть карточку</Link>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
