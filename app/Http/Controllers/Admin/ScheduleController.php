<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Athlete;
use App\Models\Attendance;
use App\Models\Group;
use App\Models\Location;
use App\Models\Schedule;
use App\Models\ScheduleCoachChange;
use App\Models\User;
use App\Support\GuardianChildAccess;
use App\Support\ScheduleAccess;
use App\Support\ScheduleConflictChecker;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ScheduleController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Schedule/Index', [
            'schedules' => Schedule::with(['group', 'location', 'coach', 'initialCoach'])
                ->orderBy('lesson_date')
                ->orderBy('start_time')
                ->get()
                ->map(fn (Schedule $s) => $this->schedulePayload($s)),
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
                'isGuardian' => false,
                'children' => [],
                'selectedAthlete' => null,
                'filters' => [],
            ]);
        }

        return Inertia::render('Athlete/ScheduleCalendar', $this->scheduleCalendarPayload($athlete, false, collect(), [
            'athlete_id' => $athlete->id,
        ]));
    }

    public function guardianCalendar(Request $request)
    {
        $user = $request->user();
        abort_unless($user?->hasRole('guardian'), 403);

        $children = GuardianChildAccess::childrenForGuardian($user);
        abort_if($children->isEmpty(), 404);

        $athleteId = GuardianChildAccess::resolveChildId($user, $request->integer('athlete_id') ?: null);
        $athlete = Athlete::findOrFail($athleteId);

        return Inertia::render('Athlete/ScheduleCalendar', $this->scheduleCalendarPayload($athlete, true, $children, [
            'athlete_id' => $athleteId,
        ]));
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array{id: int, full_name: string}>  $children
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function scheduleCalendarPayload(Athlete $athlete, bool $isGuardian, $children, array $filters): array
    {
        $groupIds = $athlete->groups()->pluck('groups.id');
        $attendanceBySchedule = Attendance::query()
            ->where('athlete_id', $athlete->id)
            ->whereIn('schedule_id', Schedule::query()->whereIn('group_id', $groupIds)->select('id'))
            ->pluck('status', 'schedule_id');

        $schedules = Schedule::query()
            ->with(['group', 'location', 'coach', 'initialCoach'])
            ->whereIn('group_id', $groupIds)
            ->orderBy('lesson_date')
            ->orderBy('start_time')
            ->get()
            ->map(function (Schedule $s) use ($attendanceBySchedule) {
                $payload = $this->schedulePayload($s);
                $payload['attendance_status'] = $attendanceBySchedule->get($s->id);

                return $payload;
            });

        return [
            'schedules' => $schedules,
            'isGuardian' => $isGuardian,
            'children' => $children->values(),
            'selectedAthlete' => [
                'id' => $athlete->id,
                'full_name' => GuardianChildAccess::fullName($athlete),
            ],
            'filters' => $filters,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function schedulePayload(Schedule $s): array
    {
        return [
            ...$s->toArray(),
            'coach' => $s->coach,
            'location' => $s->location,
            'location_address' => $s->location?->address,
            'initial_coach' => $s->initialCoach,
            'initial_coach_name' => $s->initialCoach?->name,
            'is_cancelled' => ScheduleAccess::isCancelled($s),
            'can_cancel' => ScheduleAccess::canCancel($s),
            'can_delete' => ScheduleAccess::canCancel($s),
            'can_mark_attendance' => ScheduleAccess::canMarkAttendance($s),
            'cancellation_reason_required' => ScheduleAccess::cancellationReasonRequired($s),
        ];
    }

    public function store(Request $request)
    {
        abort_if($request->user()?->hasRole('accountant') && ! $request->user()?->hasAnyRole(['admin', 'coach']), 403);

        $validated = $request->validate(
            [
                'group_id' => 'required|exists:groups,id',
                'location_id' => 'required|exists:locations,id',
                'coach_id' => 'required|exists:users,id',
                'lesson_date' => 'required|date',
                'start_time' => 'required|date_format:H:i',
                'end_time' => 'required|date_format:H:i|after:start_time',
            ],
            [],
            [
                'group_id' => 'группа',
                'location_id' => 'локация',
                'coach_id' => 'тренер',
                'lesson_date' => 'дата',
                'start_time' => 'время начала',
                'end_time' => 'время окончания',
            ],
        );

        if (ScheduleAccess::isInPast($validated['lesson_date'], $validated['start_time'])) {
            return back()->withErrors(['lesson_date' => 'Нельзя поставить тренировку на прошедшее время.'])->withInput();
        }

        if (! $this->isActiveCoach((int) $validated['coach_id'])) {
            return back()->withErrors(['coach_id' => 'Можно выбрать только активного тренера.'])->withInput();
        }

        $dayOfWeek = Carbon::parse($validated['lesson_date'])->dayOfWeekIso;

        $conflicts = ScheduleConflictChecker::conflicts($request);
        if ($conflicts !== []) {
            return back()->withErrors(['conflict' => ScheduleConflictChecker::message($conflicts)])->withInput();
        }

        Schedule::create([
            ...$validated,
            'day_of_week' => $dayOfWeek,
            'initial_coach_id' => $validated['coach_id'],
            'lesson_type' => $request->input('lesson_type', 'group'),
        ]);

        return back()->with('success', 'Занятие создано');
    }

    public function update(Request $request, Schedule $schedule)
    {
        abort_if($request->user()?->hasRole('accountant') && ! $request->user()?->hasAnyRole(['admin', 'coach']), 403);

        if (ScheduleAccess::isCancelled($schedule)) {
            return back()->with('error', 'Нельзя изменить отменённую тренировку.');
        }

        $validated = $request->validate(
            [
                'group_id' => 'required|exists:groups,id',
                'location_id' => 'required|exists:locations,id',
                'coach_id' => 'required|exists:users,id',
                'lesson_date' => 'required|date',
                'start_time' => 'required|date_format:H:i',
                'end_time' => 'required|date_format:H:i|after:start_time',
            ],
            [],
            [
                'group_id' => 'группа',
                'location_id' => 'локация',
                'coach_id' => 'тренер',
                'lesson_date' => 'дата',
                'start_time' => 'время начала',
                'end_time' => 'время окончания',
            ],
        );

        if (ScheduleAccess::isInPast($validated['lesson_date'], $validated['start_time'])) {
            return back()->withErrors(['lesson_date' => 'Нельзя поставить тренировку на прошедшее время.'])->withInput();
        }

        if (! $this->isActiveCoach((int) $validated['coach_id'])) {
            return back()->withErrors(['coach_id' => 'Можно выбрать только активного тренера.'])->withInput();
        }

        $conflicts = ScheduleConflictChecker::conflicts($request, $schedule->id);
        if ($conflicts !== []) {
            return back()->withErrors(['conflict' => ScheduleConflictChecker::message($conflicts)])->withInput();
        }

        $dayOfWeek = Carbon::parse($validated['lesson_date'])->dayOfWeekIso;
        $previousCoachId = $schedule->coach_id;

        if (! $schedule->initial_coach_id) {
            $validated['initial_coach_id'] = $previousCoachId;
        }

        $schedule->update([
            ...$validated,
            'day_of_week' => $dayOfWeek,
            'lesson_type' => $request->input('lesson_type', $schedule->lesson_type),
        ]);

        if ((int) $previousCoachId !== (int) $validated['coach_id']) {
            ScheduleCoachChange::query()->create([
                'schedule_id' => $schedule->id,
                'from_coach_id' => $previousCoachId,
                'to_coach_id' => (int) $validated['coach_id'],
                'changed_by_user_id' => $request->user()?->id,
                'created_at' => now(),
            ]);
        }

        return back()->with('success', 'Тренировка обновлена');
    }

    public function cancel(Request $request, Schedule $schedule)
    {
        abort_if($request->user()?->hasRole('accountant') && ! $request->user()?->hasAnyRole(['admin', 'coach']), 403);

        if (! ScheduleAccess::canCancel($schedule)) {
            return redirect()->back()->with('error', 'Тренировка уже отменена.');
        }

        $rules = [
            'cancellation_reason' => [
                Rule::requiredIf(ScheduleAccess::cancellationReasonRequired($schedule)),
                'nullable',
                'string',
                'max:1000',
            ],
        ];

        $validated = $request->validate(
            $rules,
            ['cancellation_reason.required' => 'Укажите причину отмены (менее чем за 5 часов до начала).'],
            ['cancellation_reason' => 'причина отмены'],
        );

        $schedule->update([
            'cancelled_at' => now(),
            'cancellation_reason' => $validated['cancellation_reason'] ?? null,
            'cancelled_by_user_id' => $request->user()?->id,
        ]);

        return redirect()->back()->with('success', 'Тренировка отменена');
    }

    /** @deprecated use cancel */
    public function destroy(Schedule $schedule)
    {
        return $this->cancel(request(), $schedule);
    }

    private function isActiveCoach(int $coachId): bool
    {
        return User::query()
            ->where('id', $coachId)
            ->withRole('coach')
            ->where('is_active', true)
            ->exists();
    }
}
