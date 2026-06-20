<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Athlete;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\PortfolioAchievement;
use App\Support\AthleteDocumentStatus;
use App\Support\DateFormatter;
use App\Support\ReportMeta;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PortfolioController extends Controller
{
    public function index(Request $request)
    {
        $athleteId = $request->integer('athlete_id');
        $athleteSearch = $request->string('athlete_search')->toString();

        $achievements = collect();
        if ($athleteId) {
            $fromEvents = EventParticipant::query()
                ->with(['event.eventType', 'event.eventLevel', 'event.eventHost', 'resultRank'])
                ->where('athlete_id', $athleteId)
                ->get()
                ->map(fn (EventParticipant $p) => $this->mapParticipantToAchievement($p));

            $legacy = PortfolioAchievement::query()
                ->with(['eventType', 'eventLevel', 'eventHost', 'resultRank'])
                ->where('athlete_id', $athleteId)
                ->get()
                ->map(fn (PortfolioAchievement $a) => $this->mapLegacyAchievement($a));

            $achievements = $fromEvents->concat($legacy)
                ->sortByDesc(fn ($item) => $item['event_date'] ?? '')
                ->values();
        }

        $athletes = Athlete::query()
            ->with('documents')
            ->select('id', 'last_name_nom', 'first_name_nom', 'middle_name_nom')
            ->when($athleteSearch, function ($query) use ($athleteSearch) {
                $query->where(function ($q) use ($athleteSearch) {
                    $q->where('last_name_nom', 'like', '%' . $athleteSearch . '%')
                        ->orWhere('first_name_nom', 'like', '%' . $athleteSearch . '%');
                });
            })
            ->orderBy('last_name_nom')
            ->get()
            ->map(function (Athlete $athlete) {
                $base = AthleteDocumentStatus::mapAthleteWithMedical($athlete);
                $base['achievements_count'] = EventParticipant::where('athlete_id', $athlete->id)->count()
                    + PortfolioAchievement::where('athlete_id', $athlete->id)->count();

                return $base;
            });

        $selectedAthlete = null;
        $athleteReport = null;
        if ($athleteId) {
            $athlete = Athlete::with('documents')->find($athleteId);
            if ($athlete) {
                $medical = AthleteDocumentStatus::medicalForAthlete($athlete);
                $selectedAthlete = [
                    'id' => $athlete->id,
                    'full_name' => trim("{$athlete->last_name_nom} {$athlete->first_name_nom} " . ($athlete->middle_name_nom ?? '')),
                    'medical_status' => $medical['status'],
                    'medical_days_left' => $medical['days_left'],
                    'medical_expiry_date' => $medical['expiry_date'],
                ];

                $athleteReport = [
                    'total' => $achievements->count(),
                    'places_1' => $achievements->where('result_place', 1)->count(),
                    'places_2' => $achievements->where('result_place', 2)->count(),
                    'places_3' => $achievements->where('result_place', 3)->count(),
                ];
            }
        }

        return Inertia::render('Admin/Portfolio/Index', [
            'athletes' => $athletes,
            'achievements' => $achievements,
            'selectedAthlete' => $selectedAthlete,
            'athleteReport' => $athleteReport,
            'filters' => $request->only(['athlete_id', 'athlete_search']),
        ]);
    }

    public function exportAthleteCsv(Request $request): StreamedResponse
    {
        $athleteId = $request->integer('athlete_id');
        abort_unless($athleteId, 422, 'Нужно выбрать спортсмена');

        $athlete = Athlete::findOrFail($athleteId);
        $rows = $this->collectAthleteRows($athleteId);

        return $this->streamCsv($rows, $athlete, 'portfolio-athlete-' . $athleteId);
    }

    public function exportAthletePdf(Request $request)
    {
        $athleteId = $request->integer('athlete_id');
        abort_unless($athleteId, 422, 'Нужно выбрать спортсмена');

        $athlete = Athlete::findOrFail($athleteId);
        $rows = $this->collectAthleteRows($athleteId);

        $pdf = Pdf::loadView('pdf.portfolio-summary', array_merge([
            'title' => 'Отчёт по спортсмену: ' . $this->athleteFullName($athlete),
            'rows' => $rows,
            'athleteName' => $this->athleteFullName($athlete),
        ], ReportMeta::forExport()))->setPaper('a4', 'landscape');

        return $pdf->download('portfolio-athlete-' . $athleteId . '-' . now()->format('Ymd-His') . '.pdf');
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function collectAthleteRows(int $athleteId)
    {
        $athlete = Athlete::find($athleteId);
        $athleteName = $athlete ? $this->athleteFullName($athlete) : '';

        $fromEvents = EventParticipant::query()
            ->with(['event.eventType', 'event.eventLevel', 'event.eventHost', 'resultRank'])
            ->where('athlete_id', $athleteId)
            ->get();

        $legacy = PortfolioAchievement::query()
            ->with(['eventType', 'eventLevel', 'eventHost', 'resultRank'])
            ->where('athlete_id', $athleteId)
            ->get();

        return $fromEvents->map(fn (EventParticipant $p) => $this->exportRowFromParticipant($p, $athleteName))
            ->concat($legacy->map(fn (PortfolioAchievement $a) => $this->exportRowFromLegacy($a, $athleteName)))
            ->sortByDesc('event_date_sort')
            ->values();
    }

    private function streamCsv($rows, Athlete $athlete, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows, $athlete) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Дата формирования', ReportMeta::generatedAtFormatted()], ';');
            fputcsv($out, ['Сформировал', ReportMeta::generatedByName()], ';');
            fputcsv($out, [], ';');
            fputcsv($out, [
                'Спортсмен', 'Мероприятие', 'Тип', 'Уровень', 'Дата',
                'Место проведения', 'Ведущий', 'Результат', 'Место', 'Разряд', 'ID сертификата',
            ], ';');

            foreach ($rows as $item) {
                fputcsv($out, [
                    $item['athlete_name'],
                    $item['event_name'] ?? '',
                    $item['event_type'] ?? '',
                    $item['event_level'] ?? '',
                    $item['event_date'] ?? '',
                    $item['event_place'] ?? '',
                    $item['event_host'] ?? '',
                    $item['result_label'] ?? '',
                    $item['result_place'] ?? '',
                    $item['result_rank'] ?? '',
                    $item['certificate_id'] ?? '',
                ], ';');
            }

            fclose($out);
        }, $filename . '-' . now()->format('Ymd-His') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function mapParticipantToAchievement(EventParticipant $p): array
    {
        $event = $p->event;

        return [
            'id' => 'ep-' . $p->id,
            'source' => 'event',
            'event_id' => $event?->id,
            'event_name' => $event?->name,
            'event_date' => DateFormatter::toDateString($event?->event_date),
            'event_date_display' => $this->formatEventDateDisplay($event),
            'event_date_range_display' => $event?->event_date_range_display,
            'event_place' => $event?->event_place,
            'cost' => $event?->cost,
            'event_type' => $event?->eventType?->name,
            'event_level' => $event?->eventLevel?->name,
            'event_host' => $event?->eventHost ? ['full_name' => $event->eventHost->full_name] : null,
            'result_label' => $p->result_label,
            'result_place' => $p->result_place,
            'result_rank' => $p->resultRank?->name,
            'certificate_id' => $p->certificate_id,
            'result_description' => $p->result_description,
            'evidence_file_path' => $p->evidence_file_path,
            'has_results' => $p->hasResults(),
        ];
    }

    private function mapLegacyAchievement(PortfolioAchievement $a): array
    {
        return [
            'id' => 'pa-' . $a->id,
            'source' => 'legacy',
            'event_name' => $a->event_name,
            'event_date' => DateFormatter::toDateString($a->event_date),
            'event_date_display' => DateFormatter::toDisplayDate($a->event_date),
            'event_place' => $a->event_place,
            'event_type' => $a->eventType?->name,
            'event_level' => $a->eventLevel?->name,
            'event_host' => $a->eventHost ? ['full_name' => $a->eventHost->full_name] : null,
            'result_label' => $a->result_label,
            'result_place' => $a->result_place,
            'result_rank' => $a->resultRank?->name,
            'certificate_id' => $a->certificate_id,
            'result_description' => $a->result_description,
            'evidence_file_path' => $a->evidence_file_path,
            'has_results' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function exportRowFromParticipant(EventParticipant $p, string $athleteName): array
    {
        $event = $p->event;

        return [
            'athlete_name' => $athleteName,
            'event_name' => $event?->name,
            'event_type' => $event?->eventType?->name,
            'event_level' => $event?->eventLevel?->name,
            'event_date' => $this->formatEventDateForExport($event),
            'event_place' => $event?->event_place,
            'event_host' => $event?->eventHost?->full_name,
            'result_label' => $p->result_label,
            'result_place' => $p->result_place,
            'result_rank' => $p->resultRank?->name,
            'certificate_id' => $p->certificate_id,
            'event_date_sort' => DateFormatter::toDateString($event?->event_date) ?? '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function exportRowFromLegacy(PortfolioAchievement $a, string $athleteName): array
    {
        return [
            'athlete_name' => $athleteName,
            'event_name' => $a->event_name,
            'event_type' => $a->eventType?->name,
            'event_level' => $a->eventLevel?->name,
            'event_date' => DateFormatter::toDisplayDate($a->event_date),
            'event_place' => $a->event_place,
            'event_host' => $a->eventHost?->full_name,
            'result_label' => $a->result_label,
            'result_place' => $a->result_place,
            'result_rank' => $a->resultRank?->name,
            'certificate_id' => $a->certificate_id,
            'event_date_sort' => DateFormatter::toDateString($a->event_date) ?? '',
        ];
    }

    private function formatEventDateForExport(?Event $event): ?string
    {
        if (! $event) {
            return null;
        }

        $from = DateFormatter::toDisplayDate($event->event_date);
        $to = DateFormatter::toDisplayDate($event->event_date_to);

        if ($from && $to && $to !== $from) {
            return "{$from} — {$to}";
        }

        return $from;
    }

    private function formatEventDateDisplay(?Event $event): ?string
    {
        return $this->formatEventDateForExport($event);
    }

    private function athleteFullName(Athlete $athlete): string
    {
        return trim("{$athlete->last_name_nom} {$athlete->first_name_nom} " . ($athlete->middle_name_nom ?? ''));
    }
}
