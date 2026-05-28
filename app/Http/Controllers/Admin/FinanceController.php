<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Athlete;
use App\Models\AthleteFinance;
use App\Models\AthleteBalanceHistory;
use App\Support\AthletePricing;
use App\Support\FormValidator;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FinanceController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $athleteId = $request->integer('athlete_id');
        $userActive = $request->input('user_active', 'all');
        $operation = $request->input('operation', 'all');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $historySort = $request->input('history_sort', 'desc');
        $balanceFilter = $request->input('balance', 'all');

        $athletes = Athlete::query()
            ->with(['finance', 'user:id,is_active'])
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('last_name_nom', 'like', '%' . $search . '%')
                        ->orWhere('first_name_nom', 'like', '%' . $search . '%');
                });
            })
            ->when($userActive === 'active', function ($query) {
                $query->where(function ($q) {
                    $q->whereNull('user_id')
                        ->orWhereHas('user', fn ($u) => $u->where('is_active', true));
                });
            })
            ->when($userActive === 'inactive', function ($query) {
                $query->whereHas('user', fn ($u) => $u->where('is_active', false));
            })
            ->when($balanceFilter === 'negative', function ($query) {
                $query->whereHas('finance', fn ($f) => $f->where('balance', '<', 0));
            })
            ->orderBy('last_name_nom')
            ->paginate(20)
            ->withQueryString()
            ->through(function (Athlete $athlete) {
                return [
                    'id' => $athlete->id,
                    'full_name' => trim($athlete->last_name_nom . ' ' . $athlete->first_name_nom . ' ' . ($athlete->middle_name_nom ?? '')),
                    'balance' => (float) ($athlete->finance?->balance ?? 0),
                    'discount_percent' => $athlete->finance?->discount_percent,
                    'user_active' => $athlete->user_id ? (bool) $athlete->user?->is_active : null,
                ];
            });

        $selectedAthlete = null;
        $history = [];
        if ($athleteId) {
            $athlete = Athlete::with(['finance', 'groups'])->find($athleteId);
            if ($athlete) {
                $selectedAthlete = [
                    'id' => $athlete->id,
                    'full_name' => trim($athlete->last_name_nom . ' ' . $athlete->first_name_nom . ' ' . ($athlete->middle_name_nom ?? '')),
                    'balance' => (float) ($athlete->finance?->balance ?? 0),
                    'discount_percent' => $athlete->finance?->discount_percent,
                    'groups' => $athlete->groups()->get(['groups.id', 'groups.name', 'groups.tariff_amount'])->map(fn ($g) => [
                        'id' => $g->id,
                        'name' => $g->name,
                        'tariff_amount' => (float) $g->tariff_amount,
                        'training_price' => (float) ($g->pivot->training_price ?? 0),
                    ]),
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
                    'created_at' => optional($item->created_at)?->setTimezone('Asia/Irkutsk')->format('Y-m-d H:i'),
                    'change_amount' => (float) $item->change_amount,
                    'balance_before' => (float) $item->balance_before,
                    'balance_after' => (float) $item->balance_after,
                    'reason' => $item->reason,
                    'status' => $item->status,
                ]);
            }
        }

        return Inertia::render('Admin/Finance/Index', [
            'athletes' => $athletes,
            'selectedAthlete' => $selectedAthlete,
            'history' => $history,
            'canManageDiscount' => $request->user()?->hasRole('admin') ?? false,
            'filters' => [
                'search' => $search,
                'athlete_id' => $athleteId,
                'user_active' => $userActive,
                'operation' => $operation,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'history_sort' => $historySort,
                'balance' => $balanceFilter,
            ],
        ]);
    }

    public function update(Request $request, Athlete $athlete)
    {
        if ($request->has('discount_percent')) {
            abort_unless($request->user()?->hasRole('admin'), 403);

            $validated = FormValidator::validate($request, [
                'discount_percent' => 'nullable|integer|min:0|max:100',
            ], [
                'discount_percent.min' => 'Скидка не может быть отрицательной.',
                'discount_percent.max' => 'Скидка не может превышать 100%.',
            ]);

            $finance = AthleteFinance::firstOrCreate(['athlete_id' => $athlete->id], ['balance' => 0]);
            $finance->update(['discount_percent' => $validated['discount_percent'] ?? null]);
            AthletePricing::applyDiscountToGroups($athlete);

            return back()->with('success', 'Скидка применена, стоимость тренировок пересчитана');
        }

        $validated = FormValidator::validate($request, [
            'amount' => 'required|numeric|min:0.01',
            'operation' => 'required|in:add,subtract',
            'reason' => 'required|string|max:255',
        ], [
            'reason.required' => 'Укажите причину операции.',
        ]);

        $finance = AthleteFinance::firstOrCreate(['athlete_id' => $athlete->id], [
            'balance' => 0,
        ]);

        $oldBalance = (float) $finance->balance;
        $amount = (float) $validated['amount'];
        $change = $validated['operation'] === 'subtract' ? -$amount : $amount;
        $newBalance = round($oldBalance + $change, 2);

        $finance->update(['balance' => $newBalance]);

        $athlete->balanceHistory()->create([
            'change_amount' => $change,
            'balance_before' => $oldBalance,
            'balance_after' => $newBalance,
            'reason' => $validated['reason'] ?: 'Ручная корректировка баланса',
            'status' => 'manual',
            'changed_by' => $request->user()?->id,
        ]);

        return back()->with('success', 'Финансы спортсмена обновлены');
    }
}
