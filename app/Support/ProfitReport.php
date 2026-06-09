<?php

namespace App\Support;

use App\Models\AthleteBalanceHistory;
use App\Models\Group;
use App\Models\User;
use Illuminate\Support\Carbon;

class ProfitReport
{
    /**
     * @param  array{date_from: string, date_to: string, group_id?: int|null, coach_id?: int|null, athlete_id?: int|null}  $filters
     * @return array{
     *     total_profit: int,
     *     operations_count: int,
     *     rows: list<array<string, mixed>>,
     *     by_source: array<string, int>
     * }
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
            ])
            ->where('change_amount', '<', 0)
            ->whereBetween('created_at', [$from, $to]);

        if (! empty($filters['athlete_id'])) {
            $query->where('athlete_id', (int) $filters['athlete_id']);
        }

        if (! empty($filters['group_id'])) {
            $groupId = (int) $filters['group_id'];
            $query->where(function ($q) use ($groupId) {
                $q->whereHas('schedule', fn ($s) => $s->where('group_id', $groupId))
                    ->orWhereHas('athlete.groups', fn ($g) => $g->where('groups.id', $groupId));
            });
        }

        if (! empty($filters['coach_id'])) {
            $coachId = (int) $filters['coach_id'];
            $query->whereHas('schedule', fn ($s) => $s->where('coach_id', $coachId));
        }

        $items = $query->orderByDesc('created_at')->get();

        $bySource = [
            'training' => 0,
            'event' => 0,
            'manual' => 0,
        ];

        $rows = $items->map(function (AthleteBalanceHistory $item) use (&$bySource) {
            $amount = abs((int) $item->change_amount);
            $source = 'manual';
            if ($item->schedule_id) {
                $source = 'training';
            } elseif ($item->event_participant_id) {
                $source = 'event';
            }
            $bySource[$source] += $amount;

            $athlete = $item->athlete;

            return [
                'id' => $item->id,
                'date' => $item->created_at?->timezone('Asia/Irkutsk')->format('d.m.Y H:i'),
                'athlete_name' => $athlete
                    ? trim("{$athlete->last_name_nom} {$athlete->first_name_nom} {$athlete->middle_name_nom}")
                    : '—',
                'amount' => $amount,
                'reason' => $item->reason,
                'source' => $source,
                'source_label' => match ($source) {
                    'training' => 'Тренировка',
                    'event' => 'Мероприятие',
                    default => 'Ручная операция',
                },
                'group' => $item->schedule?->group?->name,
                'coach' => $item->schedule?->coach?->name,
            ];
        })->values()->all();

        return [
            'total_profit' => array_sum($bySource),
            'operations_count' => count($rows),
            'rows' => $rows,
            'by_source' => $bySource,
        ];
    }

    /**
     * @return array{groups: \Illuminate\Support\Collection, coaches: \Illuminate\Support\Collection, athletes: \Illuminate\Support\Collection}
     */
    public static function filterOptions(): array
    {
        return [
            'groups' => Group::visible()->orderBy('name')->get(['id', 'name']),
            'coaches' => User::withRole('coach')->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'athletes' => \App\Models\Athlete::query()
                ->orderBy('last_name_nom')
                ->orderBy('first_name_nom')
                ->get(['id', 'last_name_nom', 'first_name_nom', 'middle_name_nom'])
                ->map(fn ($a) => [
                    'id' => $a->id,
                    'full_name' => trim("{$a->last_name_nom} {$a->first_name_nom} {$a->middle_name_nom}"),
                ]),
        ];
    }
}
