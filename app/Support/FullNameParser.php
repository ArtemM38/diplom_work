<?php

namespace App\Support;

class FullNameParser
{
    /**
     * @return array{last_name_nom: string, first_name_nom: string, middle_name_nom: string}
     */
    public static function parse(string $fullName): array
    {
        $parts = preg_split('/\s+/u', trim($fullName), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return [
            'last_name_nom' => $parts[0] ?? '',
            'first_name_nom' => $parts[1] ?? '',
            'middle_name_nom' => implode(' ', array_slice($parts, 2)),
        ];
    }
}
