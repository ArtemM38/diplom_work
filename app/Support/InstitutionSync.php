<?php

namespace App\Support;

use App\Models\Institution;

class InstitutionSync
{
    /**
     * @param  array<string, mixed>  $validated
     */
    public static function resolveId(array $validated): ?int
    {
        return match ($validated['occupation_type'] ?? null) {
            'study' => self::resolveStudy(
                trim((string) ($validated['school_name'] ?? '')),
                trim((string) ($validated['school_director_dat'] ?? '')),
            ),
            'kindergarten' => self::resolveSimple('kindergarten', trim((string) ($validated['kindergarten_name'] ?? ''))),
            'work' => self::resolveSimple('work', trim((string) ($validated['work_place'] ?? ''))),
            default => null,
        };
    }

    private static function resolveStudy(string $name, string $director): ?int
    {
        if ($name === '') {
            return null;
        }

        $institution = Institution::query()
            ->where('type', 'study')
            ->where('name', $name)
            ->first();

        if ($institution) {
            if ($director !== '' && empty($institution->director_dat)) {
                $institution->update(['director_dat' => $director]);
            }

            return $institution->id;
        }

        return Institution::create([
            'type' => 'study',
            'name' => $name,
            'director_dat' => $director !== '' ? $director : null,
        ])->id;
    }

    private static function resolveSimple(string $type, string $name): ?int
    {
        if ($name === '') {
            return null;
        }

        $institution = Institution::query()
            ->where('type', $type)
            ->where('name', $name)
            ->first();

        if ($institution) {
            return $institution->id;
        }

        return Institution::create([
            'type' => $type,
            'name' => $name,
        ])->id;
    }
}
