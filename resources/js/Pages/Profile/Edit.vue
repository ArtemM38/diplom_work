<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
    profileData: {
        type: Object,
    },
});

const editMode = ref(false);
</script>

<template>
    <Head title="Profile" />

    <AuthenticatedLayout>
        <template #header>
            <h2
                class="text-xl font-semibold leading-tight text-gray-800"
            >
                Profile
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <div class="bg-white p-4 shadow sm:rounded-lg sm:p-8">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-medium text-gray-900">Профиль</h3>
                        <button
                            @click="editMode = !editMode"
                            class="px-3 py-2 rounded-lg text-sm"
                            :class="editMode ? 'bg-gray-100 text-gray-700' : 'bg-indigo-100 text-indigo-700'"
                        >
                            {{ editMode ? 'Закрыть редактирование' : 'Редактировать' }}
                        </button>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                        <div><b>Имя:</b> {{ props.profileData?.user?.name }}</div>
                        <div><b>Email:</b> {{ props.profileData?.user?.email }}</div>
                        <div><b>Роль:</b> {{ props.profileData?.user?.role }}</div>
                        <div v-if="props.profileData?.athlete"><b>Телефон:</b> {{ props.profileData.athlete.phone || '—' }}</div>
                        <div v-if="props.profileData?.athlete"><b>Дата рождения:</b> {{ props.profileData.athlete.birth_date || '—' }}</div>
                        <div v-if="props.profileData?.athlete" class="md:col-span-2"><b>Адрес:</b> {{ props.profileData.athlete.registration_address || '—' }}</div>
                        <div v-if="props.profileData?.guardian"><b>ФИО родителя:</b> {{ props.profileData.guardian.full_name }}</div>
                        <div v-if="props.profileData?.guardian"><b>Телефон родителя:</b> {{ props.profileData.guardian.phone }}</div>
                    </div>
                </div>

                <div
                    v-if="editMode"
                    class="bg-white p-4 shadow sm:rounded-lg sm:p-8"
                >
                    <UpdateProfileInformationForm
                        :must-verify-email="mustVerifyEmail"
                        :status="status"
                        class="max-w-xl"
                    />
                </div>

                <div
                    v-if="editMode"
                    class="bg-white p-4 shadow sm:rounded-lg sm:p-8"
                >
                    <UpdatePasswordForm class="max-w-xl" />
                </div>

                <div
                    v-if="editMode"
                    class="bg-white p-4 shadow sm:rounded-lg sm:p-8"
                >
                    <DeleteUserForm class="max-w-xl" />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
