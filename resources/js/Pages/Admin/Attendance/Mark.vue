<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useForm, Head, Link } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';

const props = defineProps({
    schedule: Object,
    athletes: Array,
    existingAttendances: Object,
    existingCertificates: Object,
});

const form = useForm({
    attendance: {},
    certificates: {},
});

const certificateFiles = ref({});

onMounted(() => {
    props.athletes.forEach((athlete) => {
        form.attendance[athlete.id] = props.existingAttendances[athlete.id] || 'Н';
    });
});

const onStatusClick = (athleteId, status) => {
    form.attendance[athleteId] = status;
    if (status !== 'У') {
        certificateFiles.value[athleteId] = null;
        delete form.certificates[athleteId];
    }
};

const onCertificateChange = (athleteId, event) => {
    const file = event.target.files?.[0];
    if (file) {
        certificateFiles.value[athleteId] = file;
        form.certificates[athleteId] = file;
    }
};

const hasCertificate = (athleteId) =>
    certificateFiles.value[athleteId]
    || props.existingCertificates?.[athleteId];

const submit = () => {
    form.post(route('admin.attendance.store', props.schedule.id), {
        forceFormData: true,
    });
};
</script>

<template>
    <Head title="Отметка посещаемости" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center gap-2 min-w-0">
                <Link :href="route('admin.schedule')" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium shrink-0">← Расписание</Link>
                <span class="truncate">Отметка: {{ schedule.group.name }} ({{ schedule.lesson_date }})</span>
            </div>
        </template>

        <div class="w-full max-w-4xl mx-auto py-6 sm:py-12">
            <div class="bg-white shadow rounded-2xl overflow-hidden">
                <div class="p-4 sm:p-6 border-b bg-gray-50 flex flex-col sm:flex-row sm:justify-between gap-2">
                    <div>
                        <p class="font-bold text-lg">{{ schedule.group.name }}</p>
                        <p class="text-sm text-gray-500">{{ schedule.start_time }} - {{ schedule.end_time }}</p>
                    </div>
                    <div class="text-right text-sm">
                        <p class="font-medium">Тренер: {{ schedule.coach?.name }}</p>
                        <p v-if="schedule.initial_coach && schedule.initial_coach.id !== schedule.coach?.id" class="text-xs text-slate-500 mt-1">
                            Изначально: {{ schedule.initial_coach?.name }}
                        </p>
                    </div>
                </div>

                <div class="app-table-wrap">
                    <table class="w-full">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-400">
                            <tr>
                                <th class="px-6 py-3 text-left">Спортсмен</th>
                                <th class="px-6 py-3 text-center">Статус</th>
                                <th class="px-6 py-3 text-left">Справка (для У)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="athlete in athletes" :key="athlete.id">
                                <td class="px-6 py-4 font-medium">
                                    {{ athlete.last_name_nom }} {{ athlete.first_name_nom }}
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex justify-center gap-2">
                                        <button
                                            v-for="status in ['Я', 'Н', 'У']"
                                            :key="status"
                                            type="button"
                                            @click="onStatusClick(athlete.id, status)"
                                            :class="[
                                                'px-2 sm:px-4 py-2 rounded-lg text-xs font-bold transition',
                                                form.attendance[athlete.id] === status
                                                    ? (status === 'Я' ? 'bg-green-500 text-white' : status === 'Н' ? 'bg-red-500 text-white' : 'bg-yellow-500 text-white')
                                                    : 'bg-gray-100 text-gray-400 hover:bg-gray-200',
                                            ]"
                                        >
                                            {{ status }}
                                        </button>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div v-if="form.attendance[athlete.id] === 'У'" class="space-y-1">
                                        <input
                                            type="file"
                                            accept=".pdf,.jpg,.jpeg,.png"
                                            class="text-xs w-full"
                                            :class="form.errors[`certificates.${athlete.id}`] ? 'border border-red-500 rounded' : ''"
                                            @change="onCertificateChange(athlete.id, $event)"
                                        />
                                        <p v-if="existingCertificates?.[athlete.id] && !certificateFiles[athlete.id]" class="text-xs text-green-600">
                                            Справка уже загружена
                                        </p>
                                        <p v-if="form.errors[`certificates.${athlete.id}`]" class="text-xs text-red-600">
                                            {{ form.errors[`certificates.${athlete.id}`] }}
                                        </p>
                                    </div>
                                    <span v-else class="text-xs text-gray-400">—</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="p-6 bg-gray-50 border-t flex justify-end">
                    <button
                        type="button"
                        @click="submit"
                        class="bg-indigo-600 text-white px-8 py-3 rounded-xl font-bold hover:bg-indigo-700 shadow-lg"
                        :disabled="form.processing"
                    >
                        Сохранить журнал
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
