<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Athlete;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class AdminDashboardController extends Controller
{
    public function index(Request $request)
    {
        // 1. Начинаем запрос с подгрузкой всех связей
        $query = Athlete::with(['rankHistories.rank', 'documents', 'inventory']);

        // 2. Фильтрация по поиску (ФИО)
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('last_name_nom', 'like', '%' . $request->search . '%')
                    ->orWhere('first_name_nom', 'like', '%' . $request->search . '%');
            });
        }

        // 3. Фильтрация по полу
        if ($request->filled('gender')) {
            $query->where('gender', $request->gender);
        }

        // 4. Получаем данные и добавляем вычисляемые поля (возраст)
        $athletes = $query->latest()->get()->map(function ($athlete) {
            return [
                'id' => $athlete->id,
                'full_name' => "{$athlete->last_name_nom} {$athlete->first_name_nom} {$athlete->middle_name_nom}",
                'phone' => $athlete->phone,
                'birth_date' => $athlete->birth_date,
                'age' => Carbon::parse($athlete->birth_date)->age,
                'gender' => $athlete->gender,
                'photo' => $athlete->photo,
                // Берем последний присвоенный разряд
                'current_rank' => $athlete->rankHistories->sortByDesc('assigned_at')->first()?->rank?->name ?? 'Не присвоен',
                // Передаем статус документов
                'documents' => $athlete->documents->map(function ($doc) {
                    return [
                        'type' => $doc->type,
                        'expiry_date' => $doc->expiry_date,
                        'is_expired' => $doc->expiry_date ? Carbon::parse($doc->expiry_date)->isPast() : false,
                        'is_warning' => $doc->expiry_date ? Carbon::parse($doc->expiry_date)->diffInDays(now()) < 14 : false,
                    ];
                }),
                'inventory_count' => collect($athlete->inventory)->filter(fn($val) => $val === true || $val === 1)->count(),
            ];
        });

        return Inertia::render('Admin/AthletesList', [
            'athletes' => $athletes,
            'filters' => $request->only(['search', 'gender']),
        ]);
    }

    public function show(Athlete $athlete)
    {
        $athlete->load([
            'rankHistories.rank',
            'refereeHistories.refereeCategory',
            'documents',
            'inventory',
            'guardians',
            'groups',
        ]);

        return Inertia::render('Admin/Athletes/Show', [
            'athlete' => $athlete,
            'age' => Carbon::parse($athlete->birth_date)->age,
        ]);
    }
}
