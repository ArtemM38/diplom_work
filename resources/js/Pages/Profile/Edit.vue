<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import AvatarZoomable from '@/Components/AvatarZoomable.vue';
import AthleteDataSummary from './Partials/AthleteDataSummary.vue';
import FormedDocuments from './Partials/FormedDocuments.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import FormErrorsAlert from '@/Components/FormErrorsAlert.vue';
import { fieldClass } from '@/utils/formErrors';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    mustVerifyEmail: { type: Boolean },
    status: { type: String },
    profileData: { type: Object },
});

const avatarForm = useForm({ avatar: null });
const avatarPreview = ref(null);
const selectedAvatarName = ref('');

const onAvatarChange = (e) => {
    const file = e.target.files?.[0];
    if (!file) return;
    avatarForm.avatar = file;
    selectedAvatarName.value = file.name;
    avatarPreview.value = URL.createObjectURL(file);
};

const uploadAvatar = () => {
    avatarForm.post(route('profile.avatar.update'), {
        forceFormData: true,
        onSuccess: () => {
            avatarForm.reset();
            avatarPreview.value = null;
            selectedAvatarName.value = '';
        },
    });
};

const guardianForm = useForm({
    full_name: props.profileData?.guardian?.full_name || '',
    phone: props.profileData?.guardian?.phone || '',
    relation: props.profileData?.guardian?.relation || 'Отец',
});

const formatPhone = (value) => {
    const digits = (value || '').replace(/\D/g, '').slice(0, 11);
    const normalized = digits.startsWith('8') ? `7${digits.slice(1)}` : digits;
    const d = normalized.startsWith('7') ? normalized.slice(1) : normalized;
    let out = '+7';
    if (d.length > 0) out += ` (${d.slice(0, 3)}`;
    if (d.length >= 3) out += ')';
    if (d.length > 3) out += ` ${d.slice(3, 6)}`;
    if (d.length > 6) out += `-${d.slice(6, 8)}`;
    if (d.length > 8) out += `-${d.slice(8, 10)}`;
    return out;
};

const onPhoneInput = (event) => {
    guardianForm.phone = formatPhone(event.target.value);
};

const saveGuardian = () => {
    guardianForm.patch(route('profile.guardian.update'));
};

const childName = (child) =>
    `${child.last_name_nom} ${child.first_name_nom} ${child.middle_name_nom || ''}`.trim();

const statusMessage = computed(() => {
    const map = {
        'avatar-updated': 'Аватар успешно обновлён',
        'guardian-updated': 'Контактные данные сохранены',
        'verification-link-sent': 'Письмо для подтверждения отправлено',
    };
    return map[props.status] || null;
});

const hasRoleBlocks = computed(
    () =>
        props.profileData?.guardian ||
        props.profileData?.children?.length,
);

const isAthleteProfile = computed(() => !!props.profileData?.athlete);

const documentTemplates = computed(() => props.profileData?.documentTemplates ?? []);

const selectedChildId = ref(props.profileData?.children?.[0]?.id ?? null);
</script>

