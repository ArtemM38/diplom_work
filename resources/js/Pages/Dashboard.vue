<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps({
    athlete: Object
});

// Функция для проверки срока документов
const isExpiring = (date) => {
    if (!date) return false;
    const expiry = new Date(date);
    const today = new Date();
    const diffTime = expiry - today;
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    return diffDays < 14; // Предупреждать за 2 недели
};
</script>

<template>

    <Head title="Личный кабинет" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Мой профиль</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <!-- Основная карточка -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 flex items-center gap-6">
                    <img :src="athlete?.photo ? '/storage/' + athlete.photo : 'https://ui-avatars.com/api/?name=' + athlete?.first_name_nom"
                        class="w-24 h-24 rounded-full object-cover border-4 border-indigo-100">
                    <div>
                        <h3 class="text-2xl font-bold">{{ athlete?.last_name_nom }} {{ athlete?.first_name_nom }}</h3>
                        <p class="text-gray-500">Дата рождения: {{ athlete?.birth_date }}</p>
                        <div class="mt-2 inline-block bg-indigo-600 text-white text-xs px-3 py-1 rounded-full">
                            {{ athlete?.rank_histories[0]?.rank?.name || 'Без разряда' }}
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Виджет Документы -->
                    <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                        <h4 class="font-bold mb-4 border-b pb-2">Статус документов</h4>
                        <div v-for="doc in athlete?.documents" :key="doc.id"
                            class="flex justify-between items-center mb-2">
                            <span class="capitalize">{{ doc.type === 'medical' ? 'Медсправка' : 'Страховка' }}</span>
                            <span :class="isExpiring(doc.expiry_date) ? 'text-red-600 font-bold' : 'text-green-600'">
                                до {{ doc.expiry_date }}
                            </span>
                        </div>
                        <div v-if="!athlete?.documents?.length" class="text-gray-400 text-sm">Документы не загружены
                        </div>
                    </div>

                    <!-- Виджет Инвентарь -->
                    <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                        <h4 class="font-bold mb-4 border-b pb-2">Моя экипировка</h4>
                        <div class="flex flex-wrap gap-2">
                            <span v-if="athlete?.inventory?.jo" class="bg-gray-100 px-2 py-1 rounded text-sm">Дзё</span>
                            <span v-if="athlete?.inventory?.boken"
                                class="bg-gray-100 px-2 py-1 rounded text-sm">Бокен</span>
                            <span v-if="athlete?.inventory?.tshirt"
                                class="bg-gray-100 px-2 py-1 rounded text-sm">Футболка</span>
                            <span v-else class="text-gray-400 text-sm">Данных об инвентаре нет</span>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>