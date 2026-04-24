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
                    <TextInput v-model="form.phone" class="w-full" required placeholder="+7 (___) ___-__-__" />
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