<template>
    <Head title="Профиль" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-slate-800">Профиль</h2>
        </template>

        <div class="py-8">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                <Transition
                    enter-active-class="transition ease-out duration-200"
                    enter-from-class="opacity-0 -translate-y-1"
                    enter-to-class="opacity-100 translate-y-0"
                >
                    <div
                        v-if="statusMessage"
                        class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800"
                    >
                        {{ statusMessage }}
                    </div>
                </Transition>

                <!-- Шапка аккаунта (не для спортсмена — у него отдельный блок анкеты) -->
                <section
                    v-if="!isAthleteProfile"
                    class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
                >
                    <div class="h-24 bg-gradient-to-r from-indigo-600 via-indigo-500 to-violet-500" />
                    <div class="px-6 mt-6 pb-6">
                        <div class="flex flex-col gap-6 sm:flex-row sm:items-end sm:-mt-12">
                            <AvatarZoomable
                                :src="avatarPreview || profileData?.user?.avatar_url"
                                :name="profileData?.user?.name"
                                size="lg"
                                shape="rounded"
                            />
                            <div class="flex-1 min-w-0 pt-2 sm:pt-14">
                                <h1 class="text-2xl font-bold text-slate-900 truncate">
                                    {{ profileData?.user?.name }}
                                </h1>
                                <p class="text-slate-500 truncate">{{ profileData?.user?.email }}</p>
                                <span
                                    v-if="profileData?.user?.role_label"
                                    class="mt-2 inline-flex items-center rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700"
                                >
                                    {{ profileData.user.role_label }}
                                </span>
                            </div>
                            <form @submit.prevent="uploadAvatar" class="flex flex-col gap-2 sm:items-end sm:pb-1 w-full sm:w-auto">
                                <label class="inline-flex cursor-pointer items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50 transition w-full sm:w-auto">
                                    <input
                                        type="file"
                                        accept="image/jpeg,image/png,image/webp,image/*"
                                        class="sr-only"
                                        @change="onAvatarChange"
                                    />
                                    Выбрать фото
                                </label>
                                <p v-if="selectedAvatarName" class="text-xs text-slate-500 break-all text-center sm:text-right">
                                    {{ selectedAvatarName }}
                                </p>
                                <button
                                    v-if="avatarForm.avatar"
                                    type="submit"
                                    class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50 transition w-full sm:w-auto"
                                    :disabled="avatarForm.processing"
                                >
                                    {{ avatarForm.processing ? 'Загрузка…' : 'Сохранить аватар' }}
                                </button>
                            </form>
                        </div>
                    </div>
                </section>

                <AthleteDataSummary
                    v-if="isAthleteProfile"
                    :athlete="profileData.athlete"
                    :user-avatar-url="profileData?.user?.avatar_url"
                    :user-name="profileData?.user?.name"
                    :document-templates="documentTemplates"
                />

                <section
                    v-if="profileData?.children?.length && documentTemplates.length"
                    class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
                >
                    <h3 class="text-lg font-semibold text-slate-900 mb-3">Документы ребёнка</h3>
                    <label class="text-xs text-slate-500 uppercase font-medium">Спортсмен</label>
                    <select
                        v-model="selectedChildId"
                        class="mt-1 mb-4 w-full max-w-md border-slate-300 rounded-xl"
                    >
                        <option v-for="child in profileData.children" :key="child.id" :value="child.id">
                            {{ childName(child) }}
                        </option>
                    </select>
                    <FormedDocuments
                        :templates="documentTemplates"
                        :athlete-id="selectedChildId"
                    />
                </section>

                <div class="grid gap-6 lg:grid-cols-2">
                    <!-- Левая колонка: роль -->
                    <div v-if="!isAthleteProfile" class="space-y-6">
                        <section
                            v-if="profileData?.guardian"
                            class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
                        >
                            <h3 class="text-lg font-semibold text-slate-900">Законный представитель</h3>
                            <p class="mt-1 text-sm text-slate-500 mb-5">
                                Контакты и связь с ребёнком
                            </p>
                            <form @submit.prevent="saveGuardian" class="space-y-4">
                                <FormErrorsAlert :errors="guardianForm.errors" />
                                <div>
                                    <InputLabel value="ФИО" />
                                    <TextInput
                                        v-model="guardianForm.full_name"
                                        class="mt-1.5 w-full rounded-xl"
                                        :invalid="!!guardianForm.errors.full_name"
                                        required
                                        placeholder="Иванов Иван Иванович"
                                    />
                                    <InputError :message="guardianForm.errors.full_name" class="mt-1" />
                                </div>
                                <div>
                                    <InputLabel value="Телефон" />
                                    <TextInput
                                        v-model="guardianForm.phone"
                                        class="mt-1.5 w-full rounded-xl"
                                        :invalid="!!guardianForm.errors.phone"
                                        required
                                        placeholder="+7 (___) ___-__-__"
                                        @input="onPhoneInput"
                                    />
                                    <InputError :message="guardianForm.errors.phone" class="mt-1" />
                                </div>
                                <div>
                                    <InputLabel value="Кем вы приходитесь ребёнку" />
                                    <select
                                        v-model="guardianForm.relation"
                                        :class="fieldClass(guardianForm.errors, 'relation', 'mt-1.5 w-full rounded-xl shadow-sm')"
                                    >
                                        <option value="Отец">Отец</option>
                                        <option value="Мать">Мать</option>
                                        <option value="Опекун">Опекун</option>
                                    </select>
                                    <InputError :message="guardianForm.errors.relation" class="mt-1" />
                                </div>
                                <PrimaryButton
                                    type="submit"
                                    class="rounded-xl"
                                    :disabled="guardianForm.processing"
                                >
                                    Сохранить контакты
                                </PrimaryButton>
                            </form>
                        </section>

                        <section
                            v-if="profileData?.children?.length"
                            class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
                        >
                            <h3 class="text-lg font-semibold text-slate-900">Привязанные дети</h3>
                            <p class="mt-1 text-sm text-slate-500 mb-4">Спортсмены в вашем аккаунте</p>
                            <ul class="space-y-3">
                                <li
                                    v-for="child in profileData.children"
                                    :key="child.id"
                                    class="flex items-center justify-between gap-4 rounded-xl border border-slate-100 bg-slate-50 p-4"
                                >
                                    <div class="min-w-0">
                                        <p class="font-semibold text-slate-900 truncate">{{ childName(child) }}</p>
                                        <p class="text-sm text-slate-500">
                                            {{ child.birth_date || 'дата не указана' }}
                                            <span v-if="child.phone"> · {{ child.phone }}</span>
                                        </p>
                                    </div>
                                    <a
                                        :href="route('athlete.edit', child.id)"
                                        class="shrink-0 text-sm font-medium text-indigo-600 hover:text-indigo-800"
                                    >
                                        Анкета
                                    </a>
                                </li>
                            </ul>
                        </section>

                        <section
                            v-if="!hasRoleBlocks && !isAthleteProfile"
                            class="rounded-2xl border border-dashed border-slate-200 bg-slate-50/80 p-6 text-center text-sm text-slate-500"
                        >
                            Дополнительные данные по роли не требуются. Ниже можно изменить почту и пароль.
                        </section>
                    </div>

                    <!-- Аккаунт и безопасность -->
                    <div class="space-y-6" :class="isAthleteProfile ? 'lg:col-span-2' : ''">
                        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                            <h3 class="text-lg font-semibold text-slate-900">Данные аккаунта</h3>
                            <p class="mt-1 text-sm text-slate-500 mb-5">
                                {{
                                    profileData?.guardian
                                        ? 'Адрес для входа и уведомлений'
                                        : isAthleteProfile
                                          ? 'Почта для входа (ФИО — в анкете выше)'
                                          : 'Имя в системе и адрес электронной почты'
                                }}
                            </p>
                            <UpdateProfileInformationForm
                                :must-verify-email="mustVerifyEmail"
                                :status="status"
                                :hide-name="!!profileData?.guardian || isAthleteProfile"
                            />
                        </section>

                        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                            <h3 class="text-lg font-semibold text-slate-900">Безопасность</h3>
                            <p class="mt-1 text-sm text-slate-500 mb-5">Смена пароля для входа в CRM</p>
                            <UpdatePasswordForm />
                        </section>

                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
