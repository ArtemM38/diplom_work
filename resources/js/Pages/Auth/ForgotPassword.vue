<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import FormErrorsAlert from '@/Components/FormErrorsAlert.vue';
import { Head, useForm } from '@inertiajs/vue3';

defineProps({
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <GuestLayout>
        <Head title="Восстановление пароля" />

        <div class="mb-4 text-sm text-gray-600">
            Укажите email — мы отправим ссылку для сброса пароля.
        </div>

        <div
            v-if="status"
            class="mb-4 text-sm font-medium text-green-600 bg-green-50 p-3 rounded-lg border border-green-200"
        >
            {{ status }}
        </div>

        <form @submit.prevent="submit">
            <FormErrorsAlert :errors="form.errors" class="mb-4" />

            <div>
                <InputLabel for="email" value="Email" />
                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    v-model="form.email"
                    :invalid="!!form.errors.email"
                    required
                    autofocus
                    autocomplete="username"
                />
                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div class="mt-4 flex items-center justify-end">
                <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                    Отправить ссылку
                </PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>
