<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\Athlete;
use App\Models\AthleteFinance;
use App\Support\AdminPermissions;
use App\Support\AthletePricing;
use App\Enums\GroupType;
use App\Support\FormValidator;
use Illuminate\Validation\Rule;
use Illuminate\Http\Request;
use Inertia\Inertia;

class GroupController extends Controller
{
    private function ensureGroupEditable(Group $group): void
    {
        abort_if($group->isArchived(), 403, 'Группа в архиве: редактирование недоступно.');
    }

    public function index()
    {
        $search = request('search');
        $showArchived = request('show_archived') === '1';

        $query = Group::withCount('athletes')
            ->when($search, fn ($q) => $q->where('name', 'like', '%' . $search . '%'));

        if ($showArchived) {
            $query->withTrashed()->where(function ($q) {
                $q->where('status', 'archived')->orWhereNotNull('deleted_at');
            });
        } else {
            $query->visible();
        }

        $groups = $query->orderBy('name')->paginate(15)->withQueryString();

        $user = request()->user();

        return Inertia::render('Admin/Groups/Index', [
            'groups' => $groups,
            'canCreateGroups' => AdminPermissions::canManageStructure($user),
            'tariffOnlyMode' => AdminPermissions::canEditGroupTariffOnly($user),
            'filters' => [
                'search' => $search,
                'show_archived' => $showArchived,
            ],
        ]);
    }

    public function store(Request $request)
    {
        abort_unless(AdminPermissions::canManageStructure($request->user()), 403);
        $validated = FormValidator::validate($request, [
            'name' => 'required|string|max:255',
            'type' => ['required', Rule::in(GroupType::values())],
            'tariff_amount' => 'required|numeric|min:0',
        ]);

        Group::create($validated);

        return redirect()->back()->with('success', 'Группа создана');
    }

    public function show(Group $group)
    {
        if ($group->isArchived()) {
            return redirect()
                ->route('admin.groups', ['show_archived' => '1'])
                ->with('info', 'Архивная группа: восстановите её из списка архива, чтобы снова управлять составом.');
        }

        $athleteSearch = trim((string) request('athlete_search'));
        $allAthletes = Athlete::select('id', 'last_name_nom', 'first_name_nom')
            ->when($athleteSearch !== '', function ($query) use ($athleteSearch) {
                $query->where(function ($q) use ($athleteSearch) {
                    $q->where('last_name_nom', 'like', '%' . $athleteSearch . '%')
                        ->orWhere('first_name_nom', 'like', '%' . $athleteSearch . '%');
                });
            })
            ->orderBy('last_name_nom')
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('Admin/Groups/Show', [
            'group' => $group->load('athletes'),
            'allAthletes' => $allAthletes,
            'filters' => [
                'athlete_search' => $athleteSearch,
            ],
        ]);
    }

    public function attachAthlete(Request $request, Group $group)
    {
        $this->ensureGroupEditable($group);
        abort_unless(AdminPermissions::canManageStructure($request->user()), 403);
        FormValidator::validate($request, [
            'athlete_id' => 'required|exists:athletes,id',
        ]);

        $athleteId = (int) $request->athlete_id;
        $finance = AthleteFinance::firstOrCreate(['athlete_id' => $athleteId], ['balance' => 0]);
        $trainingPrice = AthletePricing::effectivePrice((float) $group->tariff_amount, $finance);

        $group->athletes()->syncWithoutDetaching([
            $athleteId => ['training_price' => $trainingPrice],
        ]);
        $group->athletes()->updateExistingPivot($athleteId, ['training_price' => $trainingPrice]);

        return redirect()->back()->with('success', 'Спортсмен зачислен в группу');
    }

    public function detachAthlete(Group $group, $athleteId)
    {
        $this->ensureGroupEditable($group);
        abort_unless(AdminPermissions::canManageStructure(request()->user()), 403);
        $group->athletes()->detach($athleteId);

        return redirect()->back()->with('success', 'Спортсмен исключен из группы');
    }

    public function update(Request $request, Group $group)
    {
        $this->ensureGroupEditable($group);

        if (AdminPermissions::canEditGroupTariffOnly($request->user())) {
            $validated = FormValidator::validate($request, [
                'tariff_amount' => 'required|numeric|min:0',
            ]);
            $group->update(['tariff_amount' => $validated['tariff_amount']]);

            return redirect()->back()->with('success', 'Стоимость тренировки обновлена');
        }

        abort_unless(AdminPermissions::canManageStructure($request->user()), 403);

        $validated = FormValidator::validate($request, [
            'name' => 'required|string|max:255',
            'type' => ['required', Rule::in(GroupType::values())],
            'tariff_amount' => 'required|numeric|min:0',
            'status' => 'required|in:active,inactive,archived',
        ]);

        $group->update($validated);

        return redirect()->back()->with('success', 'Группа обновлена');
    }

    public function restore(int $group)
    {
        abort_unless(AdminPermissions::canManageStructure(request()->user()), 403);

        $group = Group::withTrashed()->findOrFail($group);

        if (! $group->isArchived()) {
            return redirect()->back()->with('info', 'Группа не находится в архиве.');
        }

        if ($group->trashed()) {
            $group->restore();
        }

        $group->update([
            'status' => 'active',
            'archived_at' => null,
        ]);

        return redirect()
            ->route('admin.groups')
            ->with('success', 'Группа восстановлена из архива.');
    }

    public function destroy(Group $group)
    {
        abort_unless(AdminPermissions::canManageStructure(request()->user()), 403);
        $this->ensureGroupEditable($group);

        if ($group->hasTrainingHistory()) {
            $group->update([
                'status' => 'archived',
                'archived_at' => now(),
            ]);
            $group->delete();

            return redirect()->route('admin.groups')->with('success', 'Группа перенесена в архив (были тренировки или списания).');
        }

        $group->delete();

        return redirect()->route('admin.groups')->with('success', 'Группа удалена');
    }
}
