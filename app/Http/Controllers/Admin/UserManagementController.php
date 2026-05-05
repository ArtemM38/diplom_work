<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Http\Request;
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

        return Inertia::render('Admin/Coaches/Index', [
            'users' => User::query()
                ->when($role && $role !== 'all', fn ($q) => $q->where('role', $role))
                ->when($active !== null && $active !== '' && $active !== 'all', fn ($q) => $q->where('is_active', $active === '1'))
                ->when($search, function ($query) use ($search) {
                    $query->where(function ($q) use ($search) {
                        $q->where('name', 'like', '%' . $search . '%')
                            ->orWhere('email', 'like', '%' . $search . '%');
                    });
                })
                ->orderBy('name')
                ->get(),
            'roles' => ['admin', 'accountant', 'coach', 'athlete', 'guardian'],
            'filters' => [
                'search' => $search,
                'role' => $role ?: 'all',
                'active' => ($active === null || $active === '') ? 'all' : $active,
            ],
        ]);
    }

    public function storeCoach(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
            'role' => ['required', Rule::in(['admin', 'accountant', 'coach', 'athlete', 'guardian'])],
            'is_active' => 'required|boolean',
        ]);

        User::create([
            'name' => $validated['name'],
            'email' => strtolower($validated['email']),
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
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
            'role' => ['required', Rule::in(['admin', 'accountant', 'coach', 'athlete', 'guardian'])],
            'password' => 'nullable|string|min:8',
        ]);

        $coach->name = $validated['name'];
        $coach->email = strtolower($validated['email']);
        $coach->is_active = $validated['is_active'];
        $coach->role = $validated['role'];

        if (!empty($validated['password'])) {
            $coach->password = Hash::make($validated['password']);
        }

        $coach->save();

        return redirect()->back()->with('success', 'Данные аккаунта обновлены');
    }

    public function destroyCoach(User $coach)
    {
        $coach->delete();

        return redirect()->back()->with('success', 'Аккаунт удален');
    }

    public function toggleStatus(User $coach)
    {
        if ($coach->is_active && $coach->role === 'coach') {
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

        $coach->is_active = !$coach->is_active;
        $coach->save();

        return redirect()->back()->with('success', 'Статус аккаунта обновлен');
    }
}
