<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\FormValidator;
use App\Support\LoginPasswordReset;
use App\Support\LoginRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetLinkController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPassword', [
            'status' => session('status'),
        ]);
    }

    /**
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        FormValidator::validate($request, [
            'login' => ['required', 'string', 'max:50'],
        ], [], [
            'login' => 'логин',
        ]);

        $user = User::query()
            ->where('login', LoginRules::normalize($request->input('login')))
            ->first();

        if ($user) {
            LoginPasswordReset::sendResetLink($user);
        }

        return back()->with('status', __('passwords.sent'));
    }
}
