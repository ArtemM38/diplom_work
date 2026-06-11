<?php

return [
    'templates' => [
        1 => [
            'title' => 'Заявление (приложение 1)',
            'extension' => 'docx',
            'formats' => ['docx', 'pdf'],
            'constructor' => false,
        ],
        2 => [
            'title' => 'Согласие на обработку данных (приложение 2)',
            'extension' => 'docx',
            'formats' => ['docx', 'pdf'],
            'constructor' => false,
        ],
        3 => [
            'title' => 'Анкета (приложение 3)',
            'extension' => 'docx',
            'formats' => ['docx', 'pdf'],
            'constructor' => false,
        ],
        4 => [
            'title' => 'Справка об обучении в школу (приложение 4)',
            'extension' => 'docx',
            'formats' => ['docx', 'pdf'],
            'constructor' => false,
        ],
        5 => [
            'title' => 'Заявление о сохранении места (приложение 5)',
            'extension' => 'docx',
            'formats' => ['docx', 'pdf'],
            'constructor' => true,
        ],
        6 => [
            'title' => 'Заявление на индивидуальный график (приложение 6)',
            'extension' => 'docx',
            'formats' => ['docx', 'pdf'],
            'constructor' => true,
        ],
        7 => [
            'title' => 'Счёт на оплату (приложение 7)',
            'extension' => 'xlsx',
            'formats' => ['xlsx', 'pdf'],
            'constructor' => false,
        ],
        8 => [
            'title' => 'Акт сверки (приложение 8)',
            'extension' => 'xls',
            'formats' => ['xls', 'xlsx', 'pdf'],
            'constructor' => false,
        ],
    ],
    'constructor_fields' => [
        5 => [
            ['name' => 'period_from', 'label' => 'Начало периода отсутствия', 'type' => 'date', 'required' => false],
            ['name' => 'period_to', 'label' => 'Конец периода отсутствия', 'type' => 'date', 'required' => false],
            ['name' => 'absence_reason', 'label' => 'Причина отсутствия', 'type' => 'textarea', 'required' => false],
        ],
        6 => [
            ['name' => 'period_from', 'label' => 'Начало периода', 'type' => 'date', 'required' => false],
            ['name' => 'period_to', 'label' => 'Конец периода', 'type' => 'date', 'required' => false],
            ['name' => 'schedule_description', 'label' => 'График посещения / причина', 'type' => 'textarea', 'required' => false],
        ],
    ],
    'fill' => [
        1 => [
            'rules' => [
                ['type' => 'paragraph_before_label', 'anchor' => 'ФИО заявителя в родительном падеже', 'var' => 'guardian_name_gen'],
                ['type' => 'slots_after_anchor', 'anchor' => 'Тел.:', 'vars' => ['guardian_phone']],
                ['type' => 'slots_after_anchor', 'anchor' => 'моего ребенка', 'vars' => ['athlete_fio_birth_year'], 'clear_remaining' => true],
                ['type' => 'slots_after_anchor', 'anchor' => 'проживающего по адресу', 'vars' => ['athlete_address'], 'clear_remaining' => true, 'strip_leading_underscores' => true],
                ['type' => 'document_date_footer', 'last' => true, 'must_contain' => 'г'],
            ],
        ],
        2 => [
            'rules' => [
                ['type' => 'paragraph_before_label', 'anchor' => 'ФИО законного представителя обучающегося', 'var' => 'guardian_name'],
                ['type' => 'paragraph_before_label', 'anchor' => 'фамилия, имя, отчество сына/дочери', 'var' => 'athlete_fio_birth_year'],
                ['type' => 'document_date_footer', 'last' => true, 'must_contain' => '/'],
            ],
        ],
        3 => [
            'rules' => [
                ['type' => 'line_after_label', 'anchor' => 'Фамилия', 'var' => 'athlete_last_name'],
                ['type' => 'line_after_label', 'anchor' => 'Имя', 'var' => 'athlete_first_name'],
                ['type' => 'line_after_label', 'anchor' => 'Отчество', 'var' => 'athlete_middle_name'],
                ['type' => 'table_cell_after_label', 'anchor' => 'Дата рождения', 'var' => 'athlete_birth_formatted'],
                ['type' => 'line_after_label', 'anchor' => 'Номер и вид образовательного учреждения, группа/класс', 'var' => 'education_place'],
                ['type' => 'line_after_label', 'anchor' => 'Данные об отце', 'var' => 'father_info'],
                ['type' => 'line_after_label', 'anchor' => 'Данные о матери', 'var' => 'mother_info'],
                ['type' => 'line_after_label', 'anchor' => 'Адрес по месту регистрации', 'var' => 'athlete_address'],
                ['type' => 'line_after_label', 'anchor' => 'Адрес фактического места проживания', 'var' => 'athlete_address'],
                ['type' => 'table_cell_after_label', 'anchor' => 'Начало занятий', 'var' => 'training_start_formatted'],
            ],
        ],
        4 => [
            'rules' => [
                ['type' => 'slots_after_anchor', 'anchor' => 'Директору', 'vars' => ['school_recipient_line'], 'all' => true, 'clear_remaining' => true],
                ['type' => 'slots_after_anchor', 'anchor' => 'Дана', 'vars' => ['athlete_fio'], 'all' => true, 'clear_remaining' => true],
                ['type' => 'slots_after_anchor', 'anchor' => 'дата рождения', 'vars' => ['athlete_birth_date'], 'all' => true],
                ['type' => 'slots_after_anchor', 'anchor' => 'Федерация Айкидо с', 'vars' => ['training_start_date'], 'all' => true, 'clear_remaining' => true],
                ['type' => 'document_date_footer', 'all' => true, 'must_contain' => 'г'],
            ],
        ],
        5 => [
            'rules' => [
                ['type' => 'paragraph_before_label', 'anchor' => 'ФИО заявителя в родительном падеже', 'var' => 'guardian_name_gen'],
                ['type' => 'slots_after_anchor', 'anchor' => 'Тел.:', 'vars' => ['guardian_phone']],
                ['type' => 'slots_after_anchor', 'anchor' => 'моим ребенком', 'vars' => ['athlete_fio_birth_year'], 'clear_remaining' => true, 'clear_next_underscore_paragraph' => true],
                ['type' => 'period_range'],
                ['type' => 'underscores_in_paragraph', 'anchor' => 'в связи с', 'vars' => ['absence_reason', 'absence_reason_cont']],
                ['type' => 'document_date_footer', 'last' => true, 'must_contain' => '/'],
            ],
        ],
        6 => [
            'rules' => [
                ['type' => 'paragraph_before_label', 'anchor' => 'ФИО заявителя в родительном падеже', 'var' => 'guardian_name_gen'],
                ['type' => 'slots_after_anchor', 'anchor' => 'Тел.:', 'vars' => ['guardian_phone']],
                ['type' => 'slots_after_anchor', 'anchor' => 'моего ребенка', 'vars' => ['athlete_fio_birth_year'], 'clear_remaining' => true, 'clear_next_underscore_paragraph' => true],
                ['type' => 'period_range'],
                ['type' => 'underscores_in_paragraph', 'anchor' => 'в связи с', 'vars' => ['schedule_description', 'schedule_description_cont']],
                ['type' => 'document_date_footer', 'last' => true, 'must_contain' => '/'],
            ],
        ],
        7 => [
            'cells' => [
                'B11' => 'invoice_header',
                'G15' => 'recipient_info',
                'G18' => 'payer_info',
            ],
        ],
        8 => [
            'text_slots' => [
                'date_day',
                'date_month',
                'date_year',
                'guardian_name',
                'organization_name',
            ],
        ],
    ],
];
