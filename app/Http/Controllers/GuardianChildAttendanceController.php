<?php

namespace App\Http\Controllers;

use App\Models\Athlete;
use App\Models\Attendance;
use App\Support\GuardianChildAccess;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GuardianChildAttendanceController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user?->hasRole('guardian'), 403);

        $children = GuardianChildAccess::childrenForGuardian($user);
        abort_if($children->isEmpty(), 404);

        $athleteId = GuardianChildAccess::resolveChildId($user, $request->integer('athlete_id') ?: null);
        $calendarMonth = $request->input('calendar_month', now()->format('Y-m'));
        $statsPeriod = $request->input('stats_period', 'month');

        $athlete = Athlete::findOrFail($athleteId);
        $selectedAthlete = [
            'id' => $athlete->id,
            'full_name' => GuardianChildAccess::fullName($athlete),
        ];

        [$calYear, $calMonth] = array_pad(explode('-', $calendarMonth), 2, now()->format('m'));
        if ($statsPeriod === 'year') {
            $periodStart = Carbon::create(now()->year, 1, 1)->startOfDay();
            $periodEnd = Carbon::create(now()->year, 12, 31)->endOfDay();
        } else {
            $periodStart = Carbon::create(now()->year, (int) $calMonth, 1)->startOfDay();
            $periodEnd = $periodStart->copy()->endOfMonth();
        }

        $periodAttendances = Attendance::with(['schedule.group'])
            ->where('athlete_id', $athleteId)
            ->whereHas('schedule', fn ($q) => $q->whereBetween('lesson_date', [$periodStart->toDateString(), $periodEnd->toDateString()]))
            ->get();

        $stats = [
            'present' => $periodAttendances->where('status', 'Я')->count(),
            'absent' => $periodAttendances->where('status', 'Н')->count(),
            'excused' => $periodAttendances->where('status', 'У')->count(),
            'period' => $statsPeriod,
        ];

        $calStart = Carbon::create((int) $calYear, (int) $calMonth, 1)->startOfDay();
        $calEnd = $calStart->copy()->endOfMonth();

        $calendarAttendances = Attendance::with(['schedule.group'])
            ->where('athlete_id', $athleteId)
            ->whereHas('schedule', fn ($q) => $q->whereBetween('lesson_date', [$calStart->toDateString(), $calEnd->toDateString()]))
            ->get();

        $calendar = $calendarAttendances
            ->groupBy(fn (Attendance $a) => $a->schedule?->lesson_date)
            ->map(function ($dayItems, $date) {
                return [
                    'date' => $date,
                    'entries' => $dayItems->map(function (Attendance $attendance) {
                        $schedule = $attendance->schedule;

                        return [
                            'schedule_id' => $schedule?->id,
                            'group' => $schedule?->group?->name,
                            'start_time' => $schedule?->start_time,
                            'end_time' => $schedule?->end_time,
                            'status' => $attendance->status,
                        ];
                    })->values(),
                ];
            })
            ->values();

        return Inertia::render('Guardian/ChildAttendance', [
            'children' => $children->values(),
            'selectedAthlete' => $selectedAthlete,
            'calendar' => $calendar,
            'stats' => $stats,
            'filters' => [
                'athlete_id' => $athleteId,
                'calendar_month' => $calendarMonth,
                'stats_period' => $statsPeriod,
            ],
        ]);
    }
}
