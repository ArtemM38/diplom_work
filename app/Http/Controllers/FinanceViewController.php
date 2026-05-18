<?php

namespace App\Http\Controllers;

use App\Models\Athlete;
use App\Models\AthleteBalanceHistory;
use App\Support\GuardianChildAccess;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FinanceViewController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user?->hasAnyRole(['athlete', 'guardian']), 403);

        $allowedAthletes = $this->allowedAthletes($user);
        abort_if($allowedAthletes->isEmpty(), 404);

        $athleteId = $request->integer('athlete_id') ?: $allowedAthletes->first()['id'];
        abort_unless($allowedAthletes->pluck('id')->contains($athleteId), 403);

        $operation = $request->input('operation', 'all');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $historySort = $request->input('history_sort', 'desc');

        $athlete = Athlete::with('finance')->findOrFail($athleteId);

        $selectedAthlete = [
            'id' => $athlete->id,
            'full_name' => $this->athleteFullName($athlete),
            'balance' => (float) ($athlete->finance?->balance ?? 0),
        ];

        $historyQuery = AthleteBalanceHistory::query()
            ->where('athlete_id', $athlete->id)
            ->when($operation === 'add', fn ($q) => $q->where('change_amount', '>', 0))
            ->when($operation === 'subtract', fn ($q) => $q->where('change_amount', '<', 0))
            ->when($dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('created_at', '<=', $dateTo))
            ->orderBy('created_at', $historySort === 'asc' ? 'asc' : 'desc');

        $history = $historyQuery->paginate(25)->withQueryString()->through(fn ($item) => [
            'id' => $item->id,
            'created_at' => optional($item->created_at)?->setTimezone('Asia/Irkutsk')->format('d.m.Y H:i'),
            'change_amount' => (float) $item->change_amount,
            'balance_before' => (float) $item->balance_before,
            'balance_after' => (float) $item->balance_after,
            'reason' => $item->reason,
            'status' => $item->status,
        ]);

        return Inertia::render('Finance/Show', [
            'isGuardian' => $user->hasRole('guardian'),
            'children' => $user->hasRole('guardian') ? $allowedAthletes->values() : [],
            'selectedAthlete' => $selectedAthlete,
            'history' => $history,
            'filters' => [
                'athlete_id' => $athleteId,
                'operation' => $operation,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'history_sort' => $historySort,
            ],
        ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{id: int, full_name: string, balance: float}>
     */
    private function allowedAthletes($user)
    {
        if ($user->hasRole('athlete')) {
            $athlete = $user->athlete;
            if (! $athlete) {
                return collect();
            }

            $athlete->load('finance');

            return collect([[
                'id' => $athlete->id,
                'full_name' => $this->athleteFullName($athlete),
                'balance' => (float) ($athlete->finance?->balance ?? 0),
            ]]);
        }

        $user->guardian?->load(['athletes.finance']);

        return GuardianChildAccess::childrenForGuardian($user)
            ->map(function (array $child) use ($user) {
                $athlete = $user->guardian->athletes->firstWhere('id', $child['id']);

                return [
                    'id' => $child['id'],
                    'full_name' => $child['full_name'],
                    'balance' => (float) ($athlete?->finance?->balance ?? 0),
                ];
            });
    }

    private function athleteFullName(Athlete $athlete): string
    {
        return GuardianChildAccess::fullName($athlete);
    }
}
