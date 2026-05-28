<?php

namespace App\Support;

use App\Models\User;

class UserAvatar
{
    public static function url(User $user): ?string
    {
        if ($user->avatar) {
            return asset('storage/' . ltrim($user->avatar, '/'));
        }

        $user->loadMissing('athlete');

        if ($user->athlete?->photo) {
            return asset('storage/' . ltrim($user->athlete->photo, '/'));
        }

        return null;
    }
}
