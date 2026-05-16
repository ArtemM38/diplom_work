<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const step = ref(1);

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: 'athlete',
});

const nextStep = () => {
    if (!form.name.trim()) {
        form.setError('name', 'Укажите ФИО');
        return;
    }
    form.clearErrors('name');
    step.value = 2;
};

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
            <p class="text-sm text-gray-500 mt-1">Шаг {{ step }} из 2</p>
        </div>

        <div v-if="step === 1" class="space-y-6">
            <div>
                <h3 class="text-center font-semibold mb-4">Выберите тип аккаунта</h3>
                <div class="grid grid-cols-2 gap-4">
                    <div
                        @click="form.role = 'athlete'"
                        :class="form.role === 'athlete' ? 'border-blue-500 bg-blue-50' : 'border-gray-200'"
                        class="cursor-pointer border-2 p-4 rounded-xl transition-all text-center"
                    >
                        <span class="text-2xl">🥋</span>
                        <p class="font-bold text-sm mt-2">Спортсмен</p>
                    </div>
                    <div
                        @click="form.role = 'guardian'"
                        :class="form.role === 'guardian' ? 'border-blue-500 bg-blue-50' : 'border-gray-200'"
                        class="cursor-pointer border-2 p-4 rounded-xl transition-all text-center"
                    >
                        <span class="text-2xl">👨‍👩‍👦</span>
                        <p class="font-bold text-sm mt-2">Родитель</p>
                    </div>
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

            <div class="flex items-center justify-between">
                <Link :href="route('login')" class="text-sm text-gray-600 underline">Есть аккаунт?</Link>
                <PrimaryButton type="button" @click="nextStep">Далее</PrimaryButton>
            </div>
        </div>

        <form v-else @submit.prevent="submit" class="space-y-4">
            <div class="rounded-lg bg-slate-50 border border-slate-200 px-4 py-3 text-sm">
                <span class="text-gray-500">ФИО:</span>
                <span class="font-medium ml-1">{{ form.name }}</span>
                <button type="button" class="ml-3 text-indigo-600 underline text-xs" @click="step = 1">Изменить</button>
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
                />
                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <InputLabel for="password" value="Пароль" />
                    <TextInput id="password" type="password" class="mt-1 block w-full" v-model="form.password" required />
                </div>
                <div>
                    <InputLabel for="password_confirmation" value="Повтор" />
                    <TextInput
                        id="password_confirmation"
                        type="password"
                        class="mt-1 block w-full"
                        v-model="form.password_confirmation"
                        required
                    />
                </div>
            </div>

            <div class="flex items-center justify-between mt-6">
                <button type="button" class="text-sm text-gray-600 underline" @click="step = 1">Назад</button>
                <PrimaryButton :disabled="form.processing">Создать аккаунт</PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>
