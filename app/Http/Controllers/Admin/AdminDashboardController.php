<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Athlete;
use App\Models\Guardian;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class AdminDashboardController extends Controller
{
    public function index(Request $request)
    {
        $sortByAge = $request->input('sort_age');
        $startedFrom = $request->input('started_from');
        $startedTo = $request->input('started_to');

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

        if ($startedFrom) {
            $query->whereDate('created_at', '>=', $startedFrom);
        }

        if ($startedTo) {
            $query->whereDate('created_at', '<=', $startedTo);
        }

        if (in_array($sortByAge, ['asc', 'desc'], true)) {
            $query->orderBy('birth_date', $sortByAge === 'asc' ? 'desc' : 'asc');
        } else {
            $query->orderBy('last_name_nom')
                ->orderBy('first_name_nom')
                ->orderBy('middle_name_nom');
        }

        $athletes = $query->paginate(15)->withQueryString()->through(function ($athlete) {
            $age = Carbon::parse($athlete->birth_date)->age;

            return [
                'id' => $athlete->id,
                'full_name' => "{$athlete->last_name_nom} {$athlete->first_name_nom} {$athlete->middle_name_nom}",
                'phone' => $athlete->phone,
                'birth_date' => $athlete->birth_date,
                'age' => $age,
                'age_label' => $this->formatYears($age),
                'gender' => $athlete->gender,
                'photo' => $athlete->photo,
                'started_at' => optional($athlete->created_at)?->toDateString(),
                'current_rank' => $athlete->rankHistories->sortByDesc('assigned_at')->first()?->rank?->name ?? 'Не присвоен',
                'documents' => $athlete->documents->map(function ($doc) {
                    $daysLeft = $doc->expiry_date
                        ? now()->startOfDay()->diffInDays(Carbon::parse($doc->expiry_date)->startOfDay(), false)
                        : null;

                    return [
                        'type' => $doc->type,
                        'expiry_date' => $doc->expiry_date,
                        'is_expired' => $daysLeft !== null && $daysLeft < 0,
                        'is_warning' => $daysLeft !== null && $daysLeft >= 0 && $daysLeft <= 3,
                    ];
                }),
                'inventory_count' => collect($athlete->inventory)->filter(fn ($val) => $val === true || $val === 1)->count(),
            ];
        });

        return Inertia::render('Admin/AthletesList', [
            'athletes' => $athletes,
            'filters' => $request->only(['search', 'gender', 'sort_age', 'started_from', 'started_to']),
            'canEditAthlete' => $request->user()?->hasRole('admin') ?? false,
        ]);
    }

    public function show(Request $request, Athlete $athlete)
    {
        $athlete->load([
            'rankHistories.rank',
            'refereeHistories.refereeCategory',
            'documents',
            'inventory',
            'guardians',
            'groups',
        ]);

        $user = $request->user();
        $canEditAthlete = $user && $user->hasRole('admin');
        $canEditGuardians = $canEditAthlete;

        return Inertia::render('Admin/Athletes/Show', [
            'athlete' => $athlete,
            'age' => Carbon::parse($athlete->birth_date)->age,
            'ageLabel' => $this->formatYears(Carbon::parse($athlete->birth_date)->age),
            'canEditAthlete' => $canEditAthlete,
            'canEditGuardians' => $canEditGuardians,
            'canManageInventory' => $canEditAthlete,
        ]);
    }

    public function updateInventory(Request $request, Athlete $athlete)
    {
        abort_unless($request->user()?->hasRole('admin'), 403);

        $validated = $request->validate([
            'weapon_case' => 'boolean',
            'jo' => 'boolean',
            'boken' => 'boolean',
            'tanto' => 'boolean',
            'tshirt' => 'boolean',
            'olympic_jacket' => 'boolean',
            'cap' => 'boolean',
            'backpack' => 'boolean',
            'shoe_bag' => 'boolean',
            'budo_passport' => 'boolean',
            'qual_book' => 'boolean',
            'referee_book' => 'boolean',
        ]);

        $athlete->inventory()->updateOrCreate(
            ['athlete_id' => $athlete->id],
            collect($validated)->map(fn ($v) => (bool) $v)->all()
        );

        return back()->with('success', 'Инвентарь обновлён');
    }

    public function storeGuardian(Request $request, Athlete $athlete)
    {
        abort_unless($request->user()?->hasRole('admin'), 403);

        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'phone' => 'nullable|regex:/^\+7 \(\d{3}\) \d{3}-\d{2}-\d{2}$/',
            'relation' => 'required|string|max:255',
        ]);

        $guardian = Guardian::create($validated);
        $athlete->guardians()->syncWithoutDetaching([$guardian->id]);

        return back()->with('success', 'Законный представитель добавлен');
    }

    public function updateGuardian(Request $request, Athlete $athlete, Guardian $guardian)
    {
        abort_unless($request->user()?->hasRole('admin'), 403);
        abort_unless($athlete->guardians()->where('guardians.id', $guardian->id)->exists(), 404);

        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'phone' => 'nullable|regex:/^\+7 \(\d{3}\) \d{3}-\d{2}-\d{2}$/',
            'relation' => 'required|string|max:255',
        ]);

        $guardian->update($validated);

        return back()->with('success', 'Данные представителя обновлены');
    }

    private function formatYears(int $years): string
    {
        $mod100 = $years % 100;
        if ($mod100 >= 11 && $mod100 <= 14) {
            return $years . ' лет';
        }

        return match ($years % 10) {
            1 => $years . ' год',
            2, 3, 4 => $years . ' года',
            default => $years . ' лет',
        };
    }
}
