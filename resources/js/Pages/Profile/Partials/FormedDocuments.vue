<script setup>
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { computed, reactive, ref } from 'vue';

const props = defineProps({
    templates: { type: Array, default: () => [] },
    athleteId: { type: Number, default: null },
});

const openConstructor = ref(null);
const constructorValues = reactive({});

const formatLabels = {
    docx: 'Word (DOCX)',
    xlsx: 'Excel (XLSX)',
    xls: 'Excel (XLS)',
    pdf: 'PDF',
};

const startDownload = (template, format) => {
    const params = new URLSearchParams({ format });
    if (props.athleteId) {
        params.set('athlete_id', String(props.athleteId));
    }

    const meta = props.templates.find((t) => t.id === template.id);
    if (meta?.constructor) {
        for (const field of meta.fields || []) {
            const val = constructorValues[`${template.id}_${field.name}`];
            if (val !== undefined && val !== null && val !== '') {
                params.set(field.name, val);
            }
        }
    }

    window.location.href = `${route('athlete.documents.download', template.id)}?${params.toString()}`;
};

const openForm = (template) => {
    if (!template.constructor) {
        return;
    }
    openConstructor.value = openConstructor.value === template.id ? null : template.id;
    for (const field of template.fields || []) {
        const key = `${template.id}_${field.name}`;
        if (constructorValues[key] === undefined) {
            constructorValues[key] = '';
        }
    }
};

const canDownload = (template) => {
    if (!template.constructor) {
        return true;
    }
    return (template.fields || []).every((field) => {
        if (!field.required) {
            return true;
        }
        const val = constructorValues[`${template.id}_${field.name}`];
        return val !== undefined && val !== null && String(val).trim() !== '';
    });
};

const sortedTemplates = computed(() =>
    [...props.templates].sort((a, b) => a.id - b.id),
);
</script>

<template>
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h4 class="text-lg font-semibold text-slate-900">Формируемые документы</h4>
        <p class="mt-1 text-sm text-slate-500 mb-4">
            Поля заполняются автоматически из профиля спортсмена и законного представителя.
            Подпись и отдельные поля можно дописать вручную после скачивания. Доступны форматы Word, Excel и PDF.
        </p>

        <div v-if="!sortedTemplates.length" class="text-sm text-slate-400">
            Шаблоны документов не настроены.
        </div>

        <div v-else class="space-y-3">
            <div
                v-for="template in sortedTemplates"
                :key="template.id"
                class="rounded-xl border border-slate-100 bg-slate-50/80 overflow-hidden"
            >
                <div class="flex flex-col sm:flex-row sm:items-center gap-3 p-4">
                    <div class="flex-1 min-w-0">
                        <p class="font-medium text-slate-900">{{ template.title }}</p>
                        <p v-if="template.constructor" class="text-xs text-amber-700 mt-0.5">
                            Требуется заполнить поля конструктора
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2 shrink-0">
                        <button
                            v-if="template.constructor"
                            type="button"
                            class="px-3 py-1.5 rounded-lg text-sm border border-slate-300 bg-white hover:bg-slate-50"
                            @click="openForm(template)"
                        >
                            {{ openConstructor === template.id ? 'Скрыть' : 'Параметры' }}
                        </button>
                        <button
                            v-for="format in template.formats"
                            :key="`${template.id}-${format}`"
                            type="button"
                            class="px-3 py-1.5 rounded-lg text-sm font-medium bg-indigo-600 text-white hover:bg-indigo-700 disabled:opacity-40"
                            :disabled="!canDownload(template)"
                            @click="startDownload(template, format)"
                        >
                            {{ formatLabels[format] || format }}
                        </button>
                    </div>
                </div>

                <div
                    v-if="template.constructor && openConstructor === template.id"
                    class="border-t border-slate-200 bg-white p-4 grid grid-cols-1 sm:grid-cols-2 gap-3"
                >
                    <div
                        v-for="field in template.fields"
                        :key="field.name"
                        :class="field.type === 'textarea' ? 'sm:col-span-2' : ''"
                    >
                        <InputLabel :value="field.label + (field.required ? ' *' : '')" />
                        <textarea
                            v-if="field.type === 'textarea'"
                            v-model="constructorValues[`${template.id}_${field.name}`]"
                            rows="3"
                            class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm"
                        />
                        <TextInput
                            v-else
                            v-model="constructorValues[`${template.id}_${field.name}`]"
                            :type="field.type === 'number' ? 'number' : field.type === 'date' ? 'date' : 'text'"
                            class="mt-1 w-full rounded-xl"
                        />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
