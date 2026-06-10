<?php

namespace App\Support;

use App\Models\User;

class AdminPermissions
{
    /** Создание/редактирование групп, расписания, мероприятий — только администратор. */
    public static function canManageStructure(?User $user): bool
    {
        return $user?->hasRole('admin') ?? false;
    }

    /** Бухгалтер может менять только тариф группы. */
    public static function canEditGroupTariffOnly(?User $user): bool
    {
        return $user?->hasRole('accountant') && ! $user->hasRole('admin');
    }

    public static function isCoachOnly(?User $user): bool
    {
        return $user?->hasRole('coach')
            && ! $user->hasAnyRole(['admin', 'accountant']);
    }
}
