<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\Athlete;
use Illuminate\Http\Request;
use Inertia\Inertia;

class GroupController extends Controller
{
    // Список всех групп
    public function index()
    {
        return Inertia::render('Admin/Groups/Index', [
            'groups' => Group::withCount('athletes')->get()
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
        return Inertia::render('Admin/Groups/Show', [
            'group' => $group->load('athletes'), // Загружаем текущих участников
            // Список всех спортсменов для выпадающего списка (только ФИО и ID)
            'allAthletes' => \App\Models\Athlete::select('id', 'last_name_nom', 'first_name_nom')->get()
        ]);
    }

    // Зачисление спортсмена в группу
    public function attachAthlete(Request $request, Group $group)
    {
        $request->validate([
            'athlete_id' => 'required|exists:athletes,id'
        ]);

        // syncWithoutDetaching добавит связь, если её нет, и не затронет существующие
        $group->athletes()->syncWithoutDetaching($request->athlete_id);

        return redirect()->back()->with('success', 'Спортсмен зачислен в группу');
    }

    // Исключение из группы
    public function detachAthlete(Group $group, $athleteId)
    {
        $group->athletes()->detach($athleteId);
        return redirect()->back()->with('success', 'Спортсмен исключен из группы');
    }
}
