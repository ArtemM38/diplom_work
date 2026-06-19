<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({
    mustVerifyEmail: { type: Boolean },
    status: { type: String },
    hideName: { type: Boolean, default: false },
});

const user = usePage().props.auth.user;

const form = useForm({
    name: user.name,
    email: user.email,
});

const normalizeEmail = (event) => {
    form.email = (event.target.value || '').trim().toLowerCase();
};
</script>

<template>
    <form @submit.prevent="form.patch(route('profile.update'))" class="space-y-5">
        <div v-if="!hideName">
            <InputLabel for="name" value="ФИО в аккаунте" />
            <TextInput
                id="name"
                type="text"
                class="mt-1.5 block w-full rounded-xl border-slate-300"
                v-model="form.name"
                required
                autocomplete="name"
                :invalid="!!form.errors.name"
            />
            <InputError class="mt-2" :message="form.errors.name" />
        </div>

        <div>
            <InputLabel for="login" value="Логин" />
            <TextInput
                id="login"
                type="text"
                class="mt-1.5 block w-full rounded-xl border-slate-300 bg-slate-50"
                :model-value="user.login"
                disabled
            />
            <p class="mt-1 text-xs text-slate-500">Логин нельзя изменить. Он используется для входа в систему.</p>
        </div>

        <div>
            <InputLabel for="email" value="Электронная почта" />
            <TextInput
                id="email"
                type="email"
                class="mt-1.5 block w-full rounded-xl border-slate-300"
                v-model="form.email"
                :invalid="!!form.errors.email"
                @input="normalizeEmail"
                required
                autocomplete="username"
            />
            <InputError class="mt-2" :message="form.errors.email" />
        </div>

        <p v-if="hideName" class="text-sm text-slate-500 -mt-2">
            ФИО редактируется в блоке «Законный представитель» слева.
        </p>

        <div v-if="mustVerifyEmail && user.email_verified_at === null" class="rounded-xl bg-amber-50 border border-amber-100 p-4 text-sm text-amber-900">
            <p>Адрес почты не подтверждён.</p>
            <Link
                :href="route('verification.send')"
                method="post"
                as="button"
                class="mt-2 font-medium text-indigo-700 underline hover:text-indigo-900"
            >
                Отправить письмо повторно
            </Link>
            <p v-show="status === 'verification-link-sent'" class="mt-2 text-green-700 font-medium">
                Ссылка для подтверждения отправлена на вашу почту.
            </p>
        </div>

        <div class="flex items-center gap-3 pt-1">
            <PrimaryButton :disabled="form.processing" class="rounded-xl px-5">
                Сохранить изменения
            </PrimaryButton>
            <Transition
                enter-active-class="transition ease-in-out"
                enter-from-class="opacity-0"
                leave-active-class="transition ease-in-out"
                leave-to-class="opacity-0"
            >
                <span v-if="form.recentlySuccessful" class="text-sm font-medium text-emerald-600">
                    Сохранено
                </span>
            </Transition>
        </div>
    </form>
</template>
