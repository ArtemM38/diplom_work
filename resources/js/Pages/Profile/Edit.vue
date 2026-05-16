<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    mustVerifyEmail: { type: Boolean },
    status: { type: String },
    profileData: { type: Object },
});

const editMode = ref(false);

const guardianForm = useForm({
    full_name: props.profileData?.guardian?.full_name || '',
    phone: props.profileData?.guardian?.phone || '',
    relation: props.profileData?.guardian?.relation || 'Отец',
});

const saveGuardian = () => {
    guardianForm.patch(route('profile.guardian.update'));
};

const childName = (child) => `${child.last_name_nom} ${child.first_name_nom} ${child.middle_name_nom || ''}`.trim();
</script>

<template>
    <Head title="Профиль" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Профиль</h2>
        </template>

        <div class="py-8 max-w-4xl mx-auto space-y-6">
            <div class="bg-white p-6 shadow-sm rounded-2xl border border-slate-100">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Аккаунт</h3>
                    <button
                        @click="editMode = !editMode"
                        class="px-3 py-2 rounded-lg text-sm"
                        :class="editMode ? 'bg-gray-100 text-gray-700' : 'bg-indigo-100 text-indigo-700'"
                    >
                        {{ editMode ? 'Закрыть' : 'Редактировать' }}
                    </button>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                    <div><b>ФИО:</b> {{ profileData?.user?.name }}</div>
                    <div><b>Email:</b> {{ profileData?.user?.email }}</div>
                    <div><b>Роль:</b> {{ profileData?.user?.role_label || profileData?.user?.role }}</div>
                </div>
            </div>

            <div v-if="profileData?.guardian" class="bg-white p-6 shadow-sm rounded-2xl border border-slate-100">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Данные законного представителя</h3>
                <form @submit.prevent="saveGuardian" class="grid md:grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm text-gray-600">ФИО</label>
                        <input v-model="guardianForm.full_name" class="w-full mt-1 rounded-lg border-slate-300" required />
                    </div>
                    <div>
                        <label class="text-sm text-gray-600">Телефон</label>
                        <input v-model="guardianForm.phone" class="w-full mt-1 rounded-lg border-slate-300" required />
                    </div>
                    <div>
                        <label class="text-sm text-gray-600">Кем приходитесь ребёнку</label>
                        <select v-model="guardianForm.relation" class="w-full mt-1 rounded-lg border-slate-300">
                            <option value="Отец">Отец</option>
                            <option value="Мать">Мать</option>
                            <option value="Опекун">Опекун</option>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium" :disabled="guardianForm.processing">
                            Сохранить данные
                        </button>
                    </div>
                </form>
            </div>

            <div v-if="profileData?.children?.length" class="bg-white p-6 shadow-sm rounded-2xl border border-slate-100">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Привязанные дети</h3>
                <div class="space-y-3">
                    <div
                        v-for="child in profileData.children"
                        :key="child.id"
                        class="flex items-center justify-between p-4 rounded-xl bg-slate-50 border border-slate-100"
                    >
                        <div>
                            <p class="font-semibold">{{ childName(child) }}</p>
                            <p class="text-sm text-slate-500">{{ child.birth_date }} · {{ child.phone || 'без телефона' }}</p>
                        </div>
                        <a :href="route('athlete.edit', child.id)" class="text-indigo-600 text-sm font-medium hover:underline">Редактировать</a>
                    </div>
                </div>
            </div>

            <div v-if="profileData?.athlete" class="bg-white p-6 shadow-sm rounded-2xl border border-slate-100 text-sm space-y-2">
                <h3 class="text-lg font-medium text-gray-900 mb-2">Данные спортсмена</h3>
                <p><b>Телефон:</b> {{ profileData.athlete.phone || '—' }}</p>
                <p><b>Дата рождения:</b> {{ profileData.athlete.birth_date || '—' }}</p>
                <p><b>Адрес:</b> {{ profileData.athlete.registration_address || '—' }}</p>
                <a :href="route('athlete.edit', profileData.athlete.id)" class="inline-block mt-2 text-indigo-600 hover:underline">Редактировать анкету</a>
            </div>

            <div v-if="editMode" class="bg-white p-6 shadow-sm rounded-2xl border border-slate-100">
                <UpdateProfileInformationForm :must-verify-email="mustVerifyEmail" :status="status" class="max-w-xl" />
            </div>
            <div v-if="editMode" class="bg-white p-6 shadow-sm rounded-2xl border border-slate-100">
                <UpdatePasswordForm class="max-w-xl" />
            </div>
            <div v-if="editMode" class="bg-white p-6 shadow-sm rounded-2xl border border-slate-100">
                <DeleteUserForm class="max-w-xl" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
