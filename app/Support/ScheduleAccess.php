<?php

namespace App\Support;

use App\Models\Schedule;
use Carbon\Carbon;

class ScheduleAccess
{
    public static function lessonStart(Schedule $schedule): Carbon
    {
        return Carbon::parse($schedule->lesson_date . ' ' . $schedule->start_time);
    }

    /** Удаление разрешено не позднее чем за 10 минут до начала. */
    public static function canDelete(Schedule $schedule): bool
    {
        if (! $schedule->lesson_date || ! $schedule->start_time) {
            return false;
        }

        return now()->lte(self::lessonStart($schedule)->subMinutes(10));
    }

    /** Отметки в журнале — за 10 минут до начала и позже. */
    public static function canMarkAttendance(Schedule $schedule): bool
    {
        if (! $schedule->lesson_date || ! $schedule->start_time) {
            return false;
        }

        return now()->gte(self::lessonStart($schedule)->subMinutes(10));
    }
}
