<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\FormValidator;
use App\Support\LoginRules;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = FormValidator::validate($request, [
            'name' => 'required|string|max:255',
            'login' => LoginRules::validation(),
            'email' => ['nullable', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'role' => 'required|in:athlete,guardian',
        ], [
            'role.in' => 'Выберите тип аккаунта: спортсмен или родитель.',
        ], [
            'login' => 'логин',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'login' => LoginRules::normalize($validated['login']),
            'email' => $validated['email'] ?: null,
            'password' => $validated['password'],
            'role' => $validated['role'],
            'roles' => [$validated['role']],
        ]);

        Auth::login($user);

        if ($user->role === 'athlete') {
            return redirect()->route('athlete.create');
        }

        if ($user->role === 'guardian') {
            return redirect()->route('guardian.create');
        }

        return redirect()->route('dashboard');
    }
}
