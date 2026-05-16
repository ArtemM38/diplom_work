<?php

namespace App\Http\Controllers;

use App\Models\PortfolioAchievement;
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

        $achievements = PortfolioAchievement::query()
            ->with(['eventType', 'eventLevel', 'eventHost', 'resultRank'])
            ->where('athlete_id', $athlete->id)
            ->orderByDesc('event_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'event_name' => $a->event_name,
                'event_date' => $a->event_date,
                'event_city' => $a->event_city,
                'result_place' => $a->result_place,
                'event_type' => $a->eventType?->name,
                'event_level' => $a->eventLevel?->name,
                'event_host' => $a->eventHost?->name,
                'result_rank' => $a->resultRank?->name,
                'evidence_file_path' => $a->evidence_file_path,
            ]);

        return Inertia::render('Athlete/Portfolio', [
            'athleteName' => trim("{$athlete->last_name_nom} {$athlete->first_name_nom} {$athlete->middle_name_nom}"),
            'achievements' => $achievements,
            'stats' => [
                'total' => $achievements->count(),
                'places_1' => $achievements->where('result_place', 1)->count(),
                'places_2' => $achievements->where('result_place', 2)->count(),
                'places_3' => $achievements->where('result_place', 3)->count(),
            ],
        ]);
    }
}
