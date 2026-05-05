<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Athlete;
use App\Models\AthleteFinance;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FinanceController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $athleteId = $request->integer('athlete_id');

        $athletes = Athlete::query()
            ->with('finance')
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('last_name_nom', 'like', '%' . $search . '%')
                        ->orWhere('first_name_nom', 'like', '%' . $search . '%');
                });
            })
            ->orderBy('last_name_nom')
            ->get()
            ->map(function (Athlete $athlete) {
                return [
                    'id' => $athlete->id,
                    'full_name' => trim($athlete->last_name_nom . ' ' . $athlete->first_name_nom . ' ' . ($athlete->middle_name_nom ?? '')),
                    'balance' => (float) ($athlete->finance?->balance ?? 0),
                ];
            });

        $selectedAthlete = null;
        $history = [];
        if ($athleteId) {
            $athlete = Athlete::with(['finance', 'balanceHistory' => fn ($q) => $q->latest()->limit(100)])->find($athleteId);
            if ($athlete) {
                $selectedAthlete = [
                    'id' => $athlete->id,
                    'full_name' => trim($athlete->last_name_nom . ' ' . $athlete->first_name_nom . ' ' . ($athlete->middle_name_nom ?? '')),
                    'balance' => (float) ($athlete->finance?->balance ?? 0),
                ];
                $history = $athlete->balanceHistory->map(fn ($item) => [
                    'id' => $item->id,
                    'created_at' => optional($item->created_at)?->setTimezone('Asia/Irkutsk')->format('Y-m-d H:i'),
                    'change_amount' => (float) $item->change_amount,
                    'balance_before' => (float) $item->balance_before,
                    'balance_after' => (float) $item->balance_after,
                    'reason' => $item->reason,
                    'status' => $item->status,
                ])->values();
            }
        }

        return Inertia::render('Admin/Finance/Index', [
            'athletes' => $athletes,
            'selectedAthlete' => $selectedAthlete,
            'history' => $history,
            'filters' => [
                'search' => $search,
                'athlete_id' => $athleteId,
            ],
        ]);
    }

    public function update(Request $request, Athlete $athlete)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'operation' => 'required|in:add,subtract',
            'reason' => 'nullable|string|max:255',
        ]);

        $finance = AthleteFinance::firstOrCreate(['athlete_id' => $athlete->id], [
            'balance' => 0,
            'training_price' => 0,
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
