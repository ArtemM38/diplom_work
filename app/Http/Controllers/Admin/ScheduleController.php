<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use App\Models\Group;
use App\Models\Location;
use App\Models\User;
use App\Support\ScheduleAccess;
use App\Support\ScheduleConflictChecker;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ScheduleController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Schedule/Index', [
            'schedules' => Schedule::with(['group', 'location', 'coach'])->get()->map(fn (Schedule $s) => [
                ...$s->toArray(),
                'can_delete' => ScheduleAccess::canDelete($s),
                'can_mark_attendance' => ScheduleAccess::canMarkAttendance($s),
            ]),
            'groups' => Group::visible()->where('status', 'active')->get(),
            'locations' => Location::all(),
            'coaches' => User::withRole('coach')->where('is_active', true)->get(),
        ]);
    }

    public function athleteCalendar(Request $request)
    {
        $user = $request->user();
        abort_unless($user?->hasRole('athlete'), 403);

        $athlete = $user->athlete;
        if (! $athlete) {
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
        abort_if($request->user()?->hasRole('accountant') && ! $request->user()?->hasAnyRole(['admin', 'coach']), 403);

        $request->validate([
            'group_id' => 'required|exists:groups,id',
            'location_id' => 'required|exists:locations,id',
            'coach_id' => 'required|exists:users,id',
            'lesson_date' => 'required|date',
            'start_time' => 'required',
            'end_time' => 'required|after:start_time',
        ]);

        $isActiveCoach = User::where('id', $request->coach_id)
            ->withRole('coach')
            ->where('is_active', true)
            ->exists();
        if (! $isActiveCoach) {
            return back()->withErrors(['coach_id' => 'Можно выбрать только активного тренера.']);
        }

        $dayOfWeek = \Carbon\Carbon::parse($request->lesson_date)->dayOfWeekIso;

        $conflicts = ScheduleConflictChecker::conflicts($request);
        if ($conflicts !== []) {
            return back()->withErrors(['conflict' => ScheduleConflictChecker::message($conflicts)]);
        }

        Schedule::create(array_merge($request->all(), ['day_of_week' => $dayOfWeek]));

        return back()->with('success', 'Занятие создано');
    }

    public function update(Request $request, Schedule $schedule)
    {
        abort_if($request->user()?->hasRole('accountant') && ! $request->user()?->hasAnyRole(['admin', 'coach']), 403);

        $request->validate([
            'group_id' => 'required|exists:groups,id',
            'location_id' => 'required|exists:locations,id',
            'coach_id' => 'required|exists:users,id',
            'lesson_date' => 'required|date',
            'start_time' => 'required',
            'end_time' => 'required|after:start_time',
        ]);

        $isActiveCoach = User::where('id', $request->coach_id)
            ->withRole('coach')
            ->where('is_active', true)
            ->exists();
        if (! $isActiveCoach) {
            return back()->withErrors(['coach_id' => 'Можно выбрать только активного тренера.']);
        }

        $conflicts = ScheduleConflictChecker::conflicts($request, $schedule->id);
        if ($conflicts !== []) {
            return back()->withErrors(['conflict' => ScheduleConflictChecker::message($conflicts)]);
        }

        $dayOfWeek = \Carbon\Carbon::parse($request->lesson_date)->dayOfWeekIso;
        $schedule->update(array_merge($request->all(), ['day_of_week' => $dayOfWeek]));

        return back()->with('success', 'Тренировка обновлена');
    }

    public function destroy(Schedule $schedule)
    {
        abort_if(request()->user()?->hasRole('accountant') && ! request()->user()?->hasAnyRole(['admin', 'coach']), 403);

        if (! ScheduleAccess::canDelete($schedule)) {
            return redirect()->back()->with('error', 'Удалить тренировку можно не позднее чем за 10 минут до начала.');
        }

        $schedule->delete();

        return redirect()->back()->with('success', 'Тренировка удалена');
    }
}
