<?php

namespace App\Support;

use App\Models\Athlete;
use App\Models\Guardian;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class AthleteDocumentVariables
{
    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, string>
     */
    public static function build(Athlete $athlete, array $extra = [], ?User $user = null): array
    {
        $athlete->loadMissing(['guardians.user', 'documents', 'groups', 'user']);

        $guardian = self::primaryGuardian($athlete, $user);
        $guardianCases = self::nameCasesFromFullName($guardian?->full_name);
        $identity = $athlete->documents->firstWhere('type', 'identity');
        $birth = $athlete->birth_date ? Carbon::parse($athlete->birth_date) : null;
        $today = now();

        $athleteFio = self::athleteFio($athlete);
        $athleteFioGen = self::athleteFioInCase($athlete, 'gen');
        $athleteFioDat = self::athleteFioInCase($athlete, 'dat');
        $athleteFioIns = self::athleteFioInCase($athlete, 'ins');
        $birthYear = $birth?->format('Y') ?? '';
        $absenceLines = self::splitLines((string) ($extra['absence_reason'] ?? ''));
        $scheduleLines = self::splitLines((string) ($extra['schedule_description'] ?? ''));

        $periodFrom = self::parseDate($extra['period_from'] ?? null);
        $periodTo = self::parseDate($extra['period_to'] ?? null);

        $father = self::guardianByRelation($athlete, ['отец', 'папа']);
        $mother = self::guardianByRelation($athlete, ['мать', 'мама']);

        return [
            'guardian_name' => $guardian?->full_name ?? '',
            'guardian_name_gen' => $guardianCases['gen'] ?? ($guardian?->full_name ?? ''),
            'guardian_phone' => $guardian?->phone ?? '',
            'guardian_info' => self::guardianInfoLine($guardian),
            'father_info' => self::guardianInfoLine($father),
            'mother_info' => self::guardianInfoLine($mother),
            'athlete_fio' => $athleteFio,
            'athlete_fio_gen' => $athleteFioGen,
            'athlete_fio_dat' => $athleteFioDat,
            'athlete_fio_ins' => $athleteFioIns,
            'athlete_last_name' => $athlete->last_name_nom ?? '',
            'athlete_first_name' => $athlete->first_name_nom ?? '',
            'athlete_middle_name' => $athlete->middle_name_nom ?? '',
            'athlete_fio_birth_year' => $birthYear !== ''
                ? "{$athleteFio}, {$birthYear} г.р."
                : $athleteFio,
            'athlete_fio_gen_birth_year' => $birthYear !== ''
                ? "{$athleteFioGen}, {$birthYear} г.р."
                : $athleteFioGen,
            'athlete_fio_dat_birth_year' => $birthYear !== ''
                ? "{$athleteFioDat}, {$birthYear} г.р."
                : $athleteFioDat,
            'athlete_fio_ins_birth_year' => $birthYear !== ''
                ? "{$athleteFioIns}, {$birthYear} г.р."
                : $athleteFioIns,
            'athlete_birth_date' => DateFormatter::toDisplayDate($athlete->birth_date) ?? '',
            'athlete_birth_formatted' => $birth
                ? sprintf('«%s» %s %s', $birth->format('d'), self::russianMonth($birth), $birth->format('Y'))
                : '',
            'training_start_formatted' => self::formatRussianDate(Carbon::parse($athlete->created_at ?? now())),
            'date_formatted' => sprintf(
                '«%s» %s %s г.',
                $today->format('d'),
                self::russianMonth($today),
                $today->format('Y'),
            ),
            'athlete_birth_day' => $birth?->format('d') ?? '',
            'athlete_birth_month' => $birth ? self::russianMonth($birth) : '',
            'athlete_birth_year' => $birth?->format('Y') ?? '',
            'athlete_address' => $athlete->registration_address ?? '',
            'athlete_phone' => $athlete->phone ?? '',
            'education_place' => self::educationPlace($athlete),
            'school_name' => $athlete->school_name ?? '',
            'school_class' => $athlete->school_class ?? '',
            'school_director_dat' => self::normalizeDirectorDat($athlete->school_director_dat ?? ''),
            'school_recipient_line' => trim(self::normalizeDirectorDat($athlete->school_director_dat ?? '') . ' ' . ($athlete->school_name ?? '')),
            'identity_series' => $identity?->series ?? '',
            'identity_number' => $identity?->number ?? '',
            'identity_issued_by' => $identity?->issued_by ?? '',
            'identity_issue_date' => DateFormatter::toDisplayDate($identity?->issue_date) ?? '',
            'identity_issue_day' => $identity?->issue_date ? Carbon::parse($identity->issue_date)->format('d') : '',
            'identity_issue_month' => $identity?->issue_date ? self::russianMonth(Carbon::parse($identity->issue_date)) : '',
            'identity_issue_year' => $identity?->issue_date ? Carbon::parse($identity->issue_date)->format('Y') : '',
            'date_day' => $today->format('d'),
            'date_month' => self::russianMonth($today),
            'date_year' => $today->format('Y'),
            'date_year_short' => $today->format('y'),
            'period_from_day' => $periodFrom?->format('d') ?? '',
            'period_from_month' => $periodFrom ? self::russianMonth($periodFrom) : '',
            'period_from_year' => $periodFrom?->format('Y') ?? '',
            'period_to_day' => $periodTo?->format('d') ?? '',
            'period_to_month' => $periodTo ? self::russianMonth($periodTo) : '',
            'period_to_year' => $periodTo?->format('Y') ?? '',
            'period_range_text' => ($periodFrom && $periodTo)
                ? sprintf(
                    '«%s» %s %s по «%s» %s %s',
                    $periodFrom->format('d'),
                    self::russianMonth($periodFrom),
                    $periodFrom->format('Y'),
                    $periodTo->format('d'),
                    self::russianMonth($periodTo),
                    $periodTo->format('Y'),
                )
                : '',
            'absence_reason' => $absenceLines[0],
            'absence_reason_cont' => $absenceLines[1],
            'schedule_description' => $scheduleLines[0],
            'schedule_description_cont' => $scheduleLines[1],
            'payer_info' => self::payerInfo($guardian, $athlete),
            'recipient_info' => trim($athleteFio . ($athlete->registration_address ? ', ' . $athlete->registration_address : '')),
            'invoice_header' => sprintf(
                'Счет на оплату от %s %s %s г.',
                $today->format('d'),
                self::russianMonth($today),
                $today->format('Y'),
            ),
            'training_start_date' => DateFormatter::toDisplayDate($athlete->created_at ?? now()) ?? '',
            'organization_name' => 'ИООО БИ Федерация Айкидо',
            'athlete_photo_path' => self::athletePhotoPath($athlete),
            'athlete_photo_storage_key' => $athlete->photo ? ltrim($athlete->photo, '/') : '',
        ];
    }

    private static function athletePhotoPath(Athlete $athlete): string
    {
        if (! $athlete->photo) {
            return '';
        }

        $relative = ltrim($athlete->photo, '/');

        if (Storage::disk('public')->exists($relative)) {
            return Storage::disk('public')->path($relative);
        }

        $legacy = storage_path('app/public/' . $relative);

        return is_file($legacy) ? $legacy : '';
    }

    private static function athleteFioInCase(Athlete $athlete, string $case): string
    {
        $stored = match ($case) {
            'gen' => $athlete->full_name_gen,
            'dat' => $athlete->full_name_dat,
            'ins' => $athlete->full_name_ins,
            default => null,
        };

        if (is_string($stored) && trim($stored) !== '') {
            return trim($stored);
        }

        $cases = RussianNameCases::buildFullNameCases(
            $athlete->last_name_nom ?? '',
            $athlete->first_name_nom ?? '',
            $athlete->middle_name_nom,
        );

        return $cases[$case] ?? self::athleteFio($athlete);
    }

    private static function primaryGuardian(Athlete $athlete, ?User $user): ?Guardian
    {
        if ($user?->guardian) {
            $linked = $athlete->guardians->firstWhere('id', $user->guardian->id);
            if ($linked) {
                return $linked;
            }
        }

        return $athlete->guardians->first();
    }

    /**
     * @param  list<string>  $needles
     */
    private static function guardianByRelation(Athlete $athlete, array $needles): ?Guardian
    {
        return $athlete->guardians->first(function (Guardian $guardian) use ($needles) {
            $relation = mb_strtolower(trim((string) $guardian->relation));

            foreach ($needles as $needle) {
                if (str_contains($relation, $needle)) {
                    return true;
                }
            }

            return false;
        });
    }

    private static function guardianInfoLine(?Guardian $guardian): string
    {
        if (! $guardian) {
            return '';
        }

        $parts = array_filter([
            $guardian->full_name,
            $guardian->phone,
            $guardian->user?->email,
        ]);

        return implode(', ', $parts);
    }

    private static function payerInfo(?Guardian $guardian, Athlete $athlete): string
    {
        $name = $guardian?->full_name ?: self::athleteFio($athlete);
        $phone = $guardian?->phone ?: $athlete->phone;

        return trim(implode(', ', array_filter([$name, $phone])));
    }

    private static function educationPlace(Athlete $athlete): string
    {
        return match ($athlete->occupation_type) {
            'study' => trim(implode(', ', array_filter([
                $athlete->school_name,
                $athlete->school_class ? 'класс ' . $athlete->school_class : null,
            ]))),
            'kindergarten' => (string) ($athlete->kindergarten_name ?? ''),
            'work' => trim(implode(', ', array_filter([
                $athlete->work_place,
                $athlete->work_position,
            ]))),
            default => '',
        };
    }

    private static function athleteFio(Athlete $athlete): string
    {
        return trim(implode(' ', array_filter([
            $athlete->last_name_nom,
            $athlete->first_name_nom,
            $athlete->middle_name_nom,
        ])));
    }

    /**
     * @return array{gen: string, dat: string, ins: string}
     */
    private static function nameCasesFromFullName(?string $fullName): array
    {
        $fullName = trim((string) $fullName);
        if ($fullName === '') {
            return ['gen' => '', 'dat' => '', 'ins' => ''];
        }

        $parts = preg_split('/\s+/u', $fullName, 3) ?: [];

        return RussianNameCases::buildFullNameCases(
            $parts[0] ?? '',
            $parts[1] ?? '',
            $parts[2] ?? null,
        );
    }

    private static function parseDate(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse($value);
    }

    private static function normalizeDirectorDat(string $value): string
    {
        return trim(preg_replace('/^директору\s+/ui', '', $value) ?? $value);
    }

    private static function formatRussianDate(Carbon $date): string
    {
        return sprintf('«%s» %s %s', $date->format('d'), self::russianMonth($date), $date->format('Y'));
    }

    private static function russianMonth(Carbon $date): string
    {
        $months = [
            1 => 'января',
            2 => 'февраля',
            3 => 'марта',
            4 => 'апреля',
            5 => 'мая',
            6 => 'июня',
            7 => 'июля',
            8 => 'августа',
            9 => 'сентября',
            10 => 'октября',
            11 => 'ноября',
            12 => 'декабря',
        ];

        return $months[(int) $date->format('n')] ?? '';
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function splitLines(string $text, int $maxLen = 90): array
    {
        $text = trim($text);
        if ($text === '') {
            return ['', ''];
        }

        if (mb_strlen($text) <= $maxLen) {
            return [$text, ''];
        }

        $splitAt = mb_strpos($text, ' ', max(20, $maxLen - 25));
        if ($splitAt === false) {
            $splitAt = $maxLen;
        }

        return [
            trim(mb_substr($text, 0, $splitAt)),
            trim(mb_substr($text, $splitAt)),
        ];
    }
}
