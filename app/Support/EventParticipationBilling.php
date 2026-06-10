<?php

namespace App\Support;

use App\Models\Athlete;
use App\Models\AthleteBalanceHistory;
use App\Models\AthleteFinance;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Services\AthleteNotificationService;

class EventParticipationBilling
{
    public static function sync(
        EventParticipant $participant,
        ?string $status,
        ?int $changedBy
    ): void {
        $participant->loadMissing('event');
        $event = $participant->event;

        if (! $event) {
            return;
        }

        $cost = (float) $event->cost;
        $netCharged = (float) AthleteBalanceHistory::query()
            ->where('event_participant_id', $participant->id)
            ->sum('change_amount');

        if (in_array($status, ['Я', 'Н'], true)) {
            if ($cost <= 0 || $netCharged < 0) {
                return;
            }

            self::charge($participant, $event, $cost, $status, $changedBy);
        } elseif ($status === 'У' && $netCharged < 0) {
            self::refund($participant, $event, abs($netCharged), $changedBy);
        }
    }

    private static function charge(
        EventParticipant $participant,
        Event $event,
        float $cost,
        string $status,
        ?int $changedBy
    ): void {
        $athleteId = (int) $participant->athlete_id;
        $finance = AthleteFinance::firstOrCreate(['athlete_id' => $athleteId], ['balance' => 0]);
        $before = (float) $finance->balance;
        $after = round($before - $cost, 2);
        $finance->update(['balance' => $after]);

        $reason = sprintf(
            'Списание за мероприятие "%s", дата %s',
            $event->name,
            DateFormatter::toDisplayDate($event->event_date) ?? (string) $event->event_date
        );

        $history = AthleteBalanceHistory::create([
            'athlete_id' => $athleteId,
            'event_participant_id' => $participant->id,
            'change_amount' => -$cost,
            'balance_before' => $before,
            'balance_after' => $after,
            'reason' => $reason,
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
        EventParticipant $participant,
        Event $event,
        float $amount,
        ?int $changedBy
    ): void {
        $athleteId = (int) $participant->athlete_id;
        $finance = AthleteFinance::firstOrCreate(['athlete_id' => $athleteId], ['balance' => 0]);
        $before = (float) $finance->balance;
        $after = round($before + $amount, 2);
        $finance->update(['balance' => $after]);

        $reason = sprintf(
            'Возврат за уважительную неявку на мероприятие "%s", дата %s',
            $event->name,
            DateFormatter::toDisplayDate($event->event_date) ?? (string) $event->event_date
        );

        AthleteBalanceHistory::create([
            'athlete_id' => $athleteId,
            'event_participant_id' => $participant->id,
            'change_amount' => $amount,
            'balance_before' => $before,
            'balance_after' => $after,
            'reason' => $reason,
            'status' => 'У',
            'changed_by' => $changedBy,
        ]);
    }
}
