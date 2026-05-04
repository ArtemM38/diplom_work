<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const inventoryLabels = {
    weapon_case: 'Чехол для оружия',
    jo: 'Дзё',
    boken: 'Бокен',
    tanto: 'Танто',
    tshirt: 'Футболка',
    olympic_jacket: 'Олимпийка',
    cap: 'Бейсболка',
    backpack: 'Рюкзак',
    shoe_bag: 'Мешок для сменки',
    budo_passport: 'Будо-паспорт',
    qual_book: 'Зачетная книжка',
    referee_book: 'Книжка судьи',
};

const formatInventory = (inventory) => {
    if (!inventory) return 'Нет данных';
    const keys = Object.keys(inventoryLabels).filter((key) => Number(inventory[key]) === 1 || inventory[key] === true);
    return keys.length ? keys.map((key) => inventoryLabels[key]).join(', ') : 'Нет выданного инвентаря';
};

defineProps({
    athlete: Object,
    age: Number,
    ageLabel: String,
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
                    <p class="text-sm text-gray-500">Возраст: {{ ageLabel || `${age} лет` }} | Пол: {{ athlete.gender === 'male' ? 'Мужской' : 'Женский' }}</p>
                </div>
                <Link :href="route('athlete.edit', athlete.id)" class="bg-indigo-100 text-indigo-700 px-3 py-2 rounded-lg">Редактировать</Link>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                <div><b>Телефон:</b> {{ athlete.phone || '—' }}</div>
                <div><b>Дата рождения:</b> {{ athlete.birth_date || '—' }}</div>
                <div><b>ФИО (род.п):</b> {{ athlete.full_name_gen || '—' }}</div>
                <div><b>ФИО (дат.п):</b> {{ athlete.full_name_dat || '—' }}</div>
                <div><b>ФИО (тв.п):</b> {{ athlete.full_name_ins || '—' }}</div>
                <div class="md:col-span-2"><b>Адрес:</b> {{ athlete.registration_address || '—' }}</div>
                <div><b>Школа:</b> {{ athlete.school_name || '—' }}</div>
                <div><b>Директор (дат.п):</b> {{ athlete.school_director_dat || '—' }}</div>
                <div><b>Класс:</b> {{ athlete.school_class || '—' }}</div>
                <div><b>Работа:</b> {{ athlete.work_place || '—' }}</div>
                <div><b>Должность:</b> {{ athlete.work_position || '—' }}</div>
            </div>

            <div>
                <h3 class="font-semibold mb-2">Законные представители</h3>
                <div class="space-y-1 text-sm">
                    <div v-for="guardian in athlete.guardians" :key="guardian.id">
                        {{ guardian.full_name }} — {{ guardian.phone || 'без телефона' }}
                    </div>
                    <div v-if="!athlete.guardians?.length" class="text-gray-400">Нет данных</div>
                </div>
            </div>

            <div>
                <h3 class="font-semibold mb-2">Разряды и категории</h3>
                <div class="space-y-1 text-sm">
                    <div v-for="item in athlete.rank_histories" :key="`rank-${item.id}`">
                        Разряд: {{ item.rank?.name || '—' }} ({{ item.assigned_at || 'дата не указана' }})
                    </div>
                    <div v-for="item in athlete.referee_histories" :key="`ref-${item.id}`">
                        Судейская: {{ item.referee_category?.name || '—' }} ({{ item.assigned_at || 'дата не указана' }})
                    </div>
                    <div v-if="!athlete.rank_histories?.length && !athlete.referee_histories?.length" class="text-gray-400">Нет данных</div>
                </div>
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
                        <span v-if="doc.series || doc.number"> | {{ doc.series || '' }} {{ doc.number || '' }}</span>
                        <span v-if="doc.issued_by"> | {{ doc.issued_by }}</span>
                    </div>
                    <div v-if="!athlete.documents?.length" class="text-gray-400">Нет документов</div>
                </div>
            </div>

            <div>
                <h3 class="font-semibold mb-2">Инвентарь</h3>
                <div class="text-sm">
                    {{ formatInventory(athlete.inventory) }}
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
