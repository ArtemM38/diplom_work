<?php

namespace App\Support;

use App\Models\AthleteRankHistory;
use App\Models\EventParticipant;

class AthleteRankSync
{
    public static function fromParticipant(EventParticipant $participant): void
    {
        $participant->loadMissing(['event', 'athlete']);

        if (! $participant->athlete_id) {
            return;
        }

        if (! $participant->result_rank_id) {
            self::removeForParticipant($participant);

            return;
        }

        $assignedAt = $participant->event?->event_date
            ?? $participant->results_filled_at?->toDateString()
            ?? now()->toDateString();

        AthleteRankHistory::updateOrCreate(
            ['event_participant_id' => $participant->id],
            [
                'athlete_id' => $participant->athlete_id,
                'rank_id' => $participant->result_rank_id,
                'assigned_at' => $assignedAt,
            ]
        );
    }

    public static function removeForParticipant(EventParticipant $participant): void
    {
        AthleteRankHistory::query()
            ->where('event_participant_id', $participant->id)
            ->delete();
    }
}
