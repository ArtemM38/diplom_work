<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: 'athlete',
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};

const normalizeEmail = (event) => {
    form.email = (event.target.value || '').trim().toLowerCase();
};
</script>

<template>
    <GuestLayout>
        <Head title="Регистрация" />

        <div class="mb-6 text-center">
            <h2 class="text-xl font-bold">Регистрация в системе Айкидо</h2>
            <p class="text-sm text-gray-500 mt-1">Заполните данные для создания аккаунта</p>
        </div>

        <form @submit.prevent="submit" class="space-y-5">
            <div>
                <h3 class="text-center font-semibold mb-3 text-sm text-slate-700">Тип аккаунта</h3>
                <div class="grid grid-cols-2 gap-4">
                    <button
                        type="button"
                        @click="form.role = 'athlete'"
                        :class="form.role === 'athlete' ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200'"
                        class="border-2 p-4 rounded-xl transition-all text-center"
                    >
                        <span class="text-2xl">🥋</span>
                        <p class="font-bold text-sm mt-2">Спортсмен</p>
                    </button>
                    <button
                        type="button"
                        @click="form.role = 'guardian'"
                        :class="form.role === 'guardian' ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200'"
                        class="border-2 p-4 rounded-xl transition-all text-center"
                    >
                        <span class="text-2xl">👨‍👩‍👦</span>
                        <p class="font-bold text-sm mt-2">Родитель</p>
                    </button>
                </div>
            </div>

            <div>
                <InputLabel for="name" value="Ваше ФИО" />
                <TextInput
                    id="name"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.name"
                    required
                    autofocus
                    placeholder="Иванов Иван Иванович"
                />
                <InputError class="mt-2" :message="form.errors.name" />
            </div>

            <div>
                <InputLabel for="email" value="Email" />
                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    v-model="form.email"
                    @input="normalizeEmail"
                    autocomplete="email"
                    required
                    placeholder="example@mail.ru"
                />
                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <InputLabel for="password" value="Пароль" />
                    <TextInput
                        id="password"
                        type="password"
                        class="mt-1 block w-full"
                        v-model="form.password"
                        autocomplete="new-password"
                        required
                    placeholder="••••••••"

                    />
                    <InputError class="mt-2" :message="form.errors.password" />
                </div>
                <div>
                    <InputLabel for="password_confirmation" value="Повтор пароля" />
                    <TextInput
                        id="password_confirmation"
                        type="password"
                        class="mt-1 block w-full"
                        v-model="form.password_confirmation"
                        autocomplete="new-password"
                        required
                    placeholder="••••••••"

                    />
                    <InputError class="mt-2" :message="form.errors.password_confirmation" />
                </div>
            </div>

            <div class="flex items-center justify-between pt-2">
                <Link :href="route('login')" class="text-sm text-gray-600 underline hover:text-gray-900">
                    Уже есть аккаунт?
                </Link>
                <PrimaryButton :disabled="form.processing">Создать аккаунт</PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>
