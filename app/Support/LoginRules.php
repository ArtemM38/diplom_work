<?php

namespace App\Support;

use Illuminate\Validation\Rule;

class LoginRules
{
    /**
     * @return array<int, mixed>
     */
    public static function validation(?int $ignoreUserId = null): array
    {
        $unique = Rule::unique('users', 'login');
        if ($ignoreUserId !== null) {
            $unique->ignore($ignoreUserId);
        }

        return [
            'required',
            'string',
            'lowercase',
            'min:3',
            'max:50',
            'regex:/^[a-z0-9][a-z0-9._-]*$/',
            $unique,
        ];
    }

    public static function normalize(?string $login): string
    {
        return strtolower(trim((string) $login));
    }
}
