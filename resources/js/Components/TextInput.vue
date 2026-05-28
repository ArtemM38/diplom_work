<script setup>
import { computed, onMounted, ref } from 'vue';

const model = defineModel({
    type: String,
    required: true,
});

const props = defineProps({
    invalid: {
        type: Boolean,
        default: false,
    },
});

const inputClasses = computed(() => [
    'w-full min-w-0 max-w-full rounded-md border shadow-sm focus:ring-1',
    props.invalid
        ? 'border-red-500 bg-red-50/80 focus:border-red-500 focus:ring-red-500'
        : 'border-gray-300 focus:border-indigo-500 focus:ring-indigo-500',
]);

const input = ref(null);

onMounted(() => {
    if (input.value.hasAttribute('autofocus')) {
        input.value.focus();
    }
});

defineExpose({ focus: () => input.value.focus() });
</script>

<template>
    <input
        :class="inputClasses"
        v-model="model"
        ref="input"
    />
</template>

