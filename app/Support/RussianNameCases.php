<?php

namespace App\Support;

class RussianNameCases
{
    public static function buildFullNameCases(string $lastName, string $firstName, ?string $middleName = null): array
    {
        $middleName = trim((string) $middleName);

        $parts = [
            'last' => trim($lastName),
            'first' => trim($firstName),
            'middle' => $middleName !== '' ? $middleName : null,
        ];

        return [
            'gen' => self::joinParts([
                self::toGenitiveLastName($parts['last']),
                self::toGenitiveFirstName($parts['first']),
                self::toGenitiveMiddleName($parts['middle']),
            ]),
            'dat' => self::joinParts([
                self::toDativeLastName($parts['last']),
                self::toDativeFirstName($parts['first']),
                self::toDativeMiddleName($parts['middle']),
            ]),
            'ins' => self::joinParts([
                self::toInstrumentalLastName($parts['last']),
                self::toInstrumentalFirstName($parts['first']),
                self::toInstrumentalMiddleName($parts['middle']),
            ]),
        ];
    }

    private static function joinParts(array $parts): string
    {
        return trim(implode(' ', array_filter($parts)));
    }

    private static function toGenitiveLastName(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        return self::applyLastNameRule($value, 'gen');
    }

    private static function toDativeLastName(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        return self::applyLastNameRule($value, 'dat');
    }

    private static function toInstrumentalLastName(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        return self::applyLastNameRule($value, 'ins');
    }

    private static function applyLastNameRule(string $value, string $case): string
    {
        $lower = mb_strtolower($value);

        if (preg_match('/(ова|ева|ина|ына)$/u', $lower)) {
            return match ($case) {
                'gen' => $value . 'ой',
                'dat' => $value . 'ой',
                'ins' => $value . 'ой',
                default => $value,
            };
        }

        if (preg_match('/(ов|ев|ин|ын)$/u', $lower)) {
            return match ($case) {
                'gen' => $value . 'а',
                'dat' => $value . 'у',
                'ins' => $value . 'ым',
                default => $value,
            };
        }

        if (preg_match('/а$/u', $lower)) {
            return match ($case) {
                'gen' => mb_substr($value, 0, -1) . 'ы',
                'dat' => mb_substr($value, 0, -1) . 'е',
                'ins' => mb_substr($value, 0, -1) . 'ой',
                default => $value,
            };
        }

        return match ($case) {
            'gen' => $value . 'а',
            'dat' => $value . 'у',
            'ins' => $value . 'ом',
            default => $value,
        };
    }

    private static function toGenitiveFirstName(?string $value): ?string
    {
        return self::inflectFirstName($value, 'gen');
    }

    private static function toDativeFirstName(?string $value): ?string
    {
        return self::inflectFirstName($value, 'dat');
    }

    private static function toInstrumentalFirstName(?string $value): ?string
    {
        return self::inflectFirstName($value, 'ins');
    }

    private static function inflectFirstName(?string $value, string $case): ?string
    {
        if (!$value) {
            return null;
        }

        $lower = mb_strtolower($value);

        if (preg_match('/й$/u', $lower)) {
            $base = mb_substr($value, 0, -1);
            return match ($case) {
                'gen' => $base . 'я',
                'dat' => $base . 'ю',
                'ins' => $base . 'ем',
                default => $value,
            };
        }

        if (preg_match('/а$/u', $lower)) {
            $base = mb_substr($value, 0, -1);
            return match ($case) {
                'gen' => $base . 'ы',
                'dat' => $base . 'е',
                'ins' => $base . 'ой',
                default => $value,
            };
        }

        if (preg_match('/я$/u', $lower)) {
            $base = mb_substr($value, 0, -1);
            return match ($case) {
                'gen' => $base . 'и',
                'dat' => $base . 'е',
                'ins' => $base . 'ей',
                default => $value,
            };
        }

        return match ($case) {
            'gen' => $value . 'а',
            'dat' => $value . 'у',
            'ins' => $value . 'ом',
            default => $value,
        };
    }

    private static function toGenitiveMiddleName(?string $value): ?string
    {
        return self::inflectMiddleName($value, 'gen');
    }

    private static function toDativeMiddleName(?string $value): ?string
    {
        return self::inflectMiddleName($value, 'dat');
    }

    private static function toInstrumentalMiddleName(?string $value): ?string
    {
        return self::inflectMiddleName($value, 'ins');
    }

    private static function inflectMiddleName(?string $value, string $case): ?string
    {
        if (!$value) {
            return null;
        }

        $lower = mb_strtolower($value);

        if (preg_match('/ич$/u', $lower)) {
            return match ($case) {
                'gen' => $value . 'а',
                'dat' => $value . 'у',
                'ins' => $value . 'ем',
                default => $value,
            };
        }

        if (preg_match('/на$/u', $lower)) {
            $base = mb_substr($value, 0, -1);
            return match ($case) {
                'gen' => $base . 'ы',
                'dat' => $base . 'е',
                'ins' => $base . 'ой',
                default => $value,
            };
        }

        return $value;
    }
}
