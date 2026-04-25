<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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

        return Inertia::render('Admin/Coaches/Index', [
            'coaches' => User::where('role', 'coach')
                ->when($search, function ($query) use ($search) {
                    $query->where(function ($q) use ($search) {
                        $q->where('name', 'like', '%' . $search . '%')
                            ->orWhere('email', 'like', '%' . $search . '%');
                    });
                })
                ->orderBy('name')
                ->get(),
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    public function storeCoach(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
        ]);

        User::create([
            'name' => $validated['name'],
            'email' => strtolower($validated['email']),
            'password' => Hash::make($validated['password']),
            'role' => 'coach',
        ]);

        return redirect()->back()->with('success', 'Тренер добавлен');
    }

    public function updateCoach(Request $request, User $coach)
    {
        abort_unless($coach->role === 'coach', 404);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($coach->id)],
            'is_active' => 'required|boolean',
            'password' => 'nullable|string|min:8',
        ]);

        $coach->name = $validated['name'];
        $coach->email = strtolower($validated['email']);
        $coach->is_active = $validated['is_active'];

        if (!empty($validated['password'])) {
            $coach->password = Hash::make($validated['password']);
        }

        $coach->save();

        return redirect()->back()->with('success', 'Данные тренера обновлены');
    }

    public function destroyCoach(User $coach)
    {
        abort_unless($coach->role === 'coach', 404);
        $coach->delete();

        return redirect()->back()->with('success', 'Тренер удален');
    }
}
