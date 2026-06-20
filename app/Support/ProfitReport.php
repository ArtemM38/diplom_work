<?php

namespace App\Support;

use App\Models\AthleteBalanceHistory;
use App\Models\Event;
use App\Models\Location;
use App\Models\User;
use Illuminate\Support\Carbon;

class ProfitReport
{
    /**
     * @param  array{
     *     date_from: string,
     *     date_to: string,
     *     coach_id?: int|null,
     *     athlete_id?: int|null,
     *     location_id?: int|null,
     *     event_id?: int|null
     * }  $filters
     * @return array<string, mixed>
     */
    public static function build(array $filters): array
    {
        $from = Carbon::parse($filters['date_from'])->startOfDay();
        $to = Carbon::parse($filters['date_to'])->endOfDay();

        $query = AthleteBalanceHistory::query()
            ->with([
                'athlete',
                'schedule.group',
                'schedule.coach',
                'schedule.location',
            ])
            ->whereBetween('created_at', [$from, $to]);

        if (! empty($filters['athlete_id'])) {
            $query->where('athlete_id', (int) $filters['athlete_id']);
        }

        if (! empty($filters['location_id'])) {
            $locationId = (int) $filters['location_id'];
            $query->where(function ($q) use ($locationId) {
                $q->whereHas('schedule', fn ($s) => $s->where('location_id', $locationId));
            });
        }

        if (! empty($filters['event_id'])) {
            $eventId = (int) $filters['event_id'];
            $participantIds = \App\Models\EventParticipant::query()
                ->where('event_id', $eventId)
                ->pluck('id');
            $query->whereIn('event_participant_id', $participantIds);
        }

        if (! empty($filters['coach_id'])) {
            $coachId = (int) $filters['coach_id'];
            $query->where(function ($q) use ($coachId) {
                $q->whereHas('schedule', fn ($s) => $s->where('coach_id', $coachId))
                    ->orWhereNull('schedule_id');
            });
        }

        $items = $query->orderByDesc('created_at')->get();

        $bySource = [
            'training' => 0.0,
            'event' => 0.0,
            'manual' => 0.0,
        ];
        $refundsBySource = [
            'training' => 0.0,
            'event' => 0.0,
            'manual' => 0.0,
        ];
        $totalDeposits = 0.0;
        $rows = [];

        foreach ($items as $item) {
            $amount = (float) $item->change_amount;
            $source = self::resolveSource($item);
            $isRefund = $amount > 0 && $item->attendance_id !== null;
            $isDeposit = $amount > 0 && ! $isRefund;

            if ($isDeposit) {
                $totalDeposits += $amount;
            }

            if ($amount < 0) {
                $bySource[$source] += abs($amount);
            } elseif ($isRefund) {
                $refundsBySource[$source] += $amount;
            }

            $athlete = $item->athlete;

            $rows[] = [
                'id' => $item->id,
                'date' => $item->created_at?->timezone('Asia/Irkutsk')->format('d.m.Y H:i'),
                'athlete_name' => $athlete
                    ? trim("{$athlete->last_name_nom} {$athlete->first_name_nom} {$athlete->middle_name_nom}")
                    : '—',
                'amount' => abs($amount),
                'signed_amount' => $amount,
                'operation_type' => $amount < 0 ? 'charge' : ($isRefund ? 'refund' : 'deposit'),
                'operation_label' => match (true) {
                    $amount < 0 => 'Списание',
                    $isRefund => 'Возврат',
                    default => 'Пополнение',
                },
                'reason' => $item->reason,
                'source' => $source,
                'source_label' => match ($source) {
                    'training' => 'Тренировка',
                    'event' => 'Мероприятие',
                    default => 'Ручная операция',
                },
                'group' => $item->schedule?->group?->name,
                'coach' => $item->schedule?->coach?->name,
                'location' => $item->schedule?->location?->name,
            ];
        }

        $grossProfit = array_sum($bySource);
        $totalRefunds = array_sum($refundsBySource);
        $netProfit = round($grossProfit - $totalRefunds, 2);

        return [
            'total_profit' => $netProfit,
            'gross_profit' => round($grossProfit, 2),
            'total_refunds' => round($totalRefunds, 2),
            'total_deposits' => round($totalDeposits, 2),
            'operations_count' => count(array_filter($rows, fn ($r) => $r['operation_type'] === 'charge')),
            'rows' => $rows,
            'by_source' => array_map(fn ($v) => round($v, 2), $bySource),
            'refunds_by_source' => array_map(fn ($v) => round($v, 2), $refundsBySource),
        ];
    }

    private static function resolveSource(AthleteBalanceHistory $item): string
    {
        if ($item->schedule_id) {
            return 'training';
        }

        if ($item->event_participant_id) {
            return 'event';
        }

        return 'manual';
    }

    /**
     * @return array{
     *     coaches: \Illuminate\Support\Collection,
     *     athletes: \Illuminate\Support\Collection,
     *     locations: \Illuminate\Support\Collection,
     *     events: \Illuminate\Support\Collection
     * }
     */
    public static function filterOptions(): array
    {
        return [
            'coaches' => User::withRole('coach')->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'athletes' => \App\Models\Athlete::query()
                ->orderBy('last_name_nom')
                ->orderBy('first_name_nom')
                ->get(['id', 'last_name_nom', 'first_name_nom', 'middle_name_nom'])
                ->map(fn ($a) => [
                    'id' => $a->id,
                    'full_name' => trim("{$a->last_name_nom} {$a->first_name_nom} {$a->middle_name_nom}"),
                ]),
            'locations' => Location::query()->orderBy('name')->get(['id', 'name']),
            'events' => Event::query()
                ->orderByDesc('event_date')
                ->orderBy('name')
                ->get(['id', 'name', 'event_date'])
                ->map(fn (Event $e) => [
                    'id' => $e->id,
                    'name' => $e->name,
                    'event_date' => $e->event_date?->format('d.m.Y'),
                ]),
        ];
    }
}
