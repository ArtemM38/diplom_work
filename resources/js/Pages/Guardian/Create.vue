<script setup>
import { useForm, Head } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';

const form = useForm({
    full_name: '',
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
    <div class="py-12 bg-gray-100 min-h-screen">
        <div class="max-w-md mx-auto bg-white p-8 shadow-xl rounded-2xl">
            <div class="text-center mb-6">
                <h2 class="text-2xl font-bold text-gray-800">Анкета родителя</h2>
                <p class="text-sm text-gray-500 mt-1">Сначала заполните ваши данные, затем данные ребенка</p>
            </div>

            <form @submit.prevent="submit" class="space-y-5">
                <div>
                    <InputLabel value="Ваше ФИО" />
                    <TextInput v-model="form.full_name" class="w-full" required placeholder="Иванов Иван Иванович" />
                </div>
                <div>
                    <InputLabel value="Ваш телефон" />
                    <TextInput
                        v-model="form.phone"
                        class="w-full"
                        required
                        placeholder="+7 (___) ___-__-__"
                        @input="onPhoneInput"
                    />
                </div>
                <div>
                    <InputLabel value="Кем вы являетесь ребенку?" />
                    <select v-model="form.relation"
                        class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500">
                        <option value="Отец">Отец</option>
                        <option value="Мать">Мать</option>
                        <option value="Опекун">Опекун</option>
                    </select>
                </div>
                <PrimaryButton class="w-full justify-center py-3 text-lg bg-blue-600">
                    Далее: Данные ребенка
                </PrimaryButton>
            </form>
        </div>
    </div>
</template>