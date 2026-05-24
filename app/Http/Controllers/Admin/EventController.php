<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Athlete;
use App\Models\Event;
use App\Models\EventHost;
use App\Models\EventLevel;
use App\Models\EventParticipant;
use App\Models\EventType;
use App\Models\Rank;
use App\Support\AthleteDocumentStatus;
use App\Support\AthleteRankSync;
use App\Support\PortfolioAchievementSync;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EventController extends Controller
{
    private function ensureCanEdit(Request $request): void
    {
        $user = $request->user();
        abort_if($user?->hasRole('accountant') && ! $user->hasAnyRole(['admin', 'coach']), 403);
    }

    private function isReadOnly(Request $request): bool
    {
        $user = $request->user();

        return $user?->hasRole('accountant') && ! $user->hasAnyRole(['admin', 'coach']);
    }

    public function index(Request $request)
    {
        $search = $request->string('search')->toString();

        $events = Event::query()
            ->with(['eventType', 'eventLevel', 'eventHost'])
            ->withCount('participants')
            ->when($search, fn ($q) => $q->where('name', 'like', '%' . $search . '%'))
            ->orderByDesc('event_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Admin/Events/Index', [
            'events' => $events,
            'eventTypes' => EventType::orderBy('name')->get(),
            'eventLevels' => EventLevel::orderBy('name')->get(),
            'eventHosts' => EventHost::orderBy('full_name')->get(),
            'readOnly' => $this->isReadOnly($request),
            'filters' => ['search' => $search],
        ]);
    }

    public function store(Request $request)
    {
        $this->ensureCanEdit($request);
        $validated = $this->validateEvent($request);
        Event::create($validated);

        return redirect()->route('admin.events')->with('success', 'Мероприятие создано');
    }

    public function update(Request $request, Event $event)
    {
        $this->ensureCanEdit($request);
        $validated = $this->validateEvent($request);
        $event->update($validated);
        PortfolioAchievementSync::syncEventMetadata($event);

        return back()->with('success', 'Мероприятие обновлено');
    }

    public function destroy(Request $request, Event $event)
    {
        $this->ensureCanEdit($request);
        $event->delete();

        return redirect()->route('admin.events')->with('success', 'Мероприятие удалено');
    }

    public function show(Request $request, Event $event)
    {
        $athleteSearch = $request->string('athlete_search')->toString();

        $event->load(['eventType', 'eventLevel', 'eventHost']);

        $participants = $event->participants()
            ->with(['athlete.documents', 'resultRank'])
            ->get()
            ->map(function (EventParticipant $participant) {
                $athlete = $participant->athlete;
                $medical = $athlete ? AthleteDocumentStatus::medicalForAthlete($athlete) : ['status' => 'missing', 'days_left' => null, 'expiry_date' => null];

                return [
                    'id' => $participant->id,
                    'athlete_id' => $participant->athlete_id,
                    'full_name' => $athlete
                        ? trim("{$athlete->last_name_nom} {$athlete->first_name_nom} " . ($athlete->middle_name_nom ?? ''))
                        : '—',
                    'result_label' => $participant->result_label,
                    'result_place' => $participant->result_place,
                    'result_rank_id' => $participant->result_rank_id,
                    'result_rank' => $participant->resultRank?->name,
                    'certificate_id' => $participant->certificate_id,
                    'result_description' => $participant->result_description,
                    'evidence_file_path' => $participant->evidence_file_path,
                    'results_filled_at' => $participant->results_filled_at?->toDateTimeString(),
                    'has_results' => $participant->hasResults(),
                    'medical_status' => $medical['status'],
                    'medical_days_left' => $medical['days_left'],
                    'medical_expiry_date' => $medical['expiry_date'],
                ];
            });

        $availableAthletes = Athlete::query()
            ->with('documents')
            ->when($athleteSearch, function ($query) use ($athleteSearch) {
                $query->where(function ($q) use ($athleteSearch) {
                    $q->where('last_name_nom', 'like', '%' . $athleteSearch . '%')
                        ->orWhere('first_name_nom', 'like', '%' . $athleteSearch . '%');
                });
            })
            ->whereNotIn('id', $participants->pluck('athlete_id'))
            ->orderBy('last_name_nom')
            ->limit(50)
            ->get()
            ->map(fn (Athlete $a) => AthleteDocumentStatus::mapAthleteWithMedical($a));

        return Inertia::render('Admin/Events/Show', [
            'event' => $event,
            'participants' => $participants,
            'availableAthletes' => $availableAthletes,
            'eventTypes' => EventType::orderBy('name')->get(),
            'eventLevels' => EventLevel::orderBy('name')->get(),
            'eventHosts' => EventHost::orderBy('full_name')->get(),
            'ranks' => Rank::orderBy('name')->get(),
            'readOnly' => $this->isReadOnly($request),
            'filters' => ['athlete_search' => $athleteSearch],
        ]);
    }

    public function attachAthlete(Request $request, Event $event)
    {
        $this->ensureCanEdit($request);
        $validated = $request->validate([
            'athlete_id' => 'required|exists:athletes,id',
        ]);

        $participant = EventParticipant::firstOrCreate([
            'event_id' => $event->id,
            'athlete_id' => $validated['athlete_id'],
        ]);

        PortfolioAchievementSync::fromParticipant($participant);

        return back()->with('success', 'Спортсмен добавлен в мероприятие');
    }

    public function detachAthlete(Request $request, Event $event, Athlete $athlete)
    {
        $this->ensureCanEdit($request);
        $participant = EventParticipant::query()
            ->where('event_id', $event->id)
            ->where('athlete_id', $athlete->id)
            ->first();

        if ($participant) {
            if ($participant->evidence_file_path) {
                Storage::disk('public')->delete($participant->evidence_file_path);
            }
            AthleteRankSync::removeForParticipant($participant);
            PortfolioAchievementSync::removeForParticipant($participant);
            $participant->delete();
        }

        return back()->with('success', 'Спортсмен исключён из мероприятия');
    }

    public function updateResults(Request $request, Event $event)
    {
        $this->ensureCanEdit($request);

        $validated = $request->validate([
            'status' => 'nullable|in:planned,completed',
            'participants' => 'required|array',
            'participants.*.id' => 'required|exists:event_participants,id',
            'participants.*.result_label' => 'nullable|string|max:255',
            'participants.*.result_place' => 'nullable|integer|min:1|max:3',
            'participants.*.result_rank_id' => 'nullable|exists:ranks,id',
            'participants.*.certificate_id' => 'nullable|string|max:255',
            'participants.*.result_description' => 'nullable|string',
        ]);

        if (isset($validated['status'])) {
            $event->update(['status' => $validated['status']]);
        }

        foreach ($validated['participants'] as $row) {
            $participant = EventParticipant::query()
                ->where('event_id', $event->id)
                ->where('id', $row['id'])
                ->first();

            if (! $participant) {
                continue;
            }

            $fileKey = 'evidence_' . $participant->id;
            if ($request->hasFile($fileKey)) {
                if ($participant->evidence_file_path) {
                    Storage::disk('public')->delete($participant->evidence_file_path);
                }
                $row['evidence_file_path'] = $request->file($fileKey)->store('portfolio/evidence', 'public');
            }

            $participant->update([
                'result_label' => $row['result_label'] ?? null,
                'result_place' => $row['result_place'] ?? null,
                'result_rank_id' => $row['result_rank_id'] ?? null,
                'certificate_id' => $row['certificate_id'] ?? null,
                'result_description' => $row['result_description'] ?? null,
                'evidence_file_path' => $row['evidence_file_path'] ?? $participant->evidence_file_path,
                'results_filled_at' => now(),
            ]);

            $participant = $participant->fresh(['event']);
            PortfolioAchievementSync::fromParticipant($participant);
            AthleteRankSync::fromParticipant($participant);
        }

        if (($validated['status'] ?? $event->status) === 'completed') {
            $event->update(['status' => 'completed']);
        }

        return back()->with('success', 'Результаты сохранены');
    }

    public function storeHost(Request $request)
    {
        $this->ensureCanEdit($request);
        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'birth_date' => 'nullable|date',
            'rank' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'contacts' => 'nullable|string|max:255',
            'extra_info' => 'nullable|string',
        ]);

        EventHost::create($validated);

        return back()->with('success', 'Ведущий добавлен');
    }

    public function updateHost(Request $request, EventHost $host)
    {
        $this->ensureCanEdit($request);
        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'birth_date' => 'nullable|date',
            'rank' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'contacts' => 'nullable|string|max:255',
            'extra_info' => 'nullable|string',
        ]);

        $host->update($validated);

        return back()->with('success', 'Ведущий обновлён');
    }

    public function destroyHost(Request $request, EventHost $host)
    {
        $this->ensureCanEdit($request);
        $host->delete();

        return back()->with('success', 'Ведущий удалён');
    }

    public function exportEventCsv(Event $event): StreamedResponse
    {
        $event->load(['eventType', 'eventLevel', 'eventHost']);
        $rows = $event->participants()->with(['athlete', 'resultRank'])->get();

        return response()->streamDownload(function () use ($rows, $event) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                'Мероприятие', 'Тип', 'Уровень', 'Дата', 'Место', 'Ведущий', 'Стоимость',
                'Спортсмен', 'Результат', 'Место', 'Разряд', 'ID сертификата', 'Мед. справка',
            ], ';');

            foreach ($rows as $item) {
                $athlete = $item->athlete;
                $medical = $athlete ? AthleteDocumentStatus::medicalForAthlete($athlete) : ['status' => 'missing'];

                fputcsv($out, [
                    $event->name,
                    $event->eventType?->name,
                    $event->eventLevel?->name,
                    $event->event_date,
                    $event->event_place,
                    $event->eventHost?->full_name,
                    $event->cost,
                    $athlete ? trim("{$athlete->last_name_nom} {$athlete->first_name_nom}") : '',
                    $item->result_label,
                    $item->result_place,
                    $item->resultRank?->name,
                    $item->certificate_id,
                    $medical['status'],
                ], ';');
            }

            fclose($out);
        }, 'event-' . $event->id . '-' . now()->format('Ymd-His') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportEventPdf(Event $event)
    {
        $event->load(['eventType', 'eventLevel', 'eventHost']);
        $rows = $event->participants()->with(['athlete', 'resultRank'])->get();

        $pdf = Pdf::loadView('pdf.event-report', [
            'event' => $event,
            'rows' => $rows,
            'generatedAt' => now(),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('event-' . $event->id . '-' . now()->format('Ymd-His') . '.pdf');
    }

    private function validateEvent(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'cost' => 'required|numeric|min:0',
            'event_type_id' => 'required|exists:event_types,id',
            'event_level_id' => 'nullable|exists:event_levels,id',
            'event_place' => 'nullable|string|max:255',
            'event_host_id' => 'nullable|exists:event_hosts,id',
            'event_date' => 'nullable|date',
            'event_period' => 'nullable|string|max:255',
            'status' => 'nullable|in:planned,completed',
        ]);
    }
}
