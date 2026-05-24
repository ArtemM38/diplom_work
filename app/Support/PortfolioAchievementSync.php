<?php

namespace App\Support;

use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\PortfolioAchievement;

class PortfolioAchievementSync
{
    public static function fromParticipant(EventParticipant $participant): void
    {
        $participant->loadMissing(['event.eventType', 'event.eventLevel', 'event.eventHost', 'athlete']);
        $event = $participant->event;

        if (! $event || ! $participant->athlete_id) {
            return;
        }

        PortfolioAchievement::updateOrCreate(
            [
                'event_id' => $event->id,
                'athlete_id' => $participant->athlete_id,
            ],
            [
                'event_name' => $event->name,
                'event_type_id' => $event->event_type_id,
                'event_place' => $event->event_place,
                'event_date' => $event->event_date,
                'event_period' => $event->event_period,
                'event_level_id' => $event->event_level_id,
                'event_host_id' => $event->event_host_id,
                'result_label' => $participant->result_label,
                'result_place' => $participant->result_place,
                'result_rank_id' => $participant->result_rank_id,
                'certificate_id' => $participant->certificate_id,
                'result_description' => $participant->result_description,
                'evidence_file_path' => $participant->evidence_file_path,
            ]
        );
    }

    public static function syncEventMetadata(Event $event): void
    {
        $event->loadMissing('participants');

        foreach ($event->participants as $participant) {
            self::fromParticipant($participant);
        }
    }

    public static function removeForParticipant(EventParticipant $participant): void
    {
        PortfolioAchievement::query()
            ->where('event_id', $participant->event_id)
            ->where('athlete_id', $participant->athlete_id)
            ->delete();
    }
}
