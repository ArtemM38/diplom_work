<?php

namespace App\Http\Controllers;

use App\Models\EventParticipant;
use App\Models\PortfolioAchievement;
use App\Support\DateFormatter;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AthletePortfolioController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless($user?->hasRole('athlete'), 403);

        $athlete = $user->athlete;
        abort_unless($athlete, 404);

        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $resultPlace = $request->input('result_place');

        $fromEvents = EventParticipant::query()
            ->with(['event.eventType', 'event.eventLevel', 'event.eventHost', 'resultRank'])
            ->where('athlete_id', $athlete->id)
            ->get()
            ->sortByDesc(fn (EventParticipant $p) => $p->event?->event_date ?? '')
            ->values();

        $legacy = PortfolioAchievement::query()
            ->with(['eventType', 'eventLevel', 'eventHost', 'resultRank'])
            ->where('athlete_id', $athlete->id)
            ->whereNull('event_id')
            ->orderByDesc('event_date')
            ->get();

        $achievements = $fromEvents->map(fn (EventParticipant $p) => [
            'id' => 'ep-' . $p->id,
            'event_name' => $p->event?->name,
            'event_date' => DateFormatter::toDateString($p->event?->event_date),
            'event_date_display' => DateFormatter::toDisplayDate($p->event?->event_date),
            'event_place' => $p->event?->event_place,
            'result_place' => $p->result_place,
            'event_type' => $p->event?->eventType?->name,
            'event_level' => $p->event?->eventLevel?->name,
            'event_host' => $p->event?->eventHost?->full_name,
            'result_rank' => $p->resultRank?->name,
            'result_label' => $p->result_label,
            'evidence_file_path' => $p->evidence_file_path,
        ])->concat($legacy->map(fn ($a) => [
            'id' => 'pa-' . $a->id,
            'event_name' => $a->event_name,
            'event_date' => DateFormatter::toDateString($a->event_date),
            'event_date_display' => DateFormatter::toDisplayDate($a->event_date),
            'event_place' => $a->event_place,
            'result_place' => $a->result_place,
            'event_type' => $a->eventType?->name,
            'event_level' => $a->eventLevel?->name,
            'event_host' => $a->eventHost?->full_name,
            'result_rank' => $a->resultRank?->name,
            'result_label' => $a->result_label,
            'evidence_file_path' => $a->evidence_file_path,
        ]))->sortByDesc('event_date')->values();

        if ($dateFrom) {
            $achievements = $achievements->filter(fn ($a) => $a['event_date'] && $a['event_date'] >= $dateFrom);
        }
        if ($dateTo) {
            $achievements = $achievements->filter(fn ($a) => $a['event_date'] && $a['event_date'] <= $dateTo);
        }
        if ($resultPlace !== null && $resultPlace !== '') {
            $achievements = $achievements->filter(fn ($a) => (int) $a['result_place'] === (int) $resultPlace);
        }

        $achievements = $achievements->values();

        return Inertia::render('Athlete/Portfolio', [
            'athleteName' => trim("{$athlete->last_name_nom} {$athlete->first_name_nom} {$athlete->middle_name_nom}"),
            'achievements' => $achievements,
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'result_place' => $resultPlace,
            ],
            'stats' => [
                'total' => $achievements->count(),
                'places_1' => $achievements->where('result_place', 1)->count(),
                'places_2' => $achievements->where('result_place', 2)->count(),
                'places_3' => $achievements->where('result_place', 3)->count(),
            ],
        ]);
    }
}
