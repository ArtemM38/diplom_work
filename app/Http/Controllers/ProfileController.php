<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Support\AthleteDocumentGenerator;
use App\Support\RoleLabels;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user()->load([
            'athlete.rankHistories.rank',
            'athlete.refereeHistories.refereeCategory',
            'athlete.documents',
            'athlete.inventory',
            'athlete.groups',
            'athlete.guardians',
            'guardian.athletes',
        ]);

        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
            'profileData' => [
                'user' => [
                    'name' => $user->name,
                    'login' => $user->login,
                    'email' => $user->email,
                    'avatar_url' => $user->avatar_url,
                    'role' => $user->role,
                    'role_label' => RoleLabels::labelsList($user->getRolesList()),
                ],
                'athlete' => $user->athlete,
                'guardian' => $user->guardian,
                'children' => $user->guardian?->athletes ?? [],
                'documentTemplates' => AthleteDocumentGenerator::templateList(),
            ],
        ]);
    }

    public function updateAvatar(Request $request): RedirectResponse
    {
        $request->validate(
            ['avatar' => 'required|image|max:4096'],
            [
                'avatar.required' => 'Выберите изображение для загрузки.',
                'avatar.image' => 'Файл должен быть изображением (JPEG, PNG и т.д.).',
                'avatar.max' => 'Размер изображения не должен превышать 4 МБ.',
            ],
            ['avatar' => 'аватар'],
        );

        $user = $request->user();

        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
        }

        $user->avatar = $request->file('avatar')->store('avatars', 'public');
        $user->save();

        return Redirect::route('profile.edit')->with('status', 'avatar-updated');
    }

    public function updateGuardian(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasRole('guardian') && $user->guardian, 403);

        $validated = $request->validate(
            [
                'full_name' => 'required|string|max:255',
                'phone' => 'required|regex:/^\+7 \(\d{3}\) \d{3}-\d{2}-\d{2}$/',
                'relation' => 'required|string|max:255',
            ],
            [
                'phone.regex' => 'Укажите телефон в формате +7 (999) 999-99-99.',
            ],
            [
                'full_name' => 'ФИО',
                'phone' => 'телефон',
                'relation' => 'степень родства',
            ],
        );

        $user->guardian->update($validated);
        $user->update(['name' => $validated['full_name']]);

        return Redirect::route('profile.edit')->with('status', 'guardian-updated');
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): never
    {
        abort(403, 'Удаление собственного аккаунта недоступно.');
    }
}
