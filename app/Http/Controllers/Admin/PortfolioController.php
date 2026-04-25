<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Athlete;
use App\Models\EventHost;
use App\Models\EventLevel;
use App\Models\EventType;
use App\Models\PortfolioAchievement;
use App\Models\Rank;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Barryvdh\DomPDF\Facade\Pdf;

class PortfolioController extends Controller
{
    public function index(Request $request)
    {
        $athleteId = $request->integer('athlete_id');
        $athleteSearch = $request->string('athlete_search')->toString();

        $achievements = collect();
        if ($athleteId) {
            $achievements = PortfolioAchievement::query()
                ->with(['athlete', 'eventType', 'eventLevel', 'eventHost', 'resultRank'])
                ->where('athlete_id', $athleteId)
                ->latest('event_date')
                ->latest()
                ->get();
        }

        $ratings = Athlete::query()
            ->with('achievements.eventLevel')
            ->get()
            ->map(function (Athlete $athlete) {
                $points = $athlete->achievements->sum(function (PortfolioAchievement $achievement) {
                    return $this->calculatePoints($achievement);
                });

                return [
                    'athlete_id' => $athlete->id,
                    'full_name' => trim("{$athlete->last_name_nom} {$athlete->first_name_nom} {$athlete->middle_name_nom}"),
                    'points' => $points,
                    'achievements_count' => $athlete->achievements->count(),
                ];
            })
            ->sortByDesc('points')
            ->values();

        $athleteReport = null;
        if ($athleteId) {
            $athleteReport = [
                'athlete_id' => $athleteId,
                'total_achievements' => $achievements->count(),
                'places_1' => $achievements->where('result_place', 1)->count(),
                'places_2' => $achievements->where('result_place', 2)->count(),
                'places_3' => $achievements->where('result_place', 3)->count(),
            ];
        }

        $summaryReport = [
            'total_achievements' => PortfolioAchievement::count(),
            'unique_athletes' => PortfolioAchievement::distinct('athlete_id')->count('athlete_id'),
            'places_1' => PortfolioAchievement::where('result_place', 1)->count(),
            'places_2' => PortfolioAchievement::where('result_place', 2)->count(),
            'places_3' => PortfolioAchievement::where('result_place', 3)->count(),
        ];

        $athletes = Athlete::select('id', 'last_name_nom', 'first_name_nom', 'middle_name_nom')
            ->when($athleteSearch, function ($query) use ($athleteSearch) {
                $query->where(function ($q) use ($athleteSearch) {
                    $q->where('last_name_nom', 'like', '%' . $athleteSearch . '%')
                        ->orWhere('first_name_nom', 'like', '%' . $athleteSearch . '%');
                });
            })
            ->orderBy('last_name_nom')
            ->get();

        $selectedAthlete = $athleteId ? Athlete::find($athleteId) : null;

        return Inertia::render('Admin/Portfolio/Index', [
            'athletes' => $athletes,
            'eventTypes' => EventType::all(),
            'eventLevels' => EventLevel::all(),
            'eventHosts' => EventHost::all(),
            'ranks' => Rank::all(),
            'achievements' => $achievements,
            'selectedAthlete' => $selectedAthlete,
            'ratings' => $ratings,
            'athleteReport' => $athleteReport,
            'summaryReport' => $summaryReport,
            'filters' => $request->only(['athlete_id', 'athlete_search']),
        ]);
    }

