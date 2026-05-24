<?php

namespace App\Support;

use App\Models\Athlete;
use Carbon\Carbon;

class AthleteDocumentStatus
{
    /**
     * @return array{status: string, days_left: int|null, expiry_date: string|null}
     */
    public static function medicalForAthlete(Athlete $athlete): array
    {
        $medical = $athlete->documents?->firstWhere('type', 'medical');

        if (! $medical?->expiry_date) {
            return ['status' => 'missing', 'days_left' => null, 'expiry_date' => null];
        }

        $expiry = Carbon::parse($medical->expiry_date)->startOfDay();
        $daysLeft = now()->startOfDay()->diffInDays($expiry, false);

        if ($daysLeft < 0) {
            return ['status' => 'expired', 'days_left' => $daysLeft, 'expiry_date' => $medical->expiry_date];
        }

        if ($daysLeft <= 3) {
            return ['status' => 'warning', 'days_left' => $daysLeft, 'expiry_date' => $medical->expiry_date];
        }

        return ['status' => 'ok', 'days_left' => $daysLeft, 'expiry_date' => $medical->expiry_date];
    }

    public static function mapAthleteWithMedical(Athlete $athlete): array
    {
        $medical = self::medicalForAthlete($athlete);

        return [
            'id' => $athlete->id,
            'full_name' => trim("{$athlete->last_name_nom} {$athlete->first_name_nom} " . ($athlete->middle_name_nom ?? '')),
            'medical_status' => $medical['status'],
            'medical_days_left' => $medical['days_left'],
            'medical_expiry_date' => $medical['expiry_date'],
        ];
    }
}
