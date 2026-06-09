<?php

namespace App\Enums;

enum EventStatus: string
{
    case Planned = 'planned';
    case Completed = 'completed';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Planned => 'Запланировано',
            self::Completed => 'Проведено',
        };
    }
}
