<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Athlete;
use App\Models\Attendance;
use App\Models\AthleteFinance;
use App\Models\Group;
use App\Models\Schedule;
use App\Support\AdminPermissions;
use App\Support\AttendanceBilling;
use App\Support\AthletePricing;
use App\Support\DateFormatter;
use App\Support\ScheduleAccess;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AttendanceController extends Controller
{
    private function coachIdForUser(?\App\Models\User $user): ?int
    {
        return AdminPermissions::isCoachOnly($user) ? $user?->id : null;
    }

    private function ensureCoachCanAccessSchedule(?\App\Models\User $user, Schedule $schedule): void
    {
        $coachId = $this->coachIdForUser($user);
        if ($coachId && (int) $schedule->coach_id !== $coachId) {
            abort(403, 'Доступ только к тренировкам, где вы назначены тренером.');
        }
    }

    public function show(Request $request, Schedule $schedule)
    {
        $this->ensureCoachCanAccessSchedule($request->user(), $schedule);

        if (! ScheduleAccess::canMarkAttendance($schedule)) {
            return redirect()->route('admin.schedule')->with('error', 'Нельзя ставить отметки для отменённой тренировки.');
        }

        $schedule->load(['group.athletes', 'attendances']);

        $existingCertificates = $schedule->attendances->pluck('excused_certificate', 'athlete_id');

        $schedule->load('coach', 'initialCoach');

        return Inertia::render('Admin/Attendance/Mark', [
            'schedule' => array_merge($schedule->toArray(), [
                'lesson_date' => DateFormatter::toDisplayDate($schedule->lesson_date) ?? DateFormatter::toDateString($schedule->lesson_date),
                'coach' => $schedule->coach,
                'initial_coach' => $schedule->initialCoach,
                'group' => $schedule->group,
            ]),
            'athletes' => $schedule->group->athletes,
            'existingAttendances' => $schedule->attendances->pluck('status', 'athlete_id'),
            'existingCertificates' => $existingCertificates,
        ]);
    }

    public function store(Request $request, Schedule $schedule)
    {
        abort_if($request->user()?->hasRole('accountant') && ! $request->user()?->hasAnyRole(['admin', 'coach']), 403);
        $this->ensureCoachCanAccessSchedule($request->user(), $schedule);

        if (! ScheduleAccess::canMarkAttendance($schedule)) {
            return redirect()->route('admin.schedule')->with('error', 'Нельзя сохранять отметки для отменённой тренировки.');
        }

        $validated = $request->validate(
            [
                'attendance' => 'required|array',
                'attendance.*' => 'required|in:Я,Н,У',
                'certificates' => 'nullable|array',
                'certificates.*' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            ],
            [
                'attendance.*.in' => 'Недопустимый статус посещаемости.',
                'certificates.*.mimes' => 'Справка: PDF или изображение (JPG, PNG).',
                'certificates.*.max' => 'Размер справки не более 5 МБ.',
            ],
            [
                'attendance' => 'посещаемость',
                'certificates' => 'справки',
            ],
        );

        foreach ($validated['attendance'] as $athleteId => $status) {
            if ($status === 'У') {
                $hasNew = $request->hasFile("certificates.{$athleteId}");
                $existing = Attendance::query()
                    ->where('schedule_id', $schedule->id)
                    ->where('athlete_id', $athleteId)
                    ->value('excused_certificate');

                if (! $hasNew && ! $existing) {
                    return back()->withErrors([
                        "certificates.{$athleteId}" => 'Для уважительного пропуска (У) приложите справку.',
                    ])->withInput();
                }
            }
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

        DB::transaction(function () use ($request, $schedule, $priceByAthlete, $validated) {
            foreach ($validated['attendance'] as $athleteId => $status) {
                $data = ['status' => $status];

                if ($request->hasFile("certificates.{$athleteId}")) {
                    $data['excused_certificate'] = $request->file("certificates.{$athleteId}")
                        ->store('attendance-certificates', 'public');
                } elseif ($status !== 'У') {
                    $data['excused_certificate'] = null;
                }

                $attendance = Attendance::updateOrCreate(
                    ['schedule_id' => $schedule->id, 'athlete_id' => $athleteId],
                    $data
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
        $coachId = $this->coachIdForUser($request->user());
        $viewMode = $request->input('view', 'athletes');
        $search = $request->input('search');
        $athleteId = $request->integer('athlete_id');
        $groupId = $request->integer('group_id');
        $scheduleId = $request->integer('schedule_id');
        $calendarMonth = $request->input('calendar_month', now()->format('Y-m'));
        $statsPeriod = $request->input('stats_period', 'month');

        $athletes = Athlete::query()
            ->when($coachId, fn ($q) => $q->whereHas('groups.schedules', fn ($sq) => $sq->where('coach_id', $coachId)))
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

        $groups = Group::visible()
            ->where('status', 'active')
            ->when($coachId, fn ($q) => $q->whereHas('schedules', fn ($sq) => $sq->where('coach_id', $coachId)))
            ->orderBy('name')
            ->get(['id', 'name']);

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
                    ->whereHas('schedule', function ($q) use ($periodStart, $periodEnd, $coachId) {
                        $q->whereBetween('lesson_date', [$periodStart->toDateString(), $periodEnd->toDateString()]);
                        if ($coachId) {
                            $q->where('coach_id', $coachId);
                        }
                    })
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
                    'lesson_date' => DateFormatter::toDisplayDate($a->schedule?->lesson_date),
                    'status' => $a->status,
                ])->sortByDesc('lesson_date')->values();

                [$calYear, $calMonth] = array_pad(explode('-', $calendarMonth), 2, now()->format('Y-m'));
                $calStart = Carbon::create((int) $calYear, (int) $calMonth, 1)->startOfDay();
                $calEnd = $calStart->copy()->endOfMonth();

                $groupIds = $athlete->groups()->pluck('groups.id');

                $schedulesInMonth = Schedule::query()
                    ->with(['group'])
                    ->whereIn('group_id', $groupIds)
                    ->whereNull('cancelled_at')
                    ->whereBetween('lesson_date', [$calStart->toDateString(), $calEnd->toDateString()])
                    ->when($coachId, fn ($q) => $q->where('coach_id', $coachId))
                    ->orderBy('lesson_date')
                    ->orderBy('start_time')
                    ->get();

                $attendanceBySchedule = Attendance::query()
                    ->where('athlete_id', $athleteId)
                    ->whereIn('schedule_id', $schedulesInMonth->pluck('id'))
                    ->pluck('status', 'schedule_id');

                $calendar = $schedulesInMonth
                    ->groupBy(fn (Schedule $s) => DateFormatter::toDateString($s->lesson_date))
                    ->map(function ($dayItems, $date) use ($attendanceBySchedule) {
                        return [
                            'date' => $date,
                            'entries' => $dayItems->map(fn (Schedule $schedule) => [
                                'schedule_id' => $schedule->id,
                                'group' => $schedule->group?->name,
                                'start_time' => $schedule->start_time,
                                'end_time' => $schedule->end_time,
                                'status' => $attendanceBySchedule->get($schedule->id),
                            ])->values(),
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
                ->when($coachId, fn ($q) => $q->where('coach_id', $coachId))
                ->whereBetween('lesson_date', [$calStart->toDateString(), $calEnd->toDateString()])
                ->orderBy('lesson_date')
                ->orderBy('start_time')
                ->get();

            $groupCalendar = $groupSchedules
                ->groupBy(fn (Schedule $s) => DateFormatter::toDateString($s->lesson_date))
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
                if ($schedule && (int) $schedule->group_id === $groupId && (! $coachId || (int) $schedule->coach_id === $coachId)) {
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
                        'lesson_date' => DateFormatter::toDisplayDate($schedule->lesson_date) ?? DateFormatter::toDateString($schedule->lesson_date),
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
