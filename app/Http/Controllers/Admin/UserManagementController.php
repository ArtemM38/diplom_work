<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use App\Models\User;
use App\Support\RoleLabels;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
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
            ->with(['athlete', 'guardian'])
            ->when($role && $role !== 'all', function ($q) use ($role) {
                $q->where(function ($query) use ($role) {
                    $query->where('role', $role)
                        ->orWhereJsonContains('roles', $role);
                });
            })
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
            ->paginate(20)
            ->withQueryString()
            ->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'display_name' => $user->display_name,
                'email' => $user->email,
                'role' => $user->role,
                'roles' => $user->getRolesList(),
                'role_labels' => RoleLabels::labelsList($user->getRolesList()),
                'is_active' => $user->is_active,
                'is_self' => $user->id === Auth::id(),
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
        $user->load(['athlete', 'guardian.athletes']);

        return Inertia::render('Admin/Users/Show', [
            'profileUser' => [
                'id' => $user->id,
                'name' => $user->name,
                'display_name' => $user->display_name,
                'email' => $user->email,
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
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
            'roles' => 'required|array|min:1',
            'roles.*' => Rule::in(['admin', 'accountant', 'coach', 'athlete', 'guardian']),
            'is_active' => 'required|boolean',
        ]);

        $roles = array_values(array_unique($validated['roles']));

        User::create([
            'name' => $validated['name'],
            'email' => strtolower($validated['email']),
            'password' => Hash::make($validated['password']),
            'role' => $roles[0],
            'roles' => $roles,
            'is_active' => $validated['is_active'],
        ]);

        return redirect()->back()->with('success', 'Аккаунт добавлен');
    }

    public function updateCoach(Request $request, User $coach)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($coach->id)],
            'is_active' => 'required|boolean',
            'roles' => 'required|array|min:1',
            'roles.*' => Rule::in(['admin', 'accountant', 'coach', 'athlete', 'guardian']),
            'password' => 'nullable|string|min:8',
        ]);

        if ($coach->id === Auth::id() && ! $validated['is_active']) {
            return redirect()->back()->with('error', 'Нельзя деактивировать собственный аккаунт.');
        }

        $coach->name = $validated['name'];
        $coach->email = strtolower($validated['email']);
        $coach->is_active = $validated['is_active'];
        $coach->syncRoles($validated['roles']);

        if (! empty($validated['password'])) {
            $coach->password = Hash::make($validated['password']);
        }

        $coach->save();

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
}
