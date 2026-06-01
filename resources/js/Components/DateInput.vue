<script setup>
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import { fieldClass } from '@/utils/formErrors';

defineProps({
    modelValue: { type: String, default: '' },
    label: { type: String, required: true },
    error: { type: String, default: '' },
    errors: { type: Object, default: null },
    errorKey: { type: String, default: '' },
    required: { type: Boolean, default: false },
    max: { type: String, default: undefined },
    min: { type: String, default: undefined },
    inputClass: { type: String, default: 'w-full border-gray-300 rounded-lg text-sm' },
});

const emit = defineEmits(['update:modelValue']);
</script>

<template>
    <div class="min-w-0">
        <InputLabel :value="label" class="mb-1" />
        <input
            type="date"
            :value="modelValue"
            :required="required"
            :max="max"
            :min="min"
            :class="fieldClass(errors, errorKey, inputClass)"
            @input="emit('update:modelValue', $event.target.value)"
        />
        <InputError v-if="error" :message="error" class="mt-1" />
    </div>
</template>
