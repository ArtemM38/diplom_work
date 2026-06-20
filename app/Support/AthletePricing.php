<?php

namespace App\Support;

use App\Models\Athlete;
use App\Models\AthleteFinance;

class AthletePricing
{
    public static function discountPercent(?AthleteFinance $finance): int
    {
        $discount = (float) ($finance?->discount ?? 0);
        if ($discount < 10) {
            return 0;
        }

        return min(100, $discount);
    }

    public static function effectivePrice(float $basePrice, ?AthleteFinance $finance): float
    {
        $discount = self::discountPercent($finance);
        if ($discount === 0 || $basePrice <= 0) {
            return round($basePrice, 2);
        }

        return round($basePrice * (100 - $discount) / 100, 2);
    }

    public static function applyDiscountToGroups(Athlete $athlete): void
    {
        $finance = AthleteFinance::firstWhere('athlete_id', $athlete->id);
        $athlete->load('groups');

        foreach ($athlete->groups as $group) {
            $base = (float) $group->tariff_amount;
            $price = self::effectivePrice($base, $finance);
            $athlete->groups()->updateExistingPivot($group->id, ['training_price' => $price]);
        }
    }
}
