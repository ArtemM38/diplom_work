<?php

namespace App\Support;

use App\Models\Athlete;
use Carbon\Carbon;

class AthleteDocumentStatus
{
    public const WARNING_DAYS = 3;

    public const DOC_LABELS = [
        'medical' => 'Медицинская справка',
        'insurance' => 'Страховой полис',
    ];

    /**
     * @return array{status: string, days_left: int|null, expiry_date: string|null}
     */
    public static function forDocument(?string $expiryDate): array
    {
        if (! $expiryDate) {
            return ['status' => 'missing', 'days_left' => null, 'expiry_date' => null];
        }

        $expiry = Carbon::parse($expiryDate)->startOfDay();
        $daysLeft = (int) now()->startOfDay()->diffInDays($expiry, false);

        if ($daysLeft < 0) {
            return ['status' => 'expired', 'days_left' => $daysLeft, 'expiry_date' => $expiryDate];
        }

        if ($daysLeft <= self::WARNING_DAYS) {
            return ['status' => 'warning', 'days_left' => $daysLeft, 'expiry_date' => $expiryDate];
        }

        return ['status' => 'ok', 'days_left' => $daysLeft, 'expiry_date' => $expiryDate];
    }

    /**
     * @return array{status: string, days_left: int|null, expiry_date: string|null}
     */
    public static function medicalForAthlete(Athlete $athlete): array
    {
        $medical = $athlete->documents?->firstWhere('type', 'medical');

        return self::forDocument($medical?->expiry_date);
    }

    /**
     * @return array<int, array{document_id: int, type: string, label: string, status: string, days_left: int|null, expiry_date: string}>
     */
    public static function expiringDocumentsForAthlete(Athlete $athlete): array
    {
        $items = [];

        foreach ($athlete->documents ?? [] as $doc) {
            if (! $doc->expiry_date || ! isset(self::DOC_LABELS[$doc->type])) {
                continue;
            }

            $status = self::forDocument($doc->expiry_date);
            if (! in_array($status['status'], ['warning', 'expired'], true)) {
                continue;
            }

            $items[] = [
                'document_id' => $doc->id,
                'type' => $doc->type,
                'label' => self::DOC_LABELS[$doc->type],
                'status' => $status['status'],
                'days_left' => $status['days_left'],
                'expiry_date' => $status['expiry_date'],
            ];
        }

        return $items;
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
