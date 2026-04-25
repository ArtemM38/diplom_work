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
            <h2 class="text-xl font-bold">Выберите тип аккаунта</h2>
            <div class="grid grid-cols-2 gap-4 mt-4">
                <div @click="form.role = 'athlete'"
                    :class="form.role === 'athlete' ? 'border-blue-500 bg-blue-50' : 'border-gray-200'"
                    class="cursor-pointer border-2 p-4 rounded-xl transition-all">
                    <span class="text-2xl">🥋</span>
                    <p class="font-bold text-sm">Спортсмен</p>
                </div>
                <div @click="form.role = 'guardian'"
                    :class="form.role === 'guardian' ? 'border-blue-500 bg-blue-50' : 'border-gray-200'"
                    class="cursor-pointer border-2 p-4 rounded-xl transition-all">
                    <span class="text-2xl">👨‍👩‍👦</span>
                    <p class="font-bold text-sm">Родитель</p>
                </div>
            </div>
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <div>
                <InputLabel for="name" value="Логин (ФИО)" />
                <TextInput id="name" type="text" class="mt-1 block w-full" v-model="form.name" required autofocus />
                <InputError class="mt-2" :message="form.errors.name" />
            </div>
            <div>
                <InputLabel for="email" value="Email" />
                <TextInput id="email" type="email" class="mt-1 block w-full" v-model="form.email" @input="normalizeEmail"
                    autocomplete="email" required />
                <InputError class="mt-2" :message="form.errors.email" />
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <InputLabel for="password" value="Пароль" />
                    <TextInput id="password" type="password" class="mt-1 block w-full" v-model="form.password"
                        required />
                </div>
                <div>
                    <InputLabel for="password_confirmation" value="Повтор" />
                    <TextInput id="password_confirmation" type="password" class="mt-1 block w-full"
                        v-model="form.password_confirmation" required />
                </div>
            </div>
            <div class="flex items-center justify-between mt-6">
                <Link :href="route('login')" class="text-sm text-gray-600 underline">Есть аккаунт?</Link>
                <PrimaryButton :disabled="form.processing">Создать аккаунт</PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>