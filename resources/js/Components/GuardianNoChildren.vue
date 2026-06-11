<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="bg-amber-50 border border-amber-100 rounded-2xl p-6 md:p-8 text-center max-w-lg mx-auto">
        <h2 class="text-lg font-bold text-amber-900 mb-2">Ребёнок не привязан</h2>
        <p class="text-sm text-amber-800 leading-relaxed">
            К вашему аккаунту пока не привязан спортсмен. Обратитесь к администратору клуба — он свяжет ваш профиль с карточкой ребёнка после регистрации.
        </p>
        <Link
            :href="route('dashboard')"
            class="inline-block mt-5 px-4 py-2 bg-white border border-amber-200 rounded-lg text-sm font-medium text-amber-900 hover:bg-amber-100 transition"
        >
            На главную
        </Link>
    </div>
</template>
