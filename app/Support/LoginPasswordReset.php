<?php

namespace App\Support;

use App\Models\User;
use App\Notifications\ResetPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class LoginPasswordReset
{
    public static function sendResetLink(User $user): void
    {
        $token = Str::random(64);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['login' => $user->login],
            [
                'token' => Hash::make($token),
                'created_at' => now(),
            ],
        );

        $user->notify(new ResetPassword($token));
    }

    public static function reset(string $login, string $token, string $password): bool
    {
        $user = User::query()->where('login', LoginRules::normalize($login))->first();
        $record = DB::table('password_reset_tokens')
            ->where('login', LoginRules::normalize($login))
            ->first();

        if (! $user || ! $record) {
            return false;
        }

        if (! Hash::check($token, $record->token)) {
            return false;
        }

        $expires = (int) config('auth.passwords.users.expire', 60);
        if (now()->subMinutes($expires)->greaterThan($record->created_at)) {
            return false;
        }

        $user->forceFill(['password' => $password])->save();
        DB::table('password_reset_tokens')->where('login', $user->login)->delete();

        return true;
    }
}
