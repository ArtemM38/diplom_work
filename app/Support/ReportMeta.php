<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class ReportMeta
{
    public static function timezoneLabel(): string
    {
        return 'Иркутск (UTC+8)';
    }

    public static function generatedAt(): Carbon
    {
        return now();
    }

    public static function generatedAtFormatted(): string
    {
        return self::generatedAt()->format('d.m.Y H:i') . ' (' . self::timezoneLabel() . ')';
    }

    public static function generatedByName(): string
    {
        return Auth::user()?->name ?? '—';
    }

    /**
     * @return array{generatedAt: Carbon, generatedBy: string, timezoneLabel: string}
     */
    public static function forExport(): array
    {
        return [
            'generatedAt' => self::generatedAt(),
            'generatedBy' => self::generatedByName(),
            'timezoneLabel' => self::timezoneLabel(),
        ];
    }
}
