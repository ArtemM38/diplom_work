<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Athlete;
use App\Models\Schedule;
use App\Models\Attendance;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AttendanceController extends Controller
{
    // Открыть страницу отметки для конкретной тренировки
    public function show(Schedule $schedule)
    {
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
        // Принимаем массив вида { athlete_id: 'Я', athlete_id: 'Н' }
        foreach ($request->attendance as $athleteId => $status) {
            Attendance::updateOrCreate(
                ['schedule_id' => $schedule->id, 'athlete_id' => $athleteId],
                ['status' => $status]
            );
        }

        return redirect()->route('admin.schedule')->with('success', 'Посещаемость сохранена');
    }

    public function journal(Request $request)
    {
        $search = $request->input('search');
        $athleteId = $request->integer('athlete_id');

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
                'excused' => $rows->where('status', 'УН')->count(),
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
            'rows' => $rows,
            'calendar' => $calendar,
            'selectedAthlete' => $selectedAthlete ? [
                'id' => $selectedAthlete->id,
                'full_name' => trim($selectedAthlete->last_name_nom . ' ' . $selectedAthlete->first_name_nom . ' ' . ($selectedAthlete->middle_name_nom ?? '')),
            ] : null,
            'stats' => $stats,
            'filters' => ['search' => $search, 'athlete_id' => $athleteId],
        ]);
    }
}
