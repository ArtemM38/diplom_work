<?php

namespace App\Support;

use App\Models\Attendance;
use Carbon\Carbon;

class AthleteAttendanceJournal
{
    /**
     * @return array{stats: array{present: int, absent: int, excused: int, period: string}, calendar: \Illuminate\Support\Collection}
     */
    public static function build(int $athleteId, string $calendarMonth, string $statsPeriod): array
    {
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

        return [
            'stats' => $stats,
            'calendar' => $calendar,
        ];
    }
}
