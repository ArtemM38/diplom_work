<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Athlete;
use App\Models\Event;
use App\Models\EventHost;
use App\Models\EventLevel;
use App\Models\EventParticipant;
use App\Models\EventParticipantEvidenceFile;
use App\Models\EventType;
use App\Models\Rank;
use App\Support\AdminPermissions;
use App\Support\AthleteDocumentStatus;
use App\Support\EventParticipationBilling;
use App\Support\FormValidator;
use App\Support\AthleteRankSync;
use App\Services\AthleteNotificationService;
use App\Support\ReportMeta;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EventController extends Controller
{
    private function ensureCanEdit(Request $request): void
    {
        abort_unless(AdminPermissions::canManageStructure($request->user()), 403);
    }

    private function isReadOnly(Request $request): bool
    {
        return ! AdminPermissions::canManageStructure($request->user());
    }

    public function index(Request $request)
    {
        $search = trim($request->string('search')->toString());
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $eventTypeId = $request->input('event_type_id');
        $eventLevelId = $request->input('event_level_id');

        $events = Event::query()
            ->with(['eventType', 'eventLevel', 'eventHost'])
            ->withCount('participants')
            ->when($search !== '', fn ($q) => $q->where('name', 'like', '%' . $search . '%'))
            ->when($dateFrom, fn ($q) => $q->whereRaw('COALESCE(event_date_to, event_date) >= ?', [$dateFrom]))
            ->when($dateTo, fn ($q) => $q->whereDate('event_date', '<=', $dateTo))
            ->when($eventTypeId, fn ($q) => $q->where('event_type_id', $eventTypeId))
            ->when($eventLevelId, fn ($q) => $q->where('event_level_id', $eventLevelId))
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
            'filters' => [
                'search' => $search,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'event_type_id' => $eventTypeId,
                'event_level_id' => $eventLevelId,
            ],
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
            ->with(['athlete.documents', 'athlete.inventoryItems', 'resultRank', 'evidenceFiles'])
            ->get()
            ->map(fn (EventParticipant $participant) => $this->mapParticipant($participant));

        $availableAthletes = Athlete::query()
            ->with(['documents', 'inventoryItems'])
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
            ->map(fn (Athlete $a) => AthleteDocumentStatus::mapAthleteForEvent($a));

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

        $athlete = Athlete::findOrFail($validated['athlete_id']);

        $participant = EventParticipant::firstOrCreate([
            'event_id' => $event->id,
            'athlete_id' => $athlete->id,
        ]);

        if ($participant->wasRecentlyCreated) {
            app(AthleteNotificationService::class)->notifyEventRegistration($event, $athlete);
        }

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
            foreach ($participant->evidenceFiles as $file) {
                Storage::disk('public')->delete($file->file_path);
            }
            AthleteRankSync::removeForParticipant($participant);
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
                $uploads = $request->file($fileKey);
                $uploads = is_array($uploads) ? $uploads : [$uploads];
                foreach ($uploads as $upload) {
                    $path = $upload->store('portfolio/evidence', 'public');
                    $participant->evidenceFiles()->create([
                        'file_path' => $path,
                        'original_name' => $upload->getClientOriginalName(),
                    ]);
                }
            }

            $participant->update([
                'result_label' => $row['result_label'] ?? null,
                'result_place' => $row['result_place'] ?? null,
                'result_rank_id' => $row['result_rank_id'] ?? null,
                'certificate_id' => $row['certificate_id'] ?? null,
                'result_description' => $row['result_description'] ?? null,
                'results_filled_at' => now(),
            ]);

            $participant = $participant->fresh(['event']);
            AthleteRankSync::fromParticipant($participant);
        }

        if (($validated['status'] ?? $event->status) === 'completed') {
            $event->update(['status' => 'completed']);
        }

        return back()->with('success', 'Результаты сохранены');
    }

    public function deleteEvidenceFile(Request $request, Event $event, EventParticipantEvidenceFile $evidenceFile)
    {
        $this->ensureCanEdit($request);

        $participant = $evidenceFile->participant;
        abort_unless($participant && $participant->event_id === $event->id, 404);

        Storage::disk('public')->delete($evidenceFile->file_path);
        $evidenceFile->delete();

        return back()->with('success', 'Файл удалён');
    }

    public function updateAttendance(Request $request, Event $event)
    {
        $this->ensureCanEdit($request);

        $validated = $request->validate([
            'participants' => 'required|array',
            'participants.*.id' => 'required|exists:event_participants,id',
            'participants.*.attendance_status' => 'nullable|in:Я,Н,У',
            'certificates' => 'nullable|array',
            'certificates.*' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        foreach ($validated['participants'] as $index => $row) {
            $participant = EventParticipant::query()
                ->where('event_id', $event->id)
                ->where('id', $row['id'])
                ->first();

            if (! $participant) {
                continue;
            }

            $status = $row['attendance_status'] ?? null;
            $data = ['attendance_status' => $status];

            $fileKey = "certificates.{$row['id']}";
            if ($request->hasFile($fileKey)) {
                if ($participant->excused_certificate) {
                    Storage::disk('public')->delete($participant->excused_certificate);
                }
                $data['excused_certificate'] = $request->file($fileKey)->store('event-attendance-certificates', 'public');
            } elseif ($status !== 'У') {
                $data['excused_certificate'] = null;
            }

            if ($status === 'У' && ! ($data['excused_certificate'] ?? $participant->excused_certificate)) {
                return back()->withErrors([
                    "participants.{$index}.attendance_status" => 'Для уважительной неявки (У) приложите справку.',
                ]);
            }

            $participant->update($data);
            EventParticipationBilling::sync($participant->fresh(), $status, $request->user()?->id);
        }

        return back()->with('success', 'Посещаемость на мероприятии сохранена');
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
            fputcsv($out, ['Дата формирования', ReportMeta::generatedAtFormatted()], ';');
            fputcsv($out, ['Сформировал', ReportMeta::generatedByName()], ';');
            fputcsv($out, [], ';');
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

        $pdf = Pdf::loadView('pdf.event-report', array_merge([
            'event' => $event,
            'rows' => $rows,
        ], ReportMeta::forExport()))->setPaper('a4', 'landscape');

        return $pdf->download('event-' . $event->id . '-' . now()->format('Ymd-His') . '.pdf');
    }

    private function validateEvent(Request $request): array
    {
        $validated = FormValidator::validate($request, [
            'name' => 'required|string|max:255',
            'cost' => 'required|numeric|min:0|regex:/^\d+(\.\d{1,2})?$/',
            'event_type_id' => 'required|exists:event_types,id',
            'event_level_id' => 'nullable|exists:event_levels,id',
            'event_place' => 'nullable|string|max:255',
            'event_host_id' => 'nullable|exists:event_hosts,id',
            'event_date' => 'required|date',
            'event_date_to' => 'nullable|date|after_or_equal:event_date',
            'status' => 'nullable|in:planned,completed',
        ]);

        if (empty($validated['event_date_to'])) {
            $validated['event_date_to'] = null;
        }

        return $validated;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapParticipant(EventParticipant $participant): array
    {
        $athlete = $participant->athlete;
        $base = $athlete
            ? AthleteDocumentStatus::mapAthleteForEvent($athlete)
            : [
                'full_name' => '—',
                'medical_status' => 'missing',
                'documents' => [],
                'inventory_items' => [],
            ];

        return array_merge($base, [
            'id' => $participant->id,
            'athlete_id' => $participant->athlete_id,
            'attendance_status' => $participant->attendance_status,
            'excused_certificate' => $participant->excused_certificate,
            'result_label' => $participant->result_label,
            'result_place' => $participant->result_place,
            'result_rank_id' => $participant->result_rank_id,
            'result_rank' => $participant->resultRank?->name,
            'certificate_id' => $participant->certificate_id,
            'result_description' => $participant->result_description,
            'evidence_files' => $participant->evidenceFiles->map(fn ($file) => [
                'id' => $file->id,
                'url' => $file->url,
                'original_name' => $file->original_name ?? basename($file->file_path),
            ])->values()->all(),
            'results_filled_at' => $participant->results_filled_at?->toDateTimeString(),
            'has_results' => $participant->hasResults(),
        ]);
    }
}
