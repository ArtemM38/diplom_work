<?php

namespace App\Support;

use App\Models\User;

class UserAvatar
{
    public static function url(User $user): ?string
    {
        if ($user->avatar) {
            return StorageUrl::url($user->avatar);
        }

        $user->loadMissing('athlete');

        if ($user->athlete?->photo) {
            return StorageUrl::url($user->athlete->photo);
        }

        return null;
    }
}
