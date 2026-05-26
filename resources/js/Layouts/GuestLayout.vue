<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div
        class="flex min-h-screen flex-col items-center bg-gray-100 px-4 pt-6 sm:justify-center sm:pt-0"
    >

        <div
            class="mt-6 w-full max-w-full min-w-0 overflow-hidden bg-white px-4 py-4 shadow-md sm:max-w-md sm:rounded-lg sm:px-6"
        >
            <slot />
        </div>
    </div>
</template>
