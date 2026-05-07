<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use App\Models\Group;
use App\Models\Location;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ScheduleController extends Controller
{
    private function hasConflict(Request $request, ?int $ignoreScheduleId = null): bool
    {
        return Schedule::query()
            ->when($ignoreScheduleId, fn ($q) => $q->where('id', '!=', $ignoreScheduleId))
            ->where('lesson_date', $request->lesson_date)
            ->where(function ($q) use ($request) {
                $q->where('location_id', $request->location_id)
                    ->orWhere('coach_id', $request->coach_id);
            })
            ->where(function ($q) use ($request) {
                $q->where('start_time', '<', $request->end_time)
                    ->where('end_time', '>', $request->start_time);
            })
            ->exists();
    }

    public function index()
    {
        return Inertia::render('Admin/Schedule/Index', [
            'schedules' => Schedule::with(['group', 'location', 'coach'])->get(),
            'groups' => Group::where('status', 'active')->get(),
            'locations' => Location::all(),
            'coaches' => User::where('role', 'coach')->where('is_active', true)->get(),
        ]);
    }

    public function athleteCalendar(Request $request)
    {
        $user = $request->user();
        abort_unless($user?->role === 'athlete', 403);

        $athlete = $user->athlete;
        if (!$athlete) {
            return Inertia::render('Athlete/ScheduleCalendar', [
                'schedules' => [],
            ]);
        }

        $groupIds = $athlete->groups()->pluck('groups.id');
        $schedules = Schedule::query()
            ->with(['group', 'location', 'coach'])
            ->whereIn('group_id', $groupIds)
            ->orderBy('lesson_date')
            ->orderBy('start_time')
            ->get();

        return Inertia::render('Athlete/ScheduleCalendar', [
            'schedules' => $schedules,
        ]);
    }

    public function store(Request $request)
    {
        abort_if($request->user()?->role === 'accountant', 403);

        $request->validate([
            'group_id' => 'required|exists:groups,id',
            'location_id' => 'required|exists:locations,id',
            'coach_id' => 'required|exists:users,id',
            'lesson_date' => 'required|date', // Обязательная дата
            'start_time' => 'required',
            'end_time' => 'required|after:start_time',
        ]);

        $isActiveCoach = User::where('id', $request->coach_id)
            ->where('role', 'coach')
            ->where('is_active', true)
            ->exists();
        if (!$isActiveCoach) {
            return back()->withErrors(['coach_id' => 'Можно выбрать только активного тренера.']);
        }

        // Вычисляем день недели (1-7) из даты для совместимости
        $dayOfWeek = \Carbon\Carbon::parse($request->lesson_date)->dayOfWeekIso;

        if ($this->hasConflict($request)) {
            return back()->withErrors(['conflict' => 'В этот день зал или тренер уже заняты в это время!']);
        }

        Schedule::create(array_merge($request->all(), ['day_of_week' => $dayOfWeek]));
        return back()->with('success', 'Занятие создано');
    }

    public function update(Request $request, Schedule $schedule)
    {
        abort_if($request->user()?->role === 'accountant', 403);

        $request->validate([
            'group_id' => 'required|exists:groups,id',
            'location_id' => 'required|exists:locations,id',
            'coach_id' => 'required|exists:users,id',
            'lesson_date' => 'required|date',
            'start_time' => 'required',
            'end_time' => 'required|after:start_time',
        ]);

        $isActiveCoach = User::where('id', $request->coach_id)
            ->where('role', 'coach')
            ->where('is_active', true)
            ->exists();
        if (!$isActiveCoach) {
            return back()->withErrors(['coach_id' => 'Можно выбрать только активного тренера.']);
        }

        if ($this->hasConflict($request, $schedule->id)) {
            return back()->withErrors(['conflict' => 'В этот день зал или тренер уже заняты в это время!']);
        }

        $dayOfWeek = \Carbon\Carbon::parse($request->lesson_date)->dayOfWeekIso;
        $schedule->update(array_merge($request->all(), ['day_of_week' => $dayOfWeek]));

        return back()->with('success', 'Тренировка обновлена');
    }

    public function destroy(Schedule $schedule)
    {
        abort_if(request()->user()?->role === 'accountant', 403);
        $schedule->delete();
        return redirect()->back();
    }

}
