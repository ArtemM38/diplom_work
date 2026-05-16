<?php

namespace App\Support;

class RoleLabels
{
    public const LABELS = [
        'admin' => 'Администратор',
        'accountant' => 'Бухгалтер',
        'coach' => 'Тренер',
        'athlete' => 'Спортсмен',
        'guardian' => 'Родитель',
    ];

    public static function label(?string $role): string
    {
        return self::LABELS[$role] ?? ($role ?? '—');
    }

    /**
     * @param  array<int, string>|null  $roles
     */
    public static function labelsList(?array $roles): string
    {
        if (empty($roles)) {
            return '—';
        }

        return collect($roles)
            ->map(fn (string $role) => self::label($role))
            ->unique()
            ->implode(', ');
    }
}
