<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AthleteNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate(
            [
                'current_password' => ['required', 'current_password'],
                'password' => ['required', Password::defaults(), 'confirmed'],
            ],
            [],
            [
                'current_password' => 'текущий пароль',
                'password' => 'новый пароль',
                'password_confirmation' => 'подтверждение пароля',
            ],
        );

        $user = $request->user();
        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        app(AthleteNotificationService::class)->notifyPasswordChanged($user);

        return back();
    }
}
