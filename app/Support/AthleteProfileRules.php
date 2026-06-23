<?php

namespace App\Support;

use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rule;

class AthleteProfileRules
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'last_name_nom' => 'required|string|max:255',
            'first_name_nom' => 'required|string|max:255',
            'middle_name_nom' => 'nullable|string|max:255',
            'birth_date' => 'required|date|before_or_equal:today',
            'gender' => 'required|in:male,female',
            'occupation_type' => 'required|in:study,work,kindergarten',
            'phone' => 'nullable|regex:/^\+7 \(\d{3}\) \d{3}-\d{2}-\d{2}$/',
            'registration_address' => 'nullable|string|max:500',
            'school_name' => 'nullable|required_if:occupation_type,study|string',
            'school_director_dat' => 'nullable|required_if:occupation_type,study|string|max:255',
            'school_class' => 'nullable|required_if:occupation_type,study|string|max:50',
            'kindergarten_name' => 'nullable|required_if:occupation_type,kindergarten|string',
            'work_place' => 'nullable|required_if:occupation_type,work|string',
            'work_position' => 'nullable|required_if:occupation_type,work|string|max:255',
            'photo' => 'nullable|file|image|max:4096',

            'guardian_id' => 'nullable|exists:guardians,id',
            'relation' => 'nullable|string|max:255',

            'ranks' => 'nullable|array',
            'ranks.*.rank_id' => 'required_with:ranks|exists:ranks,id',
            'ranks.*.assigned_at' => 'required_with:ranks|date',

            'referees' => 'nullable|array',
            'referees.*.referee_category_id' => 'required_with:referees|exists:referee_categories,id',
            'referees.*.assigned_at' => 'required_with:referees|date',

            'inventory_item_ids' => 'nullable|array',
            'inventory_item_ids.*' => 'integer|exists:inventory_items,id',

            'doc_medical_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:8192',
            'doc_medical_issue' => 'nullable|date|required_with:doc_medical_file,doc_medical_expiry',
            'doc_medical_expiry' => 'nullable|date|after_or_equal:doc_medical_issue|required_with:doc_medical_file,doc_medical_issue',
            'doc_insurance_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:8192',
            'doc_insurance_issue' => 'nullable|date|required_with:doc_insurance_file,doc_insurance_expiry',
            'doc_insurance_expiry' => 'nullable|date|after_or_equal:doc_insurance_issue|required_with:doc_insurance_file,doc_insurance_issue',
            'doc_identity_kind' => 'nullable|in:passport,birth_certificate|required_with:doc_identity_file',
            'doc_identity_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:8192',
            'doc_identity_series' => 'nullable|string|max:50|required_with:doc_identity_file,doc_identity_number,doc_identity_issued_by,doc_identity_issue_date',
            'doc_identity_number' => 'nullable|string|max:50|required_with:doc_identity_file,doc_identity_series,doc_identity_issued_by,doc_identity_issue_date',
            'doc_identity_issued_by' => 'nullable|string|max:255|required_with:doc_identity_file,doc_identity_series,doc_identity_number,doc_identity_issue_date',
            'doc_identity_issue_date' => 'nullable|date|required_with:doc_identity_file,doc_identity_series,doc_identity_number,doc_identity_issued_by',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function adminAccountRules(): array
    {
        return [
            'login' => LoginRules::validation(),
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function adminAccountMessages(): array
    {
        return [
            'login.required' => 'Укажите логин для входа спортсмена.',
            'email.unique' => 'Этот email уже используется.',
            'password.required' => 'Укажите пароль для аккаунта спортсмена.',
            'password.confirmed' => 'Пароли не совпадают.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'phone.regex' => 'Телефон укажите в формате +7 (999) 999-99-99.',
            'photo.max' => 'Фото не должно превышать 4 МБ.',
            'doc_medical_file.max' => 'Медицинская справка не более 8 МБ.',
            'doc_insurance_file.max' => 'Файл страховки не более 8 МБ.',
            'doc_identity_file.max' => 'Файл документа не более 8 МБ.',
            'doc_medical_file.mimes' => 'Мед. справка: PDF или изображение (JPG, PNG).',
            'doc_insurance_file.mimes' => 'Страховка: PDF или изображение (JPG, PNG).',
            'doc_identity_file.mimes' => 'Документ: PDF или изображение (JPG, PNG).',
        ];
    }
}
