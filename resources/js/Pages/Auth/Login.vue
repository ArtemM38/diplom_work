<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

// Проверка: есть ли вообще какие-либо ошибки в форме
const hasErrors = computed(() => Object.keys(form.errors).length > 0);

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <GuestLayout>

        <Head title="Вход в систему" />

        <!-- Заголовок и логотип -->
        <div class="mb-8 text-center">
            <h1 class="text-2xl font-bold mt-4 text-slate-900">АЙКИДО</h1>
            <p class="text-sm text-slate-500 mt-1">Войдите в свой аккаунт</p>
        </div>

        <!-- Успешный статус (например, после сброса пароля) -->
        <div v-if="status"
            class="mb-4 text-sm font-medium text-green-600 text-center bg-green-50 p-3 rounded-lg border border-green-200">
            {{ status }}
        </div>

        <form @submit.prevent="submit">
            <!-- Email -->
            <div>
                <InputLabel for="email" value="Email" class="text-gray-700" />
                <TextInput id="email" type="email"
                    class="mt-1 block w-full bg-gray-50 border-gray-300 focus:ring-indigo-500 focus:border-indigo-500 rounded-lg transition-colors"
                    :class="{ 'border-red-500 bg-red-50': form.errors.email }" v-model="form.email" required autofocus
                    placeholder="example@mail.ru" autocomplete="username" />
                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <!-- Пароль -->
            <div class="mt-4">
                <div class="flex justify-between items-center">
                    <InputLabel for="password" value="Пароль" class="text-gray-700" />
                    <Link v-if="canResetPassword" :href="route('password.request')"
                        class="text-xs text-indigo-600 hover:text-indigo-500 underline">
                        Забыли пароль?
                    </Link>
                </div>
                <TextInput id="password" type="password"
                    class="mt-1 block w-full bg-gray-50 border-gray-300 focus:ring-indigo-500 focus:border-indigo-500 rounded-lg transition-colors"
                    :class="{ 'border-red-500 bg-red-50': form.errors.password }" v-model="form.password" required
                    placeholder="••••••••" autocomplete="current-password" />
                <InputError class="mt-2" :message="form.errors.password" />
            </div>
            <!-- Кнопка входа -->
            <div class="mt-8">
                <PrimaryButton
                    class="w-full justify-center py-3 text-base font-semibold bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 transition duration-150 ease-in-out shadow-md"
                    :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                    {{ form.processing ? 'Вход...' : 'Войти в систему' }}
                </PrimaryButton>
            </div>

            <!-- Блок регистрации -->
            <div class="mt-8 pt-6 border-t border-gray-100 text-center">
                <p class="text-sm text-gray-600">
                    Еще нет аккаунта?
                    <Link :href="route('register')"
                        class="font-bold text-indigo-600 hover:text-indigo-500 transition duration-150 ease-in-out ml-1">
                        Зарегистрироваться
                    </Link>
                </p>
            </div>
        </form>
    </GuestLayout>
</template>