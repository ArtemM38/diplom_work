<script setup>
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Аккаунт неактивен" />
    <div class="min-h-screen bg-slate-100 flex items-center justify-center px-4">
        <div class="w-full max-w-lg bg-white rounded-2xl shadow-sm border border-slate-200 p-8 text-center">
            <h1 class="text-2xl font-bold text-slate-900 mb-3">Аккаунт неактивен</h1>
            <p class="text-slate-600 mb-6">
                Ваш аккаунт временно отключен администратором. Доступ к системе ограничен.
            </p>
            <Link :href="route('logout')" method="post" as="button" class="px-4 py-2 rounded-lg bg-slate-800 text-white font-semibold">
                Выйти из аккаунта
            </Link>
        </div>
    </div>
</template>
