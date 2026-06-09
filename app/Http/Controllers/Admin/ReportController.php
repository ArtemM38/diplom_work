<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Athlete;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\Group;
use App\Models\Rank;
use App\Support\ProfitReport;
use App\Support\ReportMeta;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $filters = $this->readFilters($request);

        $athletes = $this->athletesForFilters($filters)->map(function (Athlete $athlete) {
            $birthDate = $athlete->birth_date ? Carbon::parse($athlete->birth_date) : null;
            $age = $birthDate ? $birthDate->age : null;

            return [
                'id' => $athlete->id,
                'full_name' => $this->athleteName($athlete),
                'birth_date' => $birthDate?->format('d.m.Y'),
                'age' => $age,
                'current_rank' => $this->currentRankName($athlete),
                'groups' => $athlete->groups->pluck('name')->values(),
            ];
        })->values();

        return Inertia::render('Admin/Reports/Index', [
            'ranks' => Rank::orderBy('name')->get(['id', 'name']),
            'groups' => Group::visible()->orderBy('name')->get(['id', 'name']),
            'events' => Event::query()
                ->orderByDesc('event_date')
                ->orderBy('name')
                ->get(['id', 'name', 'event_date'])
                ->map(fn (Event $e) => [
                    'id' => $e->id,
                    'name' => $e->name,
                    'event_date' => $e->event_date?->format('d.m.Y'),
                ]),
            'athletes' => $athletes,
            'filters' => $filters,
        ]);
    }

    public function profit(Request $request)
    {
        $filters = $this->readProfitFilters($request);
        $report = ProfitReport::build($filters);
        $options = ProfitReport::filterOptions();

        return Inertia::render('Admin/Reports/Profit', [
            'filters' => $filters,
            'report' => $report,
            'groups' => $options['groups'],
            'coaches' => $options['coaches'],
            'athletes' => $options['athletes'],
        ]);
    }

    public function exportProfit(Request $request)
    {
        $validated = $request->validate([
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
            'group_id' => 'nullable|exists:groups,id',
            'coach_id' => 'nullable|exists:users,id',
            'athlete_id' => 'nullable|exists:athletes,id',
        ]);

        $report = ProfitReport::build($validated);
        $rows = $report['rows'];

        if ($request->string('format')->toString() === 'pdf') {
            $pdf = Pdf::loadView('pdf.profit-report', array_merge([
                'rows' => $rows,
                'filters' => $validated,
                'total_profit' => $report['total_profit'],
                'by_source' => $report['by_source'],
                'operations_count' => $report['operations_count'],
            ], ReportMeta::forExport()))->setPaper('a4', 'landscape');

            return $pdf->download('profit-report-' . now()->format('Ymd-His') . '.pdf');
        }

        return $this->streamCsv(
            ['Дата', 'Спортсмен', 'Сумма (₽)', 'Источник', 'Группа', 'Тренер', 'Основание'],
            $rows,
            fn ($row) => [
                $row['date'],
                $row['athlete_name'],
                (string) $row['amount'],
                $row['source_label'],
                $row['group'] ?? '',
                $row['coach'] ?? '',
                $row['reason'] ?? '',
            ],
            'profit-report'
        );
    }

    public function exportAthletes(Request $request)
    {
        $validated = $this->validatePeriod($request);
        $rows = $this->athleteReportRows($validated);

        if ($request->string('format')->toString() === 'pdf') {
            $pdf = Pdf::loadView('pdf.athletes-report', array_merge([
                'rows' => $rows,
                'filters' => $validated,
            ], ReportMeta::forExport()))->setPaper('a4', 'landscape');

            return $pdf->download('athletes-report-' . now()->format('Ymd-His') . '.pdf');
        }

        return $this->streamCsv(
            [
                'ФИО', 'Дата рождения', 'Текущий разряд', 'Группы', 'Мероприятие', 'Дата мероприятия',
                'Тип', 'Уровень', 'Результат', 'Место', 'Разряд по итогам',
            ],
            $rows,
            fn ($row) => [
                $row['athlete_name'],
                $row['birth_date'],
                $row['current_rank'],
                $row['groups'],
                $row['event_name'],
                $row['event_date'],
                $row['event_type'],
                $row['event_level'],
                $row['result'],
                $row['result_place'],
                $row['result_rank'],
            ],
            'athletes-report'
        );
    }

    public function exportEvents(Request $request)
    {
        $validated = $this->validatePeriod($request);
        $rows = $this->eventsReportRows($validated);

        if ($request->string('format')->toString() === 'pdf') {
            $pdf = Pdf::loadView('pdf.events-period-report', array_merge([
                'rows' => $rows,
                'filters' => $validated,
            ], ReportMeta::forExport()))->setPaper('a4', 'landscape');

            return $pdf->download('events-report-' . now()->format('Ymd-His') . '.pdf');
        }

        return $this->streamCsv(
            [
                'Мероприятие', 'Дата', 'Тип', 'Уровень', 'Место', 'Ведущий', 'Участников',
                'Спортсмен', 'Результат', 'Место', 'Разряд',
            ],
            $rows,
            fn ($row) => [
                $row['event_name'],
                $row['event_date'],
                $row['event_type'],
                $row['event_level'],
                $row['event_place'],
                $row['event_host'],
                $row['participants_count'],
                $row['athlete_name'],
                $row['result'],
                $row['result_place'],
                $row['result_rank'],
            ],
            'events-report'
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePeriod(Request $request): array
    {
        return $request->validate([
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
            'fio' => 'nullable|string|max:255',
            'rank_id' => 'nullable|exists:ranks,id',
            'group_id' => 'nullable|exists:groups,id',
            'event_id' => 'nullable|exists:events,id',
            'age_from' => 'nullable|integer|min:0|max:100',
            'age_to' => 'nullable|integer|min:0|max:100',
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, string|null>>
     */
    private function athleteReportRows(array $filters): array
    {
        $athletes = $this->athletesForFilters($filters);

        $athleteIds = $athletes->pluck('id');
        $participants = EventParticipant::query()
            ->with(['event.eventType', 'event.eventLevel', 'resultRank', 'athlete'])
            ->whereIn('athlete_id', $athleteIds)
            ->whereHas('event', fn ($q) => $q->whereBetween('event_date', [$filters['date_from'], $filters['date_to']]))
            ->get()
            ->groupBy('athlete_id');

        $rows = [];

        foreach ($athletes as $athlete) {
            $base = [
                'athlete_name' => $this->athleteName($athlete),
                'birth_date' => $athlete->birth_date
                    ? (\Illuminate\Support\Carbon::parse($athlete->birth_date)->format('d.m.Y'))
                    : '',
                'current_rank' => $this->currentRankName($athlete),
                'groups' => $athlete->groups->pluck('name')->join(', '),
            ];

            $items = $participants->get($athlete->id, collect());

            if ($items->isEmpty()) {
                $rows[] = array_merge($base, [
                    'event_name' => '',
                    'event_date' => '',
                    'event_type' => '',
                    'event_level' => '',
                    'result' => '',
                    'result_place' => '',
                    'result_rank' => '',
                ]);
                continue;
            }

            foreach ($items as $item) {
                $event = $item->event;
                $rows[] = array_merge($base, [
                    'event_name' => $event?->name,
                    'event_date' => $event?->event_date?->format('d.m.Y') ?? (string) $event?->event_date,
                    'event_type' => $event?->eventType?->name,
                    'event_level' => $event?->eventLevel?->name,
                    'result' => $item->result_label,
                    'result_place' => $item->result_place,
                    'result_rank' => $item->resultRank?->name,
                ]);
            }
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function readProfitFilters(Request $request): array
    {
        return [
            'date_from' => $request->string('date_from')->toString() ?: now()->startOfMonth()->toDateString(),
            'date_to' => $request->string('date_to')->toString() ?: now()->toDateString(),
            'group_id' => $request->input('group_id'),
            'coach_id' => $request->input('coach_id'),
            'athlete_id' => $request->input('athlete_id'),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function readFilters(Request $request): array
    {
        return [
            'date_from' => $request->string('date_from')->toString() ?: now()->startOfYear()->toDateString(),
            'date_to' => $request->string('date_to')->toString() ?: now()->toDateString(),
            'fio' => $request->string('fio')->toString(),
            'rank_id' => $request->input('rank_id'),
            'group_id' => $request->input('group_id'),
            'event_id' => $request->input('event_id'),
            'age_from' => $request->input('age_from'),
            'age_to' => $request->input('age_to'),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return \Illuminate\Support\Collection<int, Athlete>
     */
    private function athletesForFilters(array $filters)
    {
        $fio = $filters['fio'] ?? null;
        $groupId = $filters['group_id'] ?? null;
        $rankId = $filters['rank_id'] ?? null;
        $eventId = $filters['event_id'] ?? null;
        $ageFrom = $filters['age_from'] ?? null;
        $ageTo = $filters['age_to'] ?? null;

        $athletes = Athlete::query()
            ->with(['groups', 'rankHistories.rank'])
            ->when($groupId, fn ($q, $gid) => $q->whereHas('groups', fn ($g) => $g->where('groups.id', $gid)))
            ->when($eventId, fn ($q, $eid) => $q->whereIn('id', EventParticipant::query()->where('event_id', $eid)->select('athlete_id')))
            ->when($fio, function ($q, $fioValue) {
                $parts = preg_split('/\s+/u', trim((string) $fioValue)) ?: [];
                foreach ($parts as $part) {
                    $q->where(function ($qq) use ($part) {
                        $qq->where('last_name_nom', 'like', '%' . $part . '%')
                            ->orWhere('first_name_nom', 'like', '%' . $part . '%')
                            ->orWhere('middle_name_nom', 'like', '%' . $part . '%');
                    });
                }
            })
            ->when($ageFrom !== null && $ageFrom !== '', fn ($q) => $q->whereRaw('TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) >= ?', [(int) $ageFrom]))
            ->when($ageTo !== null && $ageTo !== '', fn ($q) => $q->whereRaw('TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) <= ?', [(int) $ageTo]))
            ->orderBy('last_name_nom')
            ->orderBy('first_name_nom')
            ->orderBy('middle_name_nom')
            ->get();

        if (! empty($rankId)) {
            $athletes = $athletes->filter(fn (Athlete $a) => $this->currentRankId($a) === (int) $rankId)->values();
        }

        return $athletes;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, string|int|null>>
     */
    private function eventsReportRows(array $filters): array
    {
        $events = Event::query()
            ->with(['eventType', 'eventLevel', 'eventHost'])
            ->withCount('participants')
            ->whereBetween('event_date', [$filters['date_from'], $filters['date_to']])
            ->orderBy('event_date')
            ->orderBy('name')
            ->get();

        $rows = [];

        foreach ($events as $event) {
            $participants = $event->participants()->with(['athlete', 'resultRank'])->get();
            $base = [
                'event_name' => $event->name,
                'event_date' => $event->event_date?->format('d.m.Y') ?? (string) $event->event_date,
                'event_type' => $event->eventType?->name,
                'event_level' => $event->eventLevel?->name,
                'event_place' => $event->event_place,
                'event_host' => $event->eventHost?->full_name,
                'participants_count' => $event->participants_count,
            ];

            if ($participants->isEmpty()) {
                $rows[] = array_merge($base, [
                    'athlete_name' => '',
                    'result' => '',
                    'result_place' => '',
                    'result_rank' => '',
                ]);
                continue;
            }

            foreach ($participants as $item) {
                $athlete = $item->athlete;
                $rows[] = array_merge($base, [
                    'athlete_name' => $athlete ? $this->athleteName($athlete) : '',
                    'result' => $item->result_label,
                    'result_place' => $item->result_place,
                    'result_rank' => $item->resultRank?->name,
                ]);
            }
        }

        return $rows;
    }

    private function athleteName(Athlete $athlete): string
    {
        return trim("{$athlete->last_name_nom} {$athlete->first_name_nom} {$athlete->middle_name_nom}");
    }

    private function currentRankName(Athlete $athlete): ?string
    {
        $latest = $athlete->rankHistories->sortByDesc('assigned_at')->first();

        return $latest?->rank?->name;
    }

    private function currentRankId(Athlete $athlete): ?int
    {
        $latest = $athlete->rankHistories->sortByDesc('assigned_at')->first();

        return $latest?->rank_id;
    }

    /**
     * @param  list<string>  $header
     * @param  list<array<string, mixed>>  $rows
     * @param  callable(array<string, mixed>): array<int, string|null>  $mapRow
     */
    private function streamCsv(array $header, array $rows, callable $mapRow, string $basename): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $rows, $mapRow) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Дата формирования', ReportMeta::generatedAtFormatted()], ';');
            fputcsv($out, ['Сформировал', ReportMeta::generatedByName()], ';');
            fputcsv($out, [], ';');
            fputcsv($out, $header, ';');

            foreach ($rows as $row) {
                fputcsv($out, $mapRow($row), ';');
            }

            fclose($out);
        }, $basename . '-' . now()->format('Ymd-His') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
