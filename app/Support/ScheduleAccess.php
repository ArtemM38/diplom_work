<?php

namespace App\Support;

use App\Models\Schedule;
use Carbon\Carbon;

class ScheduleAccess
{
    public static function lessonStart(Schedule $schedule): Carbon
    {
        return DateFormatter::toDateTime($schedule->lesson_date, $schedule->start_time);
    }

    public static function isCancelled(Schedule $schedule): bool
    {
        return $schedule->cancelled_at !== null;
    }

    /** Создание/перенос — только на будущее время. */
    public static function isInPast(string $lessonDate, string $startTime): bool
    {
        return now()->gt(DateFormatter::toDateTime($lessonDate, $startTime));
    }

    /** Отмена разрешена для любой неотменённой тренировки. */
    public static function canCancel(Schedule $schedule): bool
    {
        if (self::isCancelled($schedule)) {
            return false;
        }

        return $schedule->lesson_date && $schedule->start_time;
    }

    /** При отмене менее чем за 5 часов до начала нужна причина. */
    public static function cancellationReasonRequired(Schedule $schedule): bool
    {
        if (! $schedule->lesson_date || ! $schedule->start_time) {
            return false;
        }

        return now()->gt(self::lessonStart($schedule)->subHours(5));
    }

    /** @deprecated use canCancel */
    public static function canDelete(Schedule $schedule): bool
    {
        return self::canCancel($schedule);
    }

    /** Отметки в журнале — за 10 минут до начала и позже. */
    public static function canMarkAttendance(Schedule $schedule): bool
    {
        if (self::isCancelled($schedule) || ! $schedule->lesson_date || ! $schedule->start_time) {
            return false;
        }

        return now()->gte(self::lessonStart($schedule)->subMinutes(10));
    }
}
