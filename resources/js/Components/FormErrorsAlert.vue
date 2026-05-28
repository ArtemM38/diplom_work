<script setup>
defineProps({
    errors: {
        type: Object,
        default: () => ({}),
    },
    title: {
        type: String,
        default: 'Исправьте ошибки в форме:',
    },
});
</script>

<template>
    <div
        v-if="errors && Object.keys(errors).length"
        class="rounded-xl bg-red-50 border border-red-200 p-4 text-sm text-red-800 space-y-1"
        role="alert"
    >
        <p class="font-semibold">{{ title }}</p>
        <ul class="list-disc list-inside space-y-0.5">
            <li v-for="(message, key) in errors" :key="key">{{ message }}</li>
        </ul>
    </div>
</template>
