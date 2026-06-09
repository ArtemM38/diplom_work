<?php

namespace App\Enums;

enum GroupType: string
{
    case Study = 'Учебная';
    case Seminar = 'Семинар';
    case Attestation = 'Аттестация';
    case Camp = 'Спортивные сборы';
    case Competition = 'Соревнования';
    case Intensive = 'Интенсивные тренировки';
    case Individual = 'Индивидуальные тренировки';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
