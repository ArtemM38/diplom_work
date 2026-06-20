<?php

namespace App\Support;

use Illuminate\Http\Request;

class FormValidator
{
    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'required' => 'Поле «:attribute» обязательно для заполнения.',
            'email' => 'Укажите корректный email.',
            'unique' => 'Такое значение «:attribute» уже используется.',
            'confirmed' => 'Поле «:attribute» не совпадает с подтверждением.',
            'date' => 'Поле «:attribute» должно быть датой.',
            'before_or_equal' => 'Поле «:attribute» не может быть в будущем.',
            'after_or_equal' => 'Дата «:attribute» не может быть раньше связанной даты.',
            'numeric' => 'Поле «:attribute» должно быть числом.',
            'min.numeric' => 'Поле «:attribute» не может быть меньше :min.',
            'max.numeric' => 'Поле «:attribute» не может быть больше :max.',
            'integer' => 'Поле «:attribute» должно быть целым числом.',
            'in' => 'Выбрано недопустимое значение для «:attribute».',
            'regex' => 'Неверный формат поля «:attribute».',
            'file' => 'Поле «:attribute» должно быть файлом.',
            'image' => 'Поле «:attribute» должно быть изображением.',
            'mimes' => 'Файл «:attribute» должен быть формата: :values.',
            'max.file' => 'Размер файла «:attribute» не должен превышать :max КБ.',
            'exists' => 'Выбранное значение «:attribute» не найдено.',
            'required_if' => 'Поле «:attribute» обязательно при выбранных условиях.',
            'required_with' => 'Заполните поле «:attribute».',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function attributes(array $extra = []): array
    {
        return array_merge([
            'name' => 'имя',
            'login' => 'логин',
            'full_name' => 'ФИО',
            'email' => 'email',
            'password' => 'пароль',
            'password_confirmation' => 'подтверждение пароля',
            'role' => 'тип аккаунта',
            'roles' => 'роли',
            'phone' => 'телефон',
            'relation' => 'степень родства',
            'last_name_nom' => 'фамилия',
            'first_name_nom' => 'имя',
            'middle_name_nom' => 'отчество',
            'birth_date' => 'дата рождения',
            'gender' => 'пол',
            'occupation_type' => 'тип занятости',
            'school_name' => 'учебное заведение',
            'school_director_dat' => 'директор (дательный падеж)',
            'school_class' => 'класс',
            'kindergarten_name' => 'детский сад',
            'work_place' => 'место работы',
            'work_position' => 'должность',
            'registration_address' => 'адрес регистрации',
            'photo' => 'фото',
            'tariff_amount' => 'стоимость',
            'amount' => 'сумма',
            'reason' => 'причина',
            'discount' => 'скидка',
            'event_date' => 'дата мероприятия',
            'event_type_id' => 'тип мероприятия',
            'event_level_id' => 'уровень',
            'event_place' => 'место проведения',
            'event_host_id' => 'ведущий',
            'cost' => 'стоимость',
            'address' => 'адрес',
            'location_id' => 'зал',
            'group_id' => 'группа',
            'coach_id' => 'тренер',
            'lesson_date' => 'дата',
            'start_time' => 'время начала',
            'end_time' => 'время окончания',
            'cancellation_reason' => 'причина отмены',
            'athlete_id' => 'спортсмен',
            'doc_medical_file' => 'медицинская справка',
            'doc_insurance_file' => 'страховка',
            'doc_identity_file' => 'документ',
            'doc_identity_kind' => 'тип удостоверения',
        ], $extra);
    }

    /**
     * @param  array<string, mixed>  $rules
     * @param  array<string, string>  $extraMessages
     * @param  array<string, string>  $extraAttributes
     * @return array<string, mixed>
     */
    public static function validate(Request $request, array $rules, array $extraMessages = [], array $extraAttributes = []): array
    {
        return $request->validate(
            $rules,
            array_merge(self::messages(), $extraMessages),
            self::attributes($extraAttributes),
        );
    }
}
