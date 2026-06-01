<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AthleteNotificationService;
use App\Models\Schedule;
use App\Models\User;
use App\Support\FormValidator;
use App\Support\RoleLabels;
use App\Support\UserAvatar;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class UserManagementController extends Controller
{
    public function index()
    {
        $search = request('search');
        $role = request('role');
        $active = request('active');

        $users = User::query()
            ->with(['athlete', 'guardian', 'roleModels'])
            ->when($role && $role !== 'all', fn ($q) => $q->whereHas('roleModels', fn ($r) => $r->where('slug', $role)))
            ->when($active !== null && $active !== '' && $active !== 'all', fn ($q) => $q->where('is_active', $active === '1'))
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%')
                        ->orWhere('email', 'like', '%' . $search . '%')
                        ->orWhereHas('guardian', fn ($g) => $g->where('full_name', 'like', '%' . $search . '%'))
                        ->orWhereHas('athlete', function ($a) use ($search) {
                            $a->where('last_name_nom', 'like', '%' . $search . '%')
                                ->orWhere('first_name_nom', 'like', '%' . $search . '%')
                                ->orWhere('middle_name_nom', 'like', '%' . $search . '%');
                        });
                });
            })
            ->orderBy('name')
            ->paginate(5)
            ->withQueryString()
            ->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'profile_name' => $user->display_name,
                'display_name' => $user->display_name,
                'email' => $user->email,
                'role' => $user->role,
                'roles' => $user->getRolesList(),
                'role_labels' => RoleLabels::labelsList($user->getRolesList()),
                'is_active' => $user->is_active,
                'is_self' => $user->id === Auth::id(),
                'avatar_url' => UserAvatar::url($user),
            ]);

        return Inertia::render('Admin/Coaches/Index', [
            'users' => $users,
            'roles' => ['admin', 'accountant', 'coach', 'athlete', 'guardian'],
            'roleLabels' => RoleLabels::LABELS,
            'filters' => [
                'search' => $search,
                'role' => $role ?: 'all',
                'active' => ($active === null || $active === '') ? 'all' : $active,
            ],
        ]);
    }

    public function show(User $user)
    {
        $user->load(['athlete', 'guardian.athletes', 'roleModels']);

        return Inertia::render('Admin/Users/Show', [
            'profileUser' => [
                'id' => $user->id,
                'name' => $user->name,
                'display_name' => $user->display_name,
                'email' => $user->email,
                'avatar_url' => UserAvatar::url($user),
                'role_labels' => RoleLabels::labelsList($user->getRolesList()),
                'roles' => $user->getRolesList(),
                'is_active' => $user->is_active,
            ],
            'athlete' => $user->athlete,
            'guardian' => $user->guardian,
            'children' => $user->guardian?->athletes ?? [],
        ]);
    }

    public function storeCoach(Request $request)
    {
        $validated = FormValidator::validate($request, [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
            'roles' => 'required|array|min:1',
            'roles.*' => Rule::in(['admin', 'accountant', 'coach', 'athlete', 'guardian']),
            'is_active' => 'sometimes|boolean',
        ], [
            'roles.min' => 'Выберите хотя бы одну роль.',
            'password.min' => 'Пароль не менее 8 символов.',
        ]);

        $roles = array_values(array_unique($validated['roles']));

        if (empty($roles)) {
            return redirect()->back()
                ->withErrors(['roles' => 'Выберите хотя бы одну роль.'])
                ->withInput();
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => strtolower($validated['email']),
            'password' => $validated['password'],
            'is_active' => $request->boolean('is_active'),
        ]);
        $user->syncRoles($roles);

        return redirect()->back()->with('success', 'Аккаунт добавлен');
    }

    public function updateCoach(Request $request, User $coach)
    {
        $validated = FormValidator::validate($request, [
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($coach->id)],
            'is_active' => 'required|boolean',
            'roles' => 'required|array|min:1',
            'roles.*' => Rule::in(['admin', 'accountant', 'coach', 'athlete', 'guardian']),
            'password' => 'nullable|string|min:8',
        ], [
            'roles.min' => 'Выберите хотя бы одну роль.',
            'password.min' => 'Пароль не менее 8 символов.',
        ]);

        if ($coach->id === Auth::id() && ! $validated['is_active']) {
            return redirect()->back()->with('error', 'Нельзя деактивировать собственный аккаунт.');
        }

        $coach->name = $validated['name'];
        $coach->email = strtolower($validated['email']);
        $coach->is_active = $validated['is_active'];
        $coach->syncRoles($validated['roles']);

        $passwordChanged = false;
        if (! empty($validated['password'])) {
            $coach->password = $validated['password'];
            $passwordChanged = true;
        }

        $coach->save();
        $this->syncProfileName($coach, $validated['name']);

        if ($passwordChanged) {
            app(AthleteNotificationService::class)->notifyPasswordChanged($coach);
        }

        return redirect()->back()->with('success', 'Данные аккаунта обновлены');
    }

    public function destroyCoach(User $coach)
    {
        if ($coach->id === Auth::id()) {
            return redirect()->back()->with('error', 'Нельзя удалить собственный аккаунт.');
        }

        $coach->delete();

        return redirect()->back()->with('success', 'Аккаунт удален');
    }

    public function toggleStatus(User $coach)
    {
        if ($coach->id === Auth::id()) {
            return redirect()->back()->with('error', 'Нельзя изменить активность собственного аккаунта.');
        }

        if ($coach->is_active && $coach->hasRole('coach')) {
            $hasFutureSchedules = Schedule::query()
                ->where('coach_id', $coach->id)
                ->where(function ($q) {
                    $q->whereDate('lesson_date', '>', now()->toDateString())
                        ->orWhere(function ($inner) {
                            $inner->whereDate('lesson_date', now()->toDateString())
                                ->whereTime('start_time', '>', now()->format('H:i:s'));
                        });
                })
                ->exists();

            if ($hasFutureSchedules) {
                return redirect()->back()->with('error', 'Нельзя деактивировать тренера: у него есть будущие тренировки.');
            }
        }

        $coach->is_active = ! $coach->is_active;
        $coach->save();

        return redirect()->back()->with('success', 'Статус аккаунта обновлен');
    }

    private function syncProfileName(User $user, string $fullName): void
    {
        $user->loadMissing(['guardian', 'athlete']);

        if ($user->guardian) {
            $user->guardian->update(['full_name' => $fullName]);
        }

        if ($user->athlete) {
            $parts = preg_split('/\s+/u', trim($fullName), 3, PREG_SPLIT_NO_EMPTY) ?: [];
            $user->athlete->update([
                'last_name_nom' => $parts[0] ?? $user->athlete->last_name_nom,
                'first_name_nom' => $parts[1] ?? $user->athlete->first_name_nom,
                'middle_name_nom' => $parts[2] ?? $user->athlete->middle_name_nom,
            ]);
        }
    }
}
