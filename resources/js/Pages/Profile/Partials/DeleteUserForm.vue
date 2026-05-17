<script setup>
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { nextTick, ref } from 'vue';

const confirmingUserDeletion = ref(false);
const passwordInput = ref(null);

const form = useForm({
    password: '',
});

const confirmUserDeletion = () => {
    confirmingUserDeletion.value = true;
    nextTick(() => passwordInput.value.focus());
};

const deleteUser = () => {
    form.delete(route('profile.destroy'), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: () => passwordInput.value.focus(),
        onFinish: () => form.reset(),
    });
};

const closeModal = () => {
    confirmingUserDeletion.value = false;
    form.clearErrors();
    form.reset();
};
</script>

<template>
    <div class="space-y-4">
        <p class="text-sm text-slate-600 leading-relaxed">
            После удаления аккаунта все связанные данные будут безвозвратно удалены.
            Убедитесь, что сохранили нужную информацию заранее.
        </p>

        <DangerButton @click="confirmUserDeletion" class="rounded-xl">
            Удалить аккаунт
        </DangerButton>

        <Modal :show="confirmingUserDeletion" @close="closeModal">
            <div class="p-6">
                <h2 class="text-lg font-semibold text-slate-900">
                    Удалить аккаунт?
                </h2>
                <p class="mt-2 text-sm text-slate-600">
                    Это действие необратимо. Введите текущий пароль для подтверждения.
                </p>

                <div class="mt-5">
                    <InputLabel for="delete_password" value="Пароль" />
                    <TextInput
                        id="delete_password"
                        ref="passwordInput"
                        v-model="form.password"
                        type="password"
                        class="mt-1.5 block w-full rounded-xl border-slate-300"
                        placeholder="Введите пароль"
                        @keyup.enter="deleteUser"
                    />
                    <InputError :message="form.errors.password" class="mt-2" />
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton @click="closeModal" class="rounded-xl">
                        Отмена
                    </SecondaryButton>
                    <DangerButton
                        class="rounded-xl"
                        :class="{ 'opacity-25': form.processing }"
                        :disabled="form.processing"
                        @click="deleteUser"
                    >
                        Удалить навсегда
                    </DangerButton>
                </div>
            </div>
        </Modal>
    </div>
</template>
