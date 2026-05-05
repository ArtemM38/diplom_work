<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Athlete;
use App\Models\Schedule;
use App\Models\Attendance;
use App\Models\AthleteFinance;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AttendanceController extends Controller
{
    private function scheduleStarted(Schedule $schedule): bool
    {
        if (!$schedule->lesson_date || !$schedule->start_time) {
            return false;
        }

        $start = \Carbon\Carbon::parse($schedule->lesson_date . ' ' . $schedule->start_time);
        return now()->greaterThanOrEqualTo($start);
    }

    // Открыть страницу отметки для конкретной тренировки
    public function show(Schedule $schedule)
    {
        if (!$this->scheduleStarted($schedule)) {
            return redirect()->route('admin.schedule')->with('error', 'Отметку можно ставить только после начала тренировки.');
        }

        // Загружаем тренировку вместе с группой и её спортсменами
        $schedule->load(['group.athletes', 'attendances']);

        return Inertia::render('Admin/Attendance/Mark', [
            'schedule' => $schedule,
            // Передаем существующих спортсменов группы
            'athletes' => $schedule->group->athletes,
            // Передаем уже сохраненные отметки (если они есть)
            'existingAttendances' => $schedule->attendances->pluck('status', 'athlete_id')
        ]);
    }

    // Сохранить отметки
    public function store(Request $request, Schedule $schedule)
    {
        if (!$this->scheduleStarted($schedule)) {
            return redirect()->route('admin.schedule')->with('error', 'Отметку можно сохранять только после начала тренировки.');
        }

        $schedule->load('group.athletes');
        $priceByAthlete = $schedule->group?->athletes
            ?->mapWithKeys(fn ($athlete) => [$athlete->id => (float) ($athlete->pivot->training_price ?? 0)]) ?? collect();

        DB::transaction(function () use ($request, $schedule, $priceByAthlete) {
            foreach ($request->attendance as $athleteId => $status) {
                $attendance = Attendance::updateOrCreate(
                    ['schedule_id' => $schedule->id, 'athlete_id' => $athleteId],
                    ['status' => $status]
                );

                // Списываем при Я и Н.
                if (!in_array($status, ['Я', 'Н'], true)) {
                    continue;
                }

                $finance = AthleteFinance::firstOrCreate(['athlete_id' => $athleteId], [
                    'balance' => 0,
                ]);

                $price = (float) ($priceByAthlete->get((int) $athleteId, 0));
                if ($price <= 0) {
                    continue;
                }

                $alreadyCharged = \App\Models\AthleteBalanceHistory::query()
                    ->where('attendance_id', $attendance->id)
                    ->exists();

                if ($alreadyCharged) {
                    continue;
                }

                $before = (float) $finance->balance;
                $after = round($before - $price, 2);
                $finance->update(['balance' => $after]);

                $reasonText = 'Списание за тренировку';
                if ($schedule->group?->name && $schedule->lesson_date) {
                    $reasonText = sprintf(
                        'Списание за тренировку, группа "%s", дата %s',
                        $schedule->group->name,
                        $schedule->lesson_date
                    );
                }

                \App\Models\AthleteBalanceHistory::create([
                    'athlete_id' => $athleteId,
                    'schedule_id' => $schedule->id,
                    'attendance_id' => $attendance->id,
                    'change_amount' => -$price,
                    'balance_before' => $before,
                    'balance_after' => $after,
                    'reason' => $reasonText,
                    'status' => $status,
                    'changed_by' => $request->user()?->id,
                ]);
            }
        });

        return redirect()->route('admin.schedule')->with('success', 'Посещаемость сохранена');
    }

    public function journal(Request $request)
    {
        $search = $request->input('search');
        $athleteId = $request->integer('athlete_id');
        $scheduleId = $request->integer('schedule_id');

        $athletes = Athlete::query()
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('last_name_nom', 'like', '%' . $search . '%')
                        ->orWhere('first_name_nom', 'like', '%' . $search . '%');
                });
            })
            ->orderBy('last_name_nom')
            ->get()
            ->map(function (Athlete $athlete) {
                return [
                    'id' => $athlete->id,
                    'full_name' => trim($athlete->last_name_nom . ' ' . $athlete->first_name_nom . ' ' . ($athlete->middle_name_nom ?? '')),
                ];
            });

        $selectedAthlete = $athleteId ? Athlete::find($athleteId) : null;
        $schedules = Schedule::query()
            ->with('group')
            ->whereNotNull('lesson_date')
            ->orderByDesc('lesson_date')
            ->orderByDesc('start_time')
            ->get()
            ->map(fn (Schedule $schedule) => [
                'id' => $schedule->id,
                'lesson_date' => $schedule->lesson_date,
                'group_name' => $schedule->group?->name,
                'start_time' => $schedule->start_time,
                'end_time' => $schedule->end_time,
            ]);

        $scheduleAthletes = collect();
        if ($scheduleId) {
            $selectedSchedule = Schedule::query()
                ->with(['group.athletes', 'attendances'])
                ->find($scheduleId);

            if ($selectedSchedule) {
                $attendanceByAthlete = $selectedSchedule->attendances->keyBy('athlete_id');
                $scheduleAthletes = $selectedSchedule->group?->athletes
                    ?->map(function (Athlete $athlete) use ($attendanceByAthlete) {
                        return [
                            'id' => $athlete->id,
                            'full_name' => trim($athlete->last_name_nom . ' ' . $athlete->first_name_nom . ' ' . ($athlete->middle_name_nom ?? '')),
                            'status' => $attendanceByAthlete->get($athlete->id)?->status ?? 'Н',
                        ];
                    })
                    ->values() ?? collect();
            }
        }

        $rows = collect();
        $calendar = [];
        $stats = ['present' => 0, 'absent' => 0, 'excused' => 0];

        if ($athleteId) {
            $attendances = Attendance::with(['schedule.group'])
                ->where('athlete_id', $athleteId)
                ->orderByDesc('id')
                ->get();

            $rows = $attendances->map(function (Attendance $attendance) {
                return [
                    'id' => $attendance->id,
                    'group' => $attendance->schedule?->group?->name,
                    'lesson_date' => $attendance->schedule?->lesson_date,
                    'status' => $attendance->status,
                ];
            });

            $stats = [
                'present' => $rows->where('status', 'Я')->count(),
                'absent' => $rows->where('status', 'Н')->count(),
                'excused' => $rows->where('status', 'У')->count(),
            ];

            $schedules = Schedule::query()
                ->with('group')
                ->whereHas('group.athletes', fn($q) => $q->where('athletes.id', $athleteId))
                ->whereNotNull('lesson_date')
                ->orderBy('lesson_date')
                ->get();

            $attendanceBySchedule = $attendances->keyBy('schedule_id');

            $calendar = $schedules
                ->groupBy('lesson_date')
                ->map(function ($daySchedules, $date) use ($attendanceBySchedule) {
                    return [
                        'date' => $date,
                        'entries' => $daySchedules->map(function (Schedule $schedule) use ($attendanceBySchedule) {
                            return [
                                'group' => $schedule->group?->name,
                                'start_time' => $schedule->start_time,
                                'end_time' => $schedule->end_time,
                                'status' => $attendanceBySchedule->get($schedule->id)?->status ?? 'Н',
                            ];
                        })->values(),
                    ];
                })
                ->values();
        }

        return Inertia::render('Admin/Attendance/Journal', [
            'athletes' => $athletes,
            'schedules' => $schedules,
            'scheduleAthletes' => $scheduleAthletes,
            'rows' => $rows,
            'calendar' => $calendar,
            'selectedAthlete' => $selectedAthlete ? [
                'id' => $selectedAthlete->id,
                'full_name' => trim($selectedAthlete->last_name_nom . ' ' . $selectedAthlete->first_name_nom . ' ' . ($selectedAthlete->middle_name_nom ?? '')),
            ] : null,
            'stats' => $stats,
            'filters' => ['search' => $search, 'athlete_id' => $athleteId, 'schedule_id' => $scheduleId],
        ]);
    }
}
