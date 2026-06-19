<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AthleteNotificationService;
use App\Support\FormValidator;
use App\Support\LoginPasswordReset;
use App\Support\LoginRules;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class NewPasswordController extends Controller
{
    public function create(Request $request): Response
    {
        return Inertia::render('Auth/ResetPassword', [
            'login' => $request->query('login'),
            'token' => $request->route('token'),
        ]);
    }

    /**
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        FormValidator::validate($request, [
            'token' => 'required',
            'login' => ['required', 'string', 'max:50'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ], [], [
            'login' => 'логин',
        ]);

        $login = LoginRules::normalize($request->input('login'));
        $reset = LoginPasswordReset::reset($login, $request->input('token'), $request->input('password'));

        if (! $reset) {
            throw ValidationException::withMessages([
                'login' => [trans(Password::INVALID_TOKEN)],
            ]);
        }

        $user = \App\Models\User::query()->where('login', $login)->first();
        if ($user) {
            $user->forceFill(['remember_token' => Str::random(60)])->save();
            app(AthleteNotificationService::class)->notifyPasswordChanged($user);
            event(new PasswordReset($user));
        }

        return redirect()->route('login')->with('status', __(Password::PASSWORD_RESET));
    }
}