    public function storeHost(Request $request)
    {
        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'rank' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'contacts' => 'nullable|string|max:255',
            'extra_info' => 'nullable|string',
        ]);

        EventHost::create($validated);

        return back()->with('success', 'Ведущий мероприятия добавлен');
    }

    public function updateHost(Request $request, EventHost $host)
    {
        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'rank' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'contacts' => 'nullable|string|max:255',
            'extra_info' => 'nullable|string',
        ]);

        $host->update($validated);

        return back()->with('success', 'Данные ведущего обновлены');
    }

    public function destroyHost(EventHost $host)
    {
        $host->delete();

        return back()->with('success', 'Ведущий удален');
    }

    public function storeAchievement(Request $request)
    {
        $validated = $request->validate([
            'athlete_id' => 'required|exists:athletes,id',
            'event_name' => 'required|string|max:255',
            'event_type_id' => 'required|exists:event_types,id',
            'event_place' => 'nullable|string|max:255',
            'event_date' => 'nullable|date',
            'event_period' => 'nullable|string|max:255',
            'event_level_id' => 'nullable|exists:event_levels,id',
            'event_host_id' => 'nullable|exists:event_hosts,id',
            'result_label' => 'nullable|string|max:255',
            'result_place' => 'nullable|integer|min:1|max:3',
            'result_rank_id' => 'nullable|exists:ranks,id',
            'certificate_id' => 'nullable|string|max:255',
            'result_description' => 'nullable|string',
            'evidence_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:8192',
        ]);

        if ($request->hasFile('evidence_file')) {
            $validated['evidence_file_path'] = $request->file('evidence_file')->store('portfolio/evidence', 'public');
        }

        unset($validated['evidence_file']);
        PortfolioAchievement::create($validated);

        return back()->with('success', 'Достижение сохранено');
    }

    public function updateAchievement(Request $request, PortfolioAchievement $achievement)
    {
        $validated = $request->validate([
            'athlete_id' => 'required|exists:athletes,id',
            'event_name' => 'required|string|max:255',
            'event_type_id' => 'required|exists:event_types,id',
            'event_place' => 'nullable|string|max:255',
            'event_date' => 'nullable|date',
            'event_period' => 'nullable|string|max:255',
            'event_level_id' => 'nullable|exists:event_levels,id',
            'event_host_id' => 'nullable|exists:event_hosts,id',
            'result_label' => 'nullable|string|max:255',
            'result_place' => 'nullable|integer|min:1|max:3',
            'result_rank_id' => 'nullable|exists:ranks,id',
            'certificate_id' => 'nullable|string|max:255',
            'result_description' => 'nullable|string',
            'evidence_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:8192',
        ]);

        if ($request->hasFile('evidence_file')) {
            if ($achievement->evidence_file_path) {
                Storage::disk('public')->delete($achievement->evidence_file_path);
            }
            $validated['evidence_file_path'] = $request->file('evidence_file')->store('portfolio/evidence', 'public');
        }

        unset($validated['evidence_file']);
        $achievement->update($validated);

        return back()->with('success', 'Достижение обновлено');
    }

    public function destroyAchievement(PortfolioAchievement $achievement)
    {
        if ($achievement->evidence_file_path) {
            Storage::disk('public')->delete($achievement->evidence_file_path);
        }

        $achievement->delete();

        return back()->with('success', 'Достижение удалено');
    }

    public function exportSummaryCsv(): StreamedResponse
    {
        $rows = PortfolioAchievement::query()
            ->with(['athlete', 'eventType', 'eventLevel', 'eventHost', 'resultRank'])
            ->latest('event_date')
            ->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                'Спортсмен',
                'Мероприятие',
                'Тип',
                'Уровень',
                'Дата',
                'Период',
                'Место проведения',
                'Ведущий',
                'Результат',
                'Место',
                'Разряд',
                'ID сертификата',
            ], ';');

            foreach ($rows as $item) {
                fputcsv($out, [
                    trim(($item->athlete->last_name_nom ?? '') . ' ' . ($item->athlete->first_name_nom ?? '')),
                    $item->event_name,
                    $item->eventType?->name,
                    $item->eventLevel?->name,
                    $item->event_date,
                    $item->event_period,
                    $item->event_place,
                    $item->eventHost?->full_name,
                    $item->result_label,
                    $item->result_place,
                    $item->resultRank?->name,
                    $item->certificate_id,
                ], ';');
            }

            fclose($out);
        }, 'portfolio-summary-' . now()->format('Ymd-His') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportAthleteCsv(Request $request): StreamedResponse
    {
        $athleteId = $request->integer('athlete_id');
        abort_unless($athleteId, 422, 'Нужно выбрать спортсмена для выгрузки');

        $athlete = Athlete::findOrFail($athleteId);
        $rows = PortfolioAchievement::query()
            ->with(['eventType', 'eventLevel', 'eventHost', 'resultRank'])
            ->where('athlete_id', $athleteId)
            ->latest('event_date')
            ->get();

        return response()->streamDownload(function () use ($rows, $athlete) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                'Спортсмен',
                'Мероприятие',
                'Тип',
                'Уровень',
                'Дата',
                'Период',
                'Место проведения',
                'Ведущий',
                'Результат',
                'Место',
                'Разряд',
                'ID сертификата',
            ], ';');

            foreach ($rows as $item) {
                fputcsv($out, [
                    trim(($athlete->last_name_nom ?? '') . ' ' . ($athlete->first_name_nom ?? '')),
                    $item->event_name,
                    $item->eventType?->name,
                    $item->eventLevel?->name,
                    $item->event_date,
                    $item->event_period,
                    $item->event_place,
                    $item->eventHost?->full_name,
                    $item->result_label,
                    $item->result_place,
                    $item->resultRank?->name,
                    $item->certificate_id,
                ], ';');
            }

            fclose($out);
        }, 'portfolio-athlete-' . $athleteId . '-' . now()->format('Ymd-His') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportSummaryPdf()
    {
        $rows = PortfolioAchievement::query()
            ->with(['athlete', 'eventType', 'eventLevel', 'eventHost', 'resultRank'])
            ->latest('event_date')
            ->get();

        $pdf = Pdf::loadView('pdf.portfolio-summary', [
            'title' => 'Сводный отчет по мероприятиям',
            'rows' => $rows,
            'generatedAt' => now(),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('portfolio-summary-' . now()->format('Ymd-His') . '.pdf');
    }

    public function exportAthletePdf(Request $request)
    {
        $athleteId = $request->integer('athlete_id');
        abort_unless($athleteId, 422, 'Нужно выбрать спортсмена для выгрузки');

        $athlete = Athlete::findOrFail($athleteId);
        $rows = PortfolioAchievement::query()
            ->with(['eventType', 'eventLevel', 'eventHost', 'resultRank'])
            ->where('athlete_id', $athleteId)
            ->latest('event_date')
            ->get();

        $pdf = Pdf::loadView('pdf.portfolio-summary', [
            'title' => 'Отчет по спортсмену: ' . trim(($athlete->last_name_nom ?? '') . ' ' . ($athlete->first_name_nom ?? '')),
            'rows' => $rows,
            'generatedAt' => now(),
            'athlete' => $athlete,
        ])->setPaper('a4', 'landscape');

        return $pdf->download('portfolio-athlete-' . $athleteId . '-' . now()->format('Ymd-His') . '.pdf');
    }

    private function calculatePoints(PortfolioAchievement $achievement): int
    {
        $placePoints = match ((int) $achievement->result_place) {
            1 => 5,
            2 => 3,
            3 => 2,
            default => 1,
        };

        $levelMultiplier = match ($achievement->eventLevel?->name) {
            'Международный' => 5,
            'Всероссийский' => 4,
            'Окружной' => 3,
            'Региональный' => 2,
            default => 1,
        };

        return $placePoints * $levelMultiplier;
    }
}
