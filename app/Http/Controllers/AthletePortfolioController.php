<?php

namespace App\Http\Controllers;

use App\Models\Athlete;
use App\Support\EventAchievementMapper;
use App\Support\GuardianChildAccess;
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

        return $this->renderPortfolio($request, $athlete, [
            'pageTitle' => 'Моё портфолио',
            'headerTitle' => 'Моё портфолио',
        ]);
    }

    public function guardianIndex(Request $request)
    {
        $user = $request->user();
        abort_unless($user?->hasRole('guardian'), 403);

        $children = GuardianChildAccess::childrenForGuardian($user);
        abort_if($children->isEmpty(), 404);

        $athleteId = GuardianChildAccess::resolveChildId($user, $request->integer('athlete_id') ?: null);
        $athlete = Athlete::findOrFail($athleteId);

        return $this->renderPortfolio($request, $athlete, [
            'pageTitle' => 'Результаты ребёнка',
            'headerTitle' => 'Результаты мероприятий',
            'children' => $children,
            'selectedAthleteId' => $athlete->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function renderPortfolio(Request $request, Athlete $athlete, array $meta)
    {
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $resultPlace = $request->input('result_place');

        $achievements = EventAchievementMapper::forAthlete($athlete->id);

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
            'pageTitle' => $meta['pageTitle'] ?? 'Портфолио',
            'headerTitle' => $meta['headerTitle'] ?? 'Портфолио',
            'children' => $meta['children'] ?? null,
            'selectedAthleteId' => $meta['selectedAthleteId'] ?? null,
            'portfolioRoute' => isset($meta['children']) ? 'guardian.portfolio' : 'athlete.portfolio',
        ]);
    }
}
