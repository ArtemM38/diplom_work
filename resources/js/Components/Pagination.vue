<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    links: {
        type: Array,
        default: () => [],
    },
    meta: {
        type: Object,
        default: null,
    },
});

const translateLabel = (label) => {
    if (!label) return '';
    const text = String(label).trim();
    const map = {
        '&laquo; Previous': 'Назад',
        'Previous': 'Назад',
        'Next &raquo;': 'Вперёд',
        'Next': 'Вперёд',
    };
    if (map[text]) return map[text];
    return text
        .replace(/&laquo;/g, '«')
        .replace(/&raquo;/g, '»')
        .replace(/Previous/g, 'Назад')
        .replace(/Next/g, 'Вперёд');
};
</script>

<template>
    <div v-if="links?.length > 3" class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mt-4">
        <p v-if="meta" class="text-sm text-slate-500">
            Показано {{ meta.from }}–{{ meta.to }} из {{ meta.total }}
        </p>
        <nav class="flex flex-wrap gap-1">
            <template v-for="(link, index) in links" :key="index">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    preserve-state
                    preserve-scroll
                    class="px-3 py-1.5 text-sm rounded-lg border transition"
                    :class="link.active
                        ? 'bg-indigo-600 text-white border-indigo-600'
                        : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50'"
                >
                    {{ translateLabel(link.label) }}
                </Link>
                <span
                    v-else
                    class="px-3 py-1.5 text-sm rounded-lg border border-slate-100 text-slate-300"
                >
                    {{ translateLabel(link.label) }}
                </span>
            </template>
        </nav>
    </div>
</template>
