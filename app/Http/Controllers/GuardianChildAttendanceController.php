<?php

namespace App\Http\Controllers;

use App\Models\Athlete;
use App\Support\AthleteAttendanceJournal;
use App\Support\GuardianChildAccess;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GuardianChildAttendanceController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user?->hasRole('guardian'), 403);

        $children = GuardianChildAccess::childrenForGuardian($user);
        abort_if($children->isEmpty(), 404);

        $athleteId = GuardianChildAccess::resolveChildId($user, $request->integer('athlete_id') ?: null);
        $calendarMonth = $request->input('calendar_month', now()->format('Y-m'));
        $statsPeriod = $request->input('stats_period', 'month');

        $athlete = Athlete::findOrFail($athleteId);
        $selectedAthlete = [
            'id' => $athlete->id,
            'full_name' => GuardianChildAccess::fullName($athlete),
        ];

        $journal = AthleteAttendanceJournal::build($athleteId, $calendarMonth, $statsPeriod);

        return Inertia::render('Guardian/ChildAttendance', [
            'isGuardian' => true,
            'children' => $children->values(),
            'selectedAthlete' => $selectedAthlete,
            'calendar' => $journal['calendar'],
            'stats' => $journal['stats'],
            'filters' => [
                'athlete_id' => $athleteId,
                'calendar_month' => $calendarMonth,
                'stats_period' => $statsPeriod,
            ],
        ]);
    }
}
