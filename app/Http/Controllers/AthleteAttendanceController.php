<?php

namespace App\Http\Controllers;

use App\Support\AthleteAttendanceJournal;
use App\Support\GuardianChildAccess;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AthleteAttendanceController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user?->hasRole('athlete'), 403);

        $athlete = $user->athlete;
        abort_unless($athlete, 404);

        $calendarMonth = $request->input('calendar_month', now()->format('Y-m'));
        $statsPeriod = $request->input('stats_period', 'month');

        $journal = AthleteAttendanceJournal::build($athlete->id, $calendarMonth, $statsPeriod);

        return Inertia::render('Guardian/ChildAttendance', [
            'isGuardian' => false,
            'children' => [],
            'selectedAthlete' => [
                'id' => $athlete->id,
                'full_name' => GuardianChildAccess::fullName($athlete),
            ],
            'calendar' => $journal['calendar'],
            'stats' => $journal['stats'],
            'filters' => [
                'athlete_id' => $athlete->id,
                'calendar_month' => $calendarMonth,
                'stats_period' => $statsPeriod,
            ],
        ]);
    }
}
