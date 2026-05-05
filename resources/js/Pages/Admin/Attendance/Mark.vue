<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useForm, Head } from '@inertiajs/vue3';
import { onMounted } from 'vue';

const props = defineProps({
    schedule: Object,
    athletes: Array,
    existingAttendances: Object
});

const form = useForm({
    attendance: {} // { athlete_id: 'Я' }
});

// Инициализируем форму текущими данными или ставим "Н" по умолчанию
onMounted(() => {
    props.athletes.forEach(athlete => {
        form.attendance[athlete.id] = props.existingAttendances[athlete.id] || 'Н';
    });
});

const submit = () => {
    form.post(route('admin.attendance.store', props.schedule.id));
};
</script>

<template>

    <Head title="Отметка посещаемости" />
    <AuthenticatedLayout>
        <template #header>
            Отметка группы: {{ schedule.group.name }} ({{ schedule.lesson_date }})
        </template>

        <div class="max-w-4xl mx-auto py-12">
            <div class="bg-white shadow rounded-2xl overflow-hidden">
                <div class="p-6 border-b bg-gray-50 flex justify-between">
                    <div>
                        <p class="font-bold text-lg">{{ schedule.group.name }}</p>
                        <p class="text-sm text-gray-500">{{ schedule.start_time }} - {{ schedule.end_time }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-medium">Тренер: {{ schedule.coach?.name }}</p>
                    </div>
                </div>

                <table class="w-full">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-400">
                        <tr>
                            <th class="px-6 py-3 text-left">Спортсмен</th>
                            <th class="px-6 py-3 text-center">Статус</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="athlete in athletes" :key="athlete.id">
                            <td class="px-6 py-4 font-medium">{{ athlete.last_name_nom }} {{ athlete.first_name_nom }}
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex justify-center gap-2">
                                    <button v-for="status in ['Я', 'Н', 'У']" :key="status"
                                        @click="form.attendance[athlete.id] = status" type="button" :class="[
                                            'px-4 py-2 rounded-lg text-xs font-bold transition',
                                            form.attendance[athlete.id] === status
                                                ? (status === 'Я' ? 'bg-green-500 text-white' : status === 'Н' ? 'bg-red-500 text-white' : 'bg-yellow-500 text-white')
                                                : 'bg-gray-100 text-gray-400 hover:bg-gray-200'
                                        ]">
                                        {{ status }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div class="p-6 bg-gray-50 border-t flex justify-end">
                    <button @click="submit"
                        class="bg-indigo-600 text-white px-8 py-3 rounded-xl font-bold hover:bg-indigo-700 shadow-lg">
                        Сохранить журнал
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>