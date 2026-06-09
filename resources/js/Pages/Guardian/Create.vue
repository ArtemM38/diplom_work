<script setup>
import { useForm, Head } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import FormErrorsAlert from '@/Components/FormErrorsAlert.vue';
import { fieldClass } from '@/utils/formErrors';

const props = defineProps({
    prefilledFullName: {
        type: String,
        default: '',
    },
});

const form = useForm({
    full_name: props.prefilledFullName || '',
    phone: '',
    relation: 'Отец',
});

const formatPhone = (value) => {
    const digits = (value || '').replace(/\D/g, '').slice(0, 11);
    const normalized = digits.startsWith('8') ? `7${digits.slice(1)}` : digits;
    const d = normalized.startsWith('7') ? normalized.slice(1) : normalized;

    let out = '+7';
    if (d.length > 0) out += ` (${d.slice(0, 3)}`;
    if (d.length >= 3) out += ')';
    if (d.length > 3) out += ` ${d.slice(3, 6)}`;
    if (d.length > 6) out += `-${d.slice(6, 8)}`;
    if (d.length > 8) out += `-${d.slice(8, 10)}`;
    return out;
};

const onPhoneInput = (event) => {
    form.phone = formatPhone(event.target.value);
};

const submit = () => {
    form.post(route('guardian.store'));
};
</script>

<template>
    <Head title="Данные представителя" />
    <div class="py-12 bg-gray-100 min-h-screen px-4">
        <div class="max-w-lg mx-auto bg-white p-6 sm:p-8 shadow-xl rounded-2xl">
            <div class="text-center mb-6">
                <h2 class="text-2xl font-bold text-gray-800">Анкета родителя</h2>
                <p class="text-sm text-gray-500 mt-1">
                    Укажите контактные данные. Привязку к спортсмену выполнит администратор.
                </p>
            </div>

            <form @submit.prevent="submit" class="space-y-5">
                <FormErrorsAlert :errors="form.errors" />

                <div>
                    <InputLabel value="Ваше ФИО" />
                    <TextInput
                        v-model="form.full_name"
                        class="w-full"
                        required
                        placeholder="Иванов Иван Иванович"
                        :invalid="!!form.errors.full_name"
                    />
                    <InputError :message="form.errors.full_name" />
                </div>
                <div>
                    <InputLabel value="Ваш телефон" />
                    <TextInput
                        v-model="form.phone"
                        class="w-full"
                        required
                        placeholder="+7 (___) ___-__-__"
                        :invalid="!!form.errors.phone"
                        @input="onPhoneInput"
                    />
                    <InputError :message="form.errors.phone" />
                </div>
                <div>
                    <InputLabel value="Кем вы являетесь ребёнку?" />
                    <select
                        v-model="form.relation"
                        :class="fieldClass(form.errors, 'relation', 'w-full rounded-lg shadow-sm')"
                    >
                        <option value="Отец">Отец</option>
                        <option value="Мать">Мать</option>
                        <option value="Опекун">Опекун</option>
                    </select>
                    <InputError :message="form.errors.relation" />
                </div>

                <PrimaryButton class="w-full justify-center py-3 text-lg bg-blue-600" :disabled="form.processing">
                    Завершить регистрацию
                </PrimaryButton>
            </form>
        </div>
    </div>
</template>
