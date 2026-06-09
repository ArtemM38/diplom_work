<?php

namespace App\Enums;

enum DocumentType: string
{
    case Medical = 'medical';
    case Insurance = 'insurance';
    case Identity = 'identity';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Medical => 'Медицинская справка',
            self::Insurance => 'Страховой полис',
            self::Identity => 'Удостоверение личности',
        };
    }
}
