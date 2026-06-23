<?php

namespace App\Support;

use App\Models\EventParticipant;
use App\Models\PortfolioAchievement;
use Illuminate\Support\Collection;

class EventAchievementMapper
{
    /**
     * @return array<string, mixed>
     */
    public static function fromParticipant(EventParticipant $participant): array
    {
        $participant->loadMissing([
            'event.eventType',
            'event.eventLevel',
            'event.eventHost',
            'resultRank',
            'evidenceFiles',
        ]);

        return [
            'id' => 'ep-' . $participant->id,
            'participant_id' => $participant->id,
            'event_name' => $participant->event?->name,
            'event_date' => DateFormatter::toDateString($participant->event?->event_date),
            'event_date_display' => DateFormatter::toDisplayDate($participant->event?->event_date),
            'event_place' => $participant->event?->event_place,
            'event_cost' => $participant->event?->cost,
            'result_place' => $participant->result_place,
            'event_type' => $participant->event?->eventType?->name,
            'event_level' => $participant->event?->eventLevel?->name,
            'event_host' => $participant->event?->eventHost?->full_name,
            'result_rank' => $participant->resultRank?->name,
            'result_label' => $participant->result_label,
            'result_description' => $participant->result_description,
            'certificate_id' => $participant->certificate_id,
            'results_filled_at' => $participant->results_filled_at?->toDateTimeString(),
            'evidence_files' => $participant->evidenceFiles->map(fn ($file) => [
                'id' => $file->id,
                'url' => $file->url,
                'original_name' => $file->original_name ?? basename($file->file_path),
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function fromLegacy(PortfolioAchievement $achievement): array
    {
        $achievement->loadMissing(['eventType', 'eventLevel', 'eventHost', 'resultRank']);

        $evidenceFiles = [];
        if ($achievement->evidence_file_path) {
            $evidenceFiles[] = [
                'id' => null,
                'url' => \App\Support\StorageUrl::url($achievement->evidence_file_path),
                'original_name' => basename($achievement->evidence_file_path),
            ];
        }

        return [
            'id' => 'pa-' . $achievement->id,
            'participant_id' => null,
            'event_name' => $achievement->event_name,
            'event_date' => DateFormatter::toDateString($achievement->event_date),
            'event_date_display' => DateFormatter::toDisplayDate($achievement->event_date),
            'event_place' => $achievement->event_place,
            'event_cost' => null,
            'result_place' => $achievement->result_place,
            'event_type' => $achievement->eventType?->name,
            'event_level' => $achievement->eventLevel?->name,
            'event_host' => $achievement->eventHost?->full_name,
            'result_rank' => $achievement->resultRank?->name,
            'result_label' => $achievement->result_label,
            'result_description' => null,
            'certificate_id' => null,
            'results_filled_at' => null,
            'evidence_files' => $evidenceFiles,
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function forAthlete(int $athleteId): Collection
    {
        $fromEvents = EventParticipant::query()
            ->with(['event.eventType', 'event.eventLevel', 'event.eventHost', 'resultRank', 'evidenceFiles'])
            ->where('athlete_id', $athleteId)
            ->get()
            ->filter(fn (EventParticipant $p) => $p->hasResults())
            ->map(fn (EventParticipant $p) => self::fromParticipant($p));

        $legacy = PortfolioAchievement::query()
            ->with(['eventType', 'eventLevel', 'eventHost', 'resultRank'])
            ->where('athlete_id', $athleteId)
            ->get()
            ->map(fn (PortfolioAchievement $a) => self::fromLegacy($a));

        return $fromEvents->concat($legacy)
            ->sortByDesc('event_date')
            ->values();
    }
}
