<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Athlete;
use App\Models\Attendance;
use App\Models\AthleteFinance;
use App\Models\Group;
use App\Models\Schedule;
use App\Support\AttendanceBilling;
use App\Support\AthletePricing;
use App\Support\ScheduleAccess;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AttendanceController extends Controller
{
    public function show(Schedule $schedule)
    {
        if (! ScheduleAccess::canMarkAttendance($schedule)) {
            return redirect()->route('admin.schedule')->with('error', 'Отметку можно ставить не ранее чем за 10 минут до начала тренировки.');
        }

        $schedule->load(['group.athletes', 'attendances']);

        return Inertia::render('Admin/Attendance/Mark', [
            'schedule' => $schedule,
            'athletes' => $schedule->group->athletes,
            'existingAttendances' => $schedule->attendances->pluck('status', 'athlete_id'),
        ]);
    }

    public function store(Request $request, Schedule $schedule)
    {
        abort_if($request->user()?->hasRole('accountant') && ! $request->user()?->hasAnyRole(['admin', 'coach']), 403);

        if (! ScheduleAccess::canMarkAttendance($schedule)) {
            return redirect()->route('admin.schedule')->with('error', 'Отметку можно сохранять не ранее чем за 10 минут до начала тренировки.');
        }

        $schedule->load(['group.athletes.finance']);
        $priceByAthlete = $schedule->group?->athletes
            ?->mapWithKeys(function ($athlete) use ($schedule) {
                $price = (float) ($athlete->pivot->training_price ?? 0);
                if ($price <= 0) {
                    $price = AthletePricing::effectivePrice(
                        (float) ($schedule->group?->tariff_amount ?? 0),
                        $athlete->finance
                    );
                }

                return [$athlete->id => $price];
            }) ?? collect();

        DB::transaction(function () use ($request, $schedule, $priceByAthlete) {
            foreach ($request->attendance as $athleteId => $status) {
                $attendance = Attendance::updateOrCreate(
                    ['schedule_id' => $schedule->id, 'athlete_id' => $athleteId],
                    ['status' => $status]
                );

                $price = (float) ($priceByAthlete->get((int) $athleteId, 0));

                AttendanceBilling::sync(
                    $attendance,
                    $status,
                    $price,
                    (int) $athleteId,
                    $schedule,
                    $request->user()?->id
                );
            }
        });

        return redirect()->route('admin.schedule')->with('success', 'Посещаемость сохранена');
    }

    public function journal(Request $request)
    {
        $viewMode = $request->input('view', 'athletes');
        $search = $request->input('search');
        $athleteId = $request->integer('athlete_id');
        $groupId = $request->integer('group_id');
        $scheduleId = $request->integer('schedule_id');
        $calendarMonth = $request->input('calendar_month', now()->format('Y-m'));
        $statsPeriod = $request->input('stats_period', 'month');

        $athletes = Athlete::query()
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('last_name_nom', 'like', '%' . $search . '%')
                        ->orWhere('first_name_nom', 'like', '%' . $search . '%');
                });
            })
            ->orderBy('last_name_nom')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Athlete $athlete) => [
                'id' => $athlete->id,
                'full_name' => trim("{$athlete->last_name_nom} {$athlete->first_name_nom} " . ($athlete->middle_name_nom ?? '')),
            ]);

        $groups = Group::visible()->where('status', 'active')->orderBy('name')->get(['id', 'name']);

        $selectedAthlete = null;
        $calendar = [];
        $stats = ['present' => 0, 'absent' => 0, 'excused' => 0, 'period' => $statsPeriod];
        $rows = collect();

        if ($viewMode === 'athletes' && $athleteId) {
            $athlete = Athlete::find($athleteId);
            if ($athlete) {
                $selectedAthlete = [
                    'id' => $athlete->id,
                    'full_name' => trim("{$athlete->last_name_nom} {$athlete->first_name_nom} " . ($athlete->middle_name_nom ?? '')),
                ];

                [$calYear, $calMonth] = array_pad(explode('-', $calendarMonth), 2, now()->format('m'));
                if ($statsPeriod === 'year') {
                    $periodStart = Carbon::create(now()->year, 1, 1)->startOfDay();
                    $periodEnd = Carbon::create(now()->year, 12, 31)->endOfDay();
                } else {
                    $periodStart = Carbon::create(now()->year, (int) $calMonth, 1)->startOfDay();
                    $periodEnd = $periodStart->copy()->endOfMonth();
                }

                $periodAttendances = Attendance::with(['schedule.group'])
                    ->where('athlete_id', $athleteId)
                    ->whereHas('schedule', fn ($q) => $q->whereBetween('lesson_date', [$periodStart->toDateString(), $periodEnd->toDateString()]))
                    ->get();

                $stats = [
                    'present' => $periodAttendances->where('status', 'Я')->count(),
                    'absent' => $periodAttendances->where('status', 'Н')->count(),
                    'excused' => $periodAttendances->where('status', 'У')->count(),
                    'period' => $statsPeriod,
                ];

                $rows = $periodAttendances->map(fn (Attendance $a) => [
                    'id' => $a->id,
                    'group' => $a->schedule?->group?->name,
                    'lesson_date' => $a->schedule?->lesson_date,
                    'status' => $a->status,
                ])->sortByDesc('lesson_date')->values();

                [$calYear, $calMonth] = array_pad(explode('-', $calendarMonth), 2, now()->format('Y-m'));
                $calStart = Carbon::create((int) $calYear, (int) $calMonth, 1)->startOfDay();
                $calEnd = $calStart->copy()->endOfMonth();

                $calendarAttendances = Attendance::with(['schedule.group'])
                    ->where('athlete_id', $athleteId)
                    ->whereHas('schedule', fn ($q) => $q->whereBetween('lesson_date', [$calStart->toDateString(), $calEnd->toDateString()]))
                    ->get();

                $calendar = $calendarAttendances
                    ->groupBy(fn (Attendance $a) => $a->schedule?->lesson_date)
                    ->map(function ($dayItems, $date) {
                        return [
                            'date' => $date,
                            'entries' => $dayItems->map(function (Attendance $attendance) {
                                $schedule = $attendance->schedule;

                                return [
                                    'schedule_id' => $schedule?->id,
                                    'group' => $schedule?->group?->name,
                                    'start_time' => $schedule?->start_time,
                                    'end_time' => $schedule?->end_time,
                                    'status' => $attendance->status,
                                ];
                            })->values(),
                        ];
                    })
                    ->values();
            }
        }

        $groupCalendar = [];
        $scheduleModal = null;

        if ($viewMode === 'groups' && $groupId) {
            [$calYear, $calMonth] = array_pad(explode('-', $calendarMonth), 2, now()->format('Y-m'));
            $calStart = Carbon::create((int) $calYear, (int) $calMonth, 1)->startOfDay();
            $calEnd = $calStart->copy()->endOfMonth();

            $groupSchedules = Schedule::query()
                ->where('group_id', $groupId)
                ->whereBetween('lesson_date', [$calStart->toDateString(), $calEnd->toDateString()])
                ->orderBy('lesson_date')
                ->orderBy('start_time')
                ->get();

            $groupCalendar = $groupSchedules
                ->groupBy('lesson_date')
                ->map(fn ($items, $date) => [
                    'date' => $date,
                    'entries' => $items->map(fn (Schedule $s) => [
                        'schedule_id' => $s->id,
                        'start_time' => $s->start_time,
                        'end_time' => $s->end_time,
                        'group' => $s->group?->name,
                    ])->values(),
                ])
                ->values();

            if ($scheduleId) {
                $schedule = Schedule::with(['group', 'attendances'])->find($scheduleId);
                if ($schedule && (int) $schedule->group_id === $groupId) {
                    $attendanceByAthlete = $schedule->attendances->keyBy('athlete_id');
                    $memberIds = $schedule->group?->athletes()->pluck('athletes.id') ?? collect();
                    $historicalIds = $schedule->attendances->pluck('athlete_id');
                    $allIds = $memberIds->merge($historicalIds)->unique()->filter();

                    $modalAthletes = Athlete::whereIn('id', $allIds)->orderBy('last_name_nom')->get()->map(function (Athlete $a) use ($attendanceByAthlete) {
                        return [
                            'id' => $a->id,
                            'full_name' => trim("{$a->last_name_nom} {$a->first_name_nom} " . ($a->middle_name_nom ?? '')),
                            'status' => $attendanceByAthlete->get($a->id)?->status ?? 'Н',
                        ];
                    })->values();

                    $scheduleModal = [
                        'id' => $schedule->id,
                        'lesson_date' => $schedule->lesson_date,
                        'start_time' => $schedule->start_time,
                        'end_time' => $schedule->end_time,
                        'group_name' => $schedule->group?->name,
                        'athletes' => $modalAthletes,
                    ];
                }
            }
        }

        return Inertia::render('Admin/Attendance/Journal', [
            'athletes' => $athletes,
            'groups' => $groups,
            'calendar' => $calendar,
            'groupCalendar' => $groupCalendar,
            'scheduleModal' => $scheduleModal,
            'selectedAthlete' => $selectedAthlete,
            'stats' => $stats,
            'rows' => $rows,
            'filters' => [
                'view' => $viewMode,
                'search' => $search,
                'athlete_id' => $athleteId,
                'group_id' => $groupId,
                'schedule_id' => $scheduleId,
                'calendar_month' => $calendarMonth,
                'stats_period' => $statsPeriod,
            ],
        ]);
    }
}
