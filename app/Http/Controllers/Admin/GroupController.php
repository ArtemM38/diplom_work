<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\Athlete;
use App\Models\AthleteFinance;
use Illuminate\Http\Request;
use Inertia\Inertia;

class GroupController extends Controller
{
    // Список всех групп
    public function index()
    {
        $search = request('search');

        $groups = Group::withCount('athletes')
            ->when($search, function ($query) use ($search) {
                $query->where('name', 'like', '%' . $search . '%');
            })
            ->orderBy('name')
            ->get();

        return Inertia::render('Admin/Groups/Index', [
            'groups' => $groups,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    // Сохранение новой группы
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string',
            'tariff_amount' => 'required|numeric',
        ]);

        Group::create($validated);
        return redirect()->back()->with('success', 'Группа создана');
    }

    // Страница управления составом группы
    public function show(Group $group)
    {
        $athleteSearch = trim((string) request('athlete_search'));
        $allAthletes = Athlete::select('id', 'last_name_nom', 'first_name_nom')
            ->when($athleteSearch !== '', function ($query) use ($athleteSearch) {
                $query->where(function ($q) use ($athleteSearch) {
                    $q->where('last_name_nom', 'like', '%' . $athleteSearch . '%')
                        ->orWhere('first_name_nom', 'like', '%' . $athleteSearch . '%');
                });
            })
            ->orderBy('last_name_nom')
            ->limit(300)
            ->get();

        return Inertia::render('Admin/Groups/Show', [
            'group' => $group->load('athletes'),
            'allAthletes' => $allAthletes,
            'filters' => [
                'athlete_search' => $athleteSearch,
            ],
        ]);
    }

    // Зачисление спортсмена в группу
    public function attachAthlete(Request $request, Group $group)
    {
        $request->validate([
            'athlete_id' => 'required|exists:athletes,id',
        ]);

        $athleteId = (int) $request->athlete_id;
        $trainingPrice = (float) $group->tariff_amount;

        $group->athletes()->syncWithoutDetaching([
            $athleteId => ['training_price' => $trainingPrice],
        ]);
        $group->athletes()->updateExistingPivot($athleteId, ['training_price' => $trainingPrice]);

        AthleteFinance::firstOrCreate(['athlete_id' => $athleteId], [
            'balance' => 0,
        ]);

        return redirect()->back()->with('success', 'Спортсмен зачислен в группу');
    }

    // Исключение из группы
    public function detachAthlete(Group $group, $athleteId)
    {
        $group->athletes()->detach($athleteId);
        return redirect()->back()->with('success', 'Спортсмен исключен из группы');
    }

    public function update(Request $request, Group $group)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|max:100',
            'tariff_amount' => 'required|numeric|min:0',
            'status' => 'required|in:active,inactive',
        ]);

        $group->update($validated);

        return redirect()->back()->with('success', 'Группа обновлена');
    }

    public function destroy(Group $group)
    {
        $group->delete();

        return redirect()->route('admin.groups')->with('success', 'Группа удалена');
    }
}
