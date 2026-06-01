<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTimeInterface;

class DateFormatter
{
    public static function toDateString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof CarbonInterface) {
            return $value->format('Y-m-d');
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        $string = (string) $value;

        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $string, $matches)) {
            return $matches[1];
        }

        return Carbon::parse($string)->format('Y-m-d');
    }

    public static function toDisplayDate(mixed $value): ?string
    {
        $date = self::toDateString($value);

        return $date ? Carbon::createFromFormat('Y-m-d', $date)->format('d.m.Y') : null;
    }

    public static function normalizeTimeString(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('H:i:s');
        }

        $string = trim((string) $value);

        if (preg_match('/^(\d{1,2}:\d{2})(:\d{2})?$/', $string, $matches)) {
            return isset($matches[2]) ? $matches[1] . $matches[2] : $matches[1] . ':00';
        }

        if (preg_match('/(\d{2}:\d{2}:\d{2})/', $string, $matches)) {
            return $matches[1];
        }

        return $string;
    }

    public static function toDateTime(mixed $date, mixed $time): Carbon
    {
        $dateString = self::toDateString($date);
        $timeString = self::normalizeTimeString($time);

        return Carbon::parse("{$dateString} {$timeString}", config('app.timezone'));
    }
}
