<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';

const props = defineProps({
    src: { type: String, default: null },
    name: { type: String, default: '' },
    size: {
        type: String,
        default: 'md',
        validator: (v) => ['sm', 'md', 'lg', 'xl'].includes(v),
    },
    shape: {
        type: String,
        default: 'rounded',
        validator: (v) => ['circle', 'rounded'].includes(v),
    },
    zoomable: { type: Boolean, default: true },
});

const open = ref(false);

const displaySrc = computed(() => {
    if (props.src) return props.src;
    const label = encodeURIComponent((props.name || 'U').trim() || 'U');
    return `https://ui-avatars.com/api/?name=${label}&background=4f46e5&color=fff`;
});

const sizeClass = computed(() => {
    const map = {
        sm: 'h-10 w-10 text-sm',
        md: 'h-16 w-16 text-lg',
        lg: 'h-24 w-24 text-2xl',
        xl: 'h-32 w-32 text-3xl',
    };
    return map[props.size] || map.md;
});

const shapeClass = computed(() => (props.shape === 'circle' ? 'rounded-full' : 'rounded-2xl'));

const openPreview = () => {
    if (!props.zoomable) return;
    open.value = true;
    document.body.style.overflow = 'hidden';
};

const closePreview = () => {
    open.value = false;
    document.body.style.overflow = '';
};

const onKeydown = (e) => {
    if (e.key === 'Escape' && open.value) closePreview();
};

onMounted(() => document.addEventListener('keydown', onKeydown));
onUnmounted(() => {
    document.removeEventListener('keydown', onKeydown);
    document.body.style.overflow = '';
});
</script>

<template>
    <div class="inline-flex flex-col items-center gap-1">
        <button
            type="button"
            class="group relative shrink-0 overflow-hidden border-4 border-white shadow-lg ring-2 ring-indigo-100 focus:outline-none focus-visible:ring-4 focus-visible:ring-indigo-400"
            :class="[$attrs.class, sizeClass, shapeClass, zoomable ? 'cursor-zoom-in' : 'cursor-default']"
            :disabled="!zoomable"
            :aria-label="zoomable ? 'Увеличить фото' : undefined"
            @click="openPreview"
        >
            <img
                :src="displaySrc"
                :alt="name || 'Аватар'"
                class="h-full w-full object-cover"
            />
            <span
                v-if="zoomable"
                class="absolute inset-0 flex items-end justify-center bg-gradient-to-t from-black/50 to-transparent pb-1 opacity-0 transition group-hover:opacity-100"
            >
                <span class="text-[10px] font-medium text-white px-1">Увеличить</span>
            </span>
        </button>

        <Teleport to="body">
            <Transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="open"
                    class="fixed inset-0 z-[100] flex items-center justify-center p-4 sm:p-8"
                    role="dialog"
                    aria-modal="true"
                    :aria-label="name ? `Фото: ${name}` : 'Фото профиля'"
                    @click.self="closePreview"
                >
                    <div class="absolute inset-0 bg-slate-900/90 backdrop-blur-sm" />
                    <button
                        type="button"
                        class="absolute top-4 right-4 z-10 rounded-full bg-white/10 p-2 text-white hover:bg-white/20 transition"
                        aria-label="Закрыть"
                        @click="closePreview"
                    >
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                    <img
                        :src="displaySrc"
                        :alt="name || 'Аватар'"
                        class="relative z-10 max-h-[85vh] max-w-full rounded-2xl object-contain shadow-2xl ring-1 ring-white/20"
                        @click.stop
                    />
                    <p v-if="name" class="absolute bottom-6 left-0 right-0 z-10 text-center text-sm text-white/90">
                        {{ name }}
                    </p>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>
