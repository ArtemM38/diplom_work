<?php

namespace App\Support;

use App\Models\Athlete;
use App\Models\AthleteBalanceHistory;
use App\Models\AthleteFinance;
use App\Models\Attendance;
use App\Models\Schedule;
use App\Services\AthleteNotificationService;

class AttendanceBilling
{
    public static function sync(
        Attendance $attendance,
        string $status,
        float $price,
        int $athleteId,
        Schedule $schedule,
        ?int $changedBy
    ): void {
        $netCharged = (float) AthleteBalanceHistory::query()
            ->where('attendance_id', $attendance->id)
            ->sum('change_amount');

        if (in_array($status, ['Я', 'Н'], true)) {
            if ($price <= 0 || $netCharged < 0) {
                return;
            }

            self::charge($attendance, $price, $athleteId, $schedule, $status, $changedBy);
        } elseif ($status === 'У' && $netCharged < 0) {
            self::refund($attendance, abs($netCharged), $athleteId, $schedule, $changedBy);
        }
    }

    private static function charge(
        Attendance $attendance,
        float $price,
        int $athleteId,
        Schedule $schedule,
        string $status,
        ?int $changedBy
    ): void {
        $finance = AthleteFinance::firstOrCreate(['athlete_id' => $athleteId], ['balance' => 0]);
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

        $history = AthleteBalanceHistory::create([
            'athlete_id' => $athleteId,
            'schedule_id' => $schedule->id,
            'attendance_id' => $attendance->id,
            'change_amount' => -$price,
            'balance_before' => $before,
            'balance_after' => $after,
            'reason' => $reasonText,
            'status' => $status,
            'changed_by' => $changedBy,
        ]);

        $athlete = Athlete::find($athleteId);
        if ($athlete) {
            app(AthleteNotificationService::class)->notifyBalanceBecameNegative(
                $athlete,
                $before,
                $after,
                $history->id
            );
        }
    }

    private static function refund(
        Attendance $attendance,
        float $amount,
        int $athleteId,
        Schedule $schedule,
        ?int $changedBy
    ): void {
        $finance = AthleteFinance::firstOrCreate(['athlete_id' => $athleteId], ['balance' => 0]);
        $before = (float) $finance->balance;
        $after = round($before + $amount, 2);
        $finance->update(['balance' => $after]);

        $reasonText = 'Возврат за уважительную неявку';
        if ($schedule->group?->name && $schedule->lesson_date) {
            $reasonText = sprintf(
                'Возврат за уважительную неявку, группа "%s", дата %s',
                $schedule->group->name,
                $schedule->lesson_date
            );
        }

        AthleteBalanceHistory::create([
            'athlete_id' => $athleteId,
            'schedule_id' => $schedule->id,
            'attendance_id' => $attendance->id,
            'change_amount' => $amount,
            'balance_before' => $before,
            'balance_after' => $after,
            'reason' => $reasonText,
            'status' => 'У',
            'changed_by' => $changedBy,
        ]);
    }
}
