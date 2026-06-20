<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use ZipArchive;

class DocxTemplateFiller
{
    /**
     * @param  array<string, string>  $variables
     * @param  array<string, mixed>  $config
     */
    public function fill(string $sourcePath, array $variables, array $config): string
    {
        $temp = tempnam(sys_get_temp_dir(), 'docx-fill-');
        $target = $temp . '.docx';
        if (file_exists($temp)) {
            unlink($temp);
        }
        copy($sourcePath, $target);

        $zip = new ZipArchive();
        if ($zip->open($target) !== true) {
            throw new \RuntimeException('Не удалось открыть шаблон документа для заполнения.');
        }

        $preserveUnderscores = (bool) ($config['preserve_underscore_lines'] ?? false);
        $photoPath = trim((string) ($variables['athlete_photo_path'] ?? ''));
        $tempPhotoPath = null;

        if ($photoPath === '' && ($variables['athlete_photo_storage_key'] ?? '') !== '') {
            $storageKey = ltrim((string) $variables['athlete_photo_storage_key'], '/');
            if (Storage::disk('public')->exists($storageKey)) {
                $tempPhotoPath = tempnam(sys_get_temp_dir(), 'athlete-photo-');
                file_put_contents($tempPhotoPath, Storage::disk('public')->get($storageKey));
                $photoPath = $tempPhotoPath;
            }
        }

        foreach (['word/document.xml', 'word/header1.xml', 'word/header2.xml', 'word/footer1.xml', 'word/footer2.xml'] as $part) {
            $xml = $zip->getFromName($part);
            if ($xml === false) {
                continue;
            }

            foreach ($config['rules'] ?? [] as $rule) {
                $xml = $this->applyRule($xml, $rule, $variables);
            }

            if (($config['fit_single_page'] ?? false) && $part === 'word/document.xml') {
                $xml = $this->fitSinglePage($xml);
            }

            if (! $preserveUnderscores) {
                $xml = $this->polishDocumentXml($xml);
            }

            $zip->addFromString($part, $xml);
        }

        if ($photoPath !== '' && isset($config['photo'])) {
            $this->applyAthletePhoto($zip, $photoPath, $config['photo']);
        }

        $zip->close();

        if ($tempPhotoPath !== null && is_file($tempPhotoPath)) {
            @unlink($tempPhotoPath);
        }

        return $target;
    }

    /**
     * @param  array<string, mixed>  $rule
     * @param  array<string, string>  $variables
     */
    private function applyRule(string $xml, array $rule, array $variables): string
    {
        $type = $rule['type'] ?? 'paragraph_before_label';
        $value = trim((string) ($variables[$rule['var'] ?? ''] ?? ''));
        $multiValueTypes = ['slots_after_anchor', 'underscores_in_paragraph', 'period_range', 'period_range_formatted', 'date_russian', 'birth_date_line', 'table_cell_after_label', 'document_date_footer', 'remove_paragraph_containing'];

        if ($value === '' && ! in_array($type, $multiValueTypes, true)) {
            return $xml;
        }

        return match ($type) {
            'paragraph_before_label' => $this->fillParagraphBeforeLabel($xml, (string) $rule['anchor'], $value),
            'slots_after_anchor' => $this->fillSlotsAfterAnchor(
                $xml,
                (string) $rule['anchor'],
                $rule['vars'] ?? [$rule['var'] ?? ''],
                $variables,
                (bool) ($rule['all'] ?? false),
                (bool) ($rule['clear_remaining'] ?? false),
                (bool) ($rule['last'] ?? false),
                (string) ($rule['must_contain'] ?? ''),
                (bool) ($rule['clear_next_underscore_paragraph'] ?? false),
                (bool) ($rule['strip_leading_underscores'] ?? false),
            ),
            'table_cell_after_label' => $this->fillTableCellAfterLabel($xml, (string) $rule['anchor'], $value),
            'line_after_label' => $this->fillLineAfterLabel($xml, (string) $rule['anchor'], $value),
            'underscores_in_paragraph' => $this->fillUnderscoresInParagraph($xml, (string) $rule['anchor'], $rule['vars'] ?? [], $variables, (bool) ($rule['all'] ?? false)),
            'birth_date_line' => $this->fillBirthDateLine($xml, (string) $rule['anchor'], $variables, $rule['keys'] ?? ['day' => 'athlete_birth_day', 'month' => 'athlete_birth_month', 'year' => 'athlete_birth_year']),
            'date_russian' => $this->fillRussianDateLine($xml, $variables, (string) ($rule['which'] ?? 'all')),
            'period_range' => $this->fillPeriodRange($xml, $variables),
            'period_range_formatted' => $this->fillPeriodRangeFormatted($xml, $variables),
            'prefix_value_line' => $this->fillPrefixValueLine(
                $xml,
                (string) ($rule['prefix'] ?? $rule['anchor'] ?? ''),
                $value,
                (bool) ($rule['keep_trailing_underscores'] ?? false),
            ),
            'remove_paragraph_containing' => $this->removeParagraphsContaining(
                $xml,
                (string) ($rule['anchor'] ?? ''),
                (bool) ($rule['only_parenthetical'] ?? true),
            ),
            'document_date_footer' => $this->fillDocumentDateFooter(
                $xml,
                $variables,
                (bool) ($rule['all'] ?? false),
                (bool) ($rule['last'] ?? true),
                (string) ($rule['must_contain'] ?? ''),
            ),
            default => $xml,
        };
    }

    private function fillParagraphBeforeLabel(string $xml, string $anchor, string $value): string
    {
        $paragraphs = $this->collectParagraphs($xml);

        $targetIndex = null;
        foreach ($paragraphs as $index => $paragraph) {
            if ($this->paragraphContains($paragraph, $anchor)) {
                $targetIndex = $index;
                break;
            }
        }

        if ($targetIndex === null) {
            return $xml;
        }

        for ($i = $targetIndex - 1; $i >= 0; $i--) {
            if ($this->paragraphHasUnderscores($paragraphs[$i])) {
                $paragraphs[$i] = $this->setParagraphPlainText($paragraphs[$i], $value);
                break;
            }

            if ($this->paragraphPlainText($paragraphs[$i]) !== '') {
                break;
            }
        }

        for ($i = $targetIndex + 1; $i < count($paragraphs); $i++) {
            $text = $this->paragraphPlainText($paragraphs[$i]);

            if ($text === '') {
                continue;
            }

            if ($this->paragraphHasUnderscores($paragraphs[$i]) && ! str_starts_with($text, '(')) {
                $paragraphs[$i] = $this->setParagraphPlainText($paragraphs[$i], '');
                break;
            }

            break;
        }

        return $this->replaceParagraphs($xml, $paragraphs);
    }

    private function removeParagraphsContaining(string $xml, string $anchor, bool $onlyParenthetical = true): string
    {
        if ($anchor === '') {
            return $xml;
        }

        $paragraphs = $this->collectParagraphs($xml);
        $index = 0;

        return preg_replace_callback('/<w:p\b.*?<\/w:p>/us', function () use ($paragraphs, $anchor, $onlyParenthetical, &$index) {
            $paragraph = $paragraphs[$index++] ?? '';
            if ($paragraph === '') {
                return '';
            }

            $plain = trim($this->paragraphPlainText($paragraph));
            if ($plain === '' || ! str_contains($plain, $anchor)) {
                return $paragraph;
            }

            if ($onlyParenthetical) {
                $withoutLabel = trim(preg_replace('/\([^)]*' . preg_quote($anchor, '/') . '[^)]*\)/u', '', $plain) ?? $plain);

                if ($withoutLabel !== '' && ! preg_match('/^[\s_]+$/u', $withoutLabel)) {
                    return $paragraph;
                }
            }

            return '';
        }, $xml) ?? $xml;
    }

    private function fitSinglePage(string $xml): string
    {
        $xml = $this->removeEmptyParagraphs($xml);

        $xml = preg_replace(
            '/<w:pgMar[^>]+\/>/',
            '<w:pgMar w:top="567" w:right="850" w:bottom="567" w:left="850" w:header="397" w:footer="397" w:gutter="0"/>',
            $xml,
        ) ?? $xml;

        return preg_replace_callback('/<w:p\b.*?<\/w:p>/us', function (array $match): string {
            $paragraph = $match[0];
            if (! preg_match('/<w:pPr>.*?<\/w:pPr>/us', $paragraph, $pPrMatch)) {
                return preg_replace(
                    '/^(<w:p[^>]*>)/',
                    '$1<w:pPr><w:spacing w:before="0" w:after="40" w:line="240" w:lineRule="auto"/></w:pPr>',
                    $paragraph,
                    1,
                ) ?? $paragraph;
            }

            $pPr = $pPrMatch[0];
            if (preg_match('/<w:spacing[^>]+\/>/u', $pPr)) {
                $newPPr = preg_replace(
                    '/<w:spacing[^>]+\/>/u',
                    '<w:spacing w:before="0" w:after="40" w:line="240" w:lineRule="auto"/>',
                    $pPr,
                ) ?? $pPr;
            } else {
                $newPPr = str_replace('</w:pPr>', '<w:spacing w:before="0" w:after="40" w:line="240" w:lineRule="auto"/></w:pPr>', $pPr);
            }

            return str_replace($pPr, $newPPr, $paragraph);
        }, $xml) ?? $xml;
    }

    private function removeEmptyParagraphs(string $xml): string
    {
        $paragraphs = $this->collectParagraphs($xml);
        $index = 0;

        return preg_replace_callback('/<w:p\b.*?<\/w:p>/us', function () use ($paragraphs, &$index) {
            $paragraph = $paragraphs[$index++] ?? '';
            $plain = trim($this->paragraphPlainText($paragraph));

            return $plain === '' ? '' : $paragraph;
        }, $xml) ?? $xml;
    }

    private function fillPrefixValueLine(string $xml, string $prefix, string $value, bool $keepTrailingUnderscores = false): string
    {
        $value = trim($value);
        if ($prefix === '' || $value === '') {
            return $xml;
        }

        $paragraphs = $this->collectParagraphs($xml);

        foreach ($paragraphs as $index => $paragraph) {
            $plain = $this->paragraphPlainText($paragraph);
            $prefixPos = mb_stripos($plain, $prefix);
            if ($prefixPos === false) {
                continue;
            }

            $linePrefix = mb_substr($plain, 0, $prefixPos + mb_strlen($prefix));
            $afterPrefix = mb_substr($plain, $prefixPos + mb_strlen($prefix));
            $trailingUnderscores = $keepTrailingUnderscores
                ? (preg_replace('/[^_]/u', '', $afterPrefix) ?? '')
                : '';

            $newText = rtrim($linePrefix) . ' ' . $value . $trailingUnderscores;
            $paragraphs[$index] = $this->setParagraphPlainText($paragraph, $newText);

            return $this->replaceParagraphs($xml, $paragraphs);
        }

        return $xml;
    }

    /**
     * @param  list<string>  $varKeys
     * @param  array<string, string>  $variables
     */
    private function fillTableCellAfterLabel(string $xml, string $label, string $value): string
    {
        $pattern = '/(' . preg_quote($label, '/') . '[\s\S]*?<\/w:tc>[\s\S]*?<w:tc[^>]*>[\s\S]*?<w:t[^>]*>)([^<]*)(<\/w:t>)/u';

        return preg_replace(
            $pattern,
            '$1' . $this->escapeXml($value) . '$3',
            $xml,
            1,
        ) ?? $xml;
    }

    private function fillSlotsAfterAnchor(
        string $xml,
        string $anchor,
        array $varKeys,
        array $variables,
        bool $all = false,
        bool $clearRemaining = false,
        bool $last = false,
        string $mustContain = '',
        bool $clearNextUnderscoreParagraph = false,
        bool $stripLeadingUnderscores = false,
    ): string {
        $values = array_values(array_filter(array_map(
            fn (string $key) => trim((string) ($variables[$key] ?? '')),
            $varKeys,
        )));

        if ($values === []) {
            return $xml;
        }

        $paragraphs = $this->collectParagraphs($xml);
        $targetIndexes = [];

        foreach ($paragraphs as $index => $paragraph) {
            if (! $this->paragraphContains($paragraph, $anchor)) {
                continue;
            }

            if ($mustContain !== '' && ! $this->paragraphContains($paragraph, $mustContain)) {
                continue;
            }

            $targetIndexes[] = $index;
        }

        if ($targetIndexes === []) {
            return $xml;
        }

        if ($last) {
            $targetIndexes = [(int) end($targetIndexes)];
        } elseif (! $all) {
            $targetIndexes = [$targetIndexes[0]];
        }

        foreach ($targetIndexes as $index) {
            if ($stripLeadingUnderscores) {
                $paragraphs[$index] = $this->stripUnderscoreRunsBeforeAnchor($paragraphs[$index], $anchor);
            }

            $paragraphs[$index] = $this->replaceSlotsAfterAnchor($paragraphs[$index], $anchor, $values, $clearRemaining);

            if ($clearRemaining && str_contains(mb_strtolower($anchor), 'в связи с')) {
                $paragraphs[$index] = $this->normalizeReasonParagraphTail($paragraphs[$index]);
            }

            if ($clearNextUnderscoreParagraph && isset($paragraphs[$index + 1]) && $this->paragraphHasUnderscores($paragraphs[$index + 1])) {
                $paragraphs[$index + 1] = $this->setParagraphPlainText($paragraphs[$index + 1], '');
            }
        }

        return $this->replaceParagraphs($xml, $paragraphs);
    }

    private function normalizeReasonParagraphTail(string $paragraph): string
    {
        $plain = $this->paragraphPlainText($paragraph);
        if (! str_contains($plain, 'в связи с')) {
            return $paragraph;
        }

        $plain = preg_replace('/_{2,}/u', '', $plain) ?? $plain;
        $plain = preg_replace('/\s+\./u', '.', $plain) ?? $plain;

        return $this->setParagraphPlainText($paragraph, $plain);
    }

    private function fillLineAfterLabel(string $xml, string $label, string $value): string
    {
        $paragraphs = $this->collectParagraphs($xml);

        foreach ($paragraphs as $index => $paragraph) {
            if (! $this->paragraphContains($paragraph, $label)) {
                continue;
            }

            for ($next = $index + 1; $next < count($paragraphs); $next++) {
                $text = $this->paragraphPlainText($paragraphs[$next]);

                if ($text === '') {
                    $paragraphs[$next] = $this->setParagraphPlainText($paragraphs[$next], $value);

                    return $this->replaceParagraphs($xml, $paragraphs);
                }

                if (str_starts_with($text, '(')) {
                    continue;
                }

                if ($this->paragraphHasUnderscores($paragraphs[$next])) {
                    $paragraphs[$next] = $this->setParagraphPlainText($paragraphs[$next], $value);

                    return $this->replaceParagraphs($xml, $paragraphs);
                }

                break;
            }
        }

        return $xml;
    }

    /**
     * @param  list<string>  $varKeys
     * @param  array<string, string>  $variables
     */
    private function fillUnderscoresInParagraph(string $xml, string $anchor, array $varKeys, array $variables, bool $all = false): string
    {
        $paragraphs = $this->collectParagraphs($xml);

        $changed = false;
        foreach ($paragraphs as $index => $paragraph) {
            if (! $this->paragraphContains($paragraph, $anchor)) {
                continue;
            }

            $values = array_values(array_filter(array_map(
                fn (string $key) => trim((string) ($variables[$key] ?? '')),
                $varKeys,
            )));

            if ($values === []) {
                continue;
            }

            $anchorEnd = $this->anchorEndOffset($paragraph, $anchor);
            $paragraphs[$index] = $this->fillUnderscoreSlotsInParagraph($paragraph, $values, $anchorEnd, null, true);
            $changed = true;

            if (! $all) {
                break;
            }
        }

        return $changed ? $this->replaceParagraphs($xml, $paragraphs) : $xml;
    }

    /**
     * @param  array<string, string>  $variables
     * @param  array<string, string>  $keys
     */
    private function fillBirthDateLine(string $xml, string $anchor, array $variables, array $keys): string
    {
        $day = trim((string) ($variables[$keys['day'] ?? 'athlete_birth_day'] ?? ''));
        $month = trim((string) ($variables[$keys['month'] ?? 'athlete_birth_month'] ?? ''));
        $year = trim((string) ($variables[$keys['year'] ?? 'athlete_birth_year'] ?? ''));

        if ($day === '' && $month === '' && $year === '') {
            return $xml;
        }

        $paragraphs = $this->collectParagraphs($xml);

        foreach ($paragraphs as $index => $paragraph) {
            if (! $this->paragraphContains($paragraph, $anchor)) {
                continue;
            }

            $slot = 0;
            $values = [$day, $month, $year];
            $paragraphs[$index] = preg_replace_callback(
                '/<w:t([^>]*)>([^<]*)<\/w:t>/u',
                function (array $match) use ($values, &$slot) {
                    $text = html_entity_decode($match[2], ENT_XML1, 'UTF-8');
                    if (! preg_match('/_{1,}/u', $text)) {
                        return $match[0];
                    }

                    $value = $values[$slot] ?? '';
                    $slot++;
                    if ($value === '') {
                        return $match[0];
                    }

                    return '<w:t' . $match[1] . '>' . $this->escapeXml($this->substituteUnderscoreText($text, $value)) . '</w:t>';
                },
                $paragraph,
            ) ?? $paragraph;

            return $this->replaceParagraphs($xml, $paragraphs);
        }

        return $xml;
    }

    /**
     * @param  array<string, string>  $variables
     */
    private function fillRussianDateLine(string $xml, array $variables, string $which = 'all'): string
    {
        $day = trim((string) ($variables['date_day'] ?? ''));
        $month = trim((string) ($variables['date_month'] ?? ''));
        $yearShort = trim((string) ($variables['date_year_short'] ?? mb_substr((string) ($variables['date_year'] ?? ''), -2)));

        if ($day === '' && $month === '' && $yearShort === '') {
            return $xml;
        }

        $paragraphs = $this->collectParagraphs($xml);
        $targets = [];

        foreach ($paragraphs as $index => $paragraph) {
            $plain = $this->paragraphPlainText($paragraph);
            if (! str_contains($plain, '«') || ! preg_match('/(?:20|202)_{1,}\s*г/u', $plain)) {
                continue;
            }

            if (preg_match('/подпись|расшифровка/i', $plain)) {
                continue;
            }

            $targets[] = $index;
        }

        if ($targets === []) {
            return $xml;
        }

        if ($which === 'last') {
            $targets = [end($targets)];
        }

        foreach ($targets as $index) {
            $paragraphs[$index] = $this->fillUnderscoreSlotsInParagraph(
                $paragraphs[$index],
                [$day, $month, $yearShort],
            );
        }

        return $this->replaceParagraphs($xml, $paragraphs);
    }

    /**
     * @param  array<string, string>  $variables
     */
    private function fillPeriodRange(string $xml, array $variables): string
    {
        $slots = [
            $variables['period_from_day'] ?? '',
            $variables['period_from_month'] ?? '',
            $variables['period_from_year'] ?? '',
            $variables['period_to_day'] ?? '',
            $variables['period_to_month'] ?? '',
            $variables['period_to_year'] ?? '',
        ];

        if (implode('', array_map('trim', $slots)) === '') {
            return $xml;
        }

        $paragraphs = $this->collectParagraphs($xml);

        foreach ($paragraphs as $index => $paragraph) {
            if (! $this->paragraphContains($paragraph, 'на период с')) {
                continue;
            }

            $anchorEnd = $this->anchorEndOffset($paragraph, 'на период с');
            $stopBefore = $this->anchorStartOffset($paragraph, 'в связи с')
                ?? $this->anchorStartOffset($paragraph, 'так как');

            $paragraphs[$index] = $this->fillUnderscoreSlotsInParagraph(
                $paragraph,
                array_map('trim', $slots),
                $anchorEnd,
                $stopBefore,
            );

            return $this->replaceParagraphs($xml, $paragraphs);
        }

        return $xml;
    }

    /**
     * @param  array<string, string>  $variables
     */
    private function fillPeriodRangeFormatted(string $xml, array $variables): string
    {
        $periodText = trim((string) ($variables['period_range_text'] ?? ''));
        if ($periodText === '') {
            return $xml;
        }

        $paragraphs = $this->collectParagraphs($xml);

        foreach ($paragraphs as $index => $paragraph) {
            if (! $this->paragraphContains($paragraph, 'на период с')) {
                continue;
            }

            $plain = $this->paragraphPlainText($paragraph);
            $newPlain = preg_replace(
                '/на период с\s*«[^»]*»[^,]*?(?:20)?[^,]*?по\s*«[^»]*»[^,]*?(?:20)?[^,]*?,/ui',
                'на период с ' . $periodText . ',',
                $plain,
                1,
            );

            if (! is_string($newPlain) || $newPlain === $plain) {
                continue;
            }

            $paragraphs[$index] = $this->setParagraphPlainText($paragraph, $newPlain);

            return $this->replaceParagraphs($xml, $paragraphs);
        }

        return $xml;
    }

    /**
     * @param  list<string>  $values
     */
    private function fillUnderscoreSlotsInParagraph(
        string $paragraph,
        array $values,
        int $startAfterOffset = 0,
        ?int $stopBeforeOffset = null,
        bool $leadingSpaceAfterAnchor = false,
    ): string {
        $plainOffset = 0;
        $valueIndex = 0;
        $needsLeadingSpace = $leadingSpaceAfterAnchor;

        return preg_replace_callback(
            '/<w:t([^>]*)>([^<]*)<\/w:t>/u',
            function (array $match) use ($values, $startAfterOffset, $stopBeforeOffset, $leadingSpaceAfterAnchor, &$plainOffset, &$valueIndex, &$needsLeadingSpace) {
                $text = html_entity_decode($match[2], ENT_XML1, 'UTF-8');
                $runStart = $plainOffset;
                $runEnd = $plainOffset + mb_strlen($text);
                $plainOffset = $runEnd;

                if ($runEnd <= $startAfterOffset || ! preg_match('/_{1,}/u', $text)) {
                    return $match[0];
                }

                if ($stopBeforeOffset !== null && $runStart >= $stopBeforeOffset) {
                    return $match[0];
                }

                while ($valueIndex < count($values) && preg_match('/_{1,}/u', $text, $underscoreMatch, PREG_OFFSET_CAPTURE)) {
                    $underscoreStart = $underscoreMatch[0][1];
                    $underscoreGlobalStart = $runStart + mb_strlen(mb_substr($text, 0, $underscoreStart));

                    if ($underscoreGlobalStart < $startAfterOffset) {
                        $text = mb_substr($text, 0, $underscoreStart)
                            . mb_substr($text, $underscoreStart + mb_strlen($underscoreMatch[0][0]));
                        continue;
                    }

                    if ($stopBeforeOffset !== null && $underscoreGlobalStart >= $stopBeforeOffset) {
                        break;
                    }

                    $value = trim($values[$valueIndex++]);
                    if ($value === '') {
                        break;
                    }

                    if ($needsLeadingSpace) {
                        $value = $this->leadingSpacedValue($value, true);
                        $needsLeadingSpace = false;
                    }

                    $text = $this->replaceNextUnderscoreSlot($text, $value);
                }

                return '<w:t' . $match[1] . '>' . $this->escapeXml($text) . '</w:t>';
            },
            $paragraph,
        ) ?? $paragraph;
    }

    private function anchorEndOffset(string $paragraph, string $anchor): int
    {
        $plain = $this->paragraphPlainText($paragraph);
        $pos = mb_stripos($plain, $anchor);

        return $pos === false ? 0 : $pos + mb_strlen($anchor);
    }

    private function anchorStartOffset(string $paragraph, string $anchor): ?int
    {
        $plain = $this->paragraphPlainText($paragraph);
        $pos = mb_stripos($plain, $anchor);

        return $pos === false ? null : $pos;
    }

    /**
     * @param  list<string>  $values
     */
    private function replaceSlotsAfterAnchor(string $paragraph, string $anchor, array $values, bool $clearRemainingUnderscores = false): string
    {
        $plain = $this->paragraphPlainText($paragraph);
        $anchorPos = mb_stripos($plain, $anchor);
        if ($anchorPos === false) {
            return $paragraph;
        }

        $anchorEnd = $anchorPos + mb_strlen($anchor);
        $plainOffset = 0;
        $valueIndex = 0;
        $needsLeadingSpace = ! preg_match('/^\s/u', mb_substr($plain, $anchorEnd, 8));
        $passedOpeningParen = false;
        $respectParenWhenClearing = str_contains(mb_strtolower($anchor), 'дана')
            || str_contains(mb_strtolower($anchor), 'ребенк');

        $paragraph = preg_replace_callback(
            '/<w:t([^>]*)>([^<]*)<\/w:t>/u',
            function (array $match) use ($anchor, $anchorEnd, &$plainOffset, $values, &$valueIndex, $clearRemainingUnderscores, &$needsLeadingSpace, &$passedOpeningParen, $respectParenWhenClearing) {
                $text = html_entity_decode($match[2], ENT_XML1, 'UTF-8');
                $runStart = $plainOffset;
                $plainOffset += mb_strlen($text);

                if (str_contains($text, '(')) {
                    $passedOpeningParen = true;
                }

                if ($valueIndex >= count($values)) {
                    if ($clearRemainingUnderscores && $plainOffset > $anchorEnd && (! $respectParenWhenClearing || ! $passedOpeningParen)) {
                        if (preg_match('/^[\s_]+$/u', $text)) {
                            return str_contains($text, '_') ? '' : $match[0];
                        }

                        if (preg_match('/_/u', $text)) {
                            $cleaned = $this->stripUnderscores($text);

                            return $cleaned === ''
                                ? ''
                                : '<w:t' . $match[1] . '>' . $this->escapeXml($cleaned) . '</w:t>';
                        }
                    }

                    return $match[0];
                }

                $anchorInRun = mb_stripos($text, $anchor);
                if ($anchorInRun !== false) {
                    $before = mb_substr($text, 0, $anchorInRun + mb_strlen($anchor));
                    $after = mb_substr($text, $anchorInRun + mb_strlen($anchor));

                    while ($valueIndex < count($values) && preg_match('/_{1,}/u', $after)) {
                        $nextValue = $this->leadingSpacedValue($values[$valueIndex++], $needsLeadingSpace);
                        $needsLeadingSpace = true;
                        $after = $this->replaceNextUnderscoreSlot($after, $nextValue);
                    }

                    if ($clearRemainingUnderscores) {
                        $after = $this->stripUnderscores($after);
                    }

                    return '<w:t' . $match[1] . '>' . $this->escapeXml($before . $after) . '</w:t>';
                }

                if ($plainOffset <= $anchorEnd || ! preg_match('/_{1,}/u', $text)) {
                    if ($needsLeadingSpace && $runStart >= $anchorEnd && preg_match('/^_{1,}/u', ltrim($text))) {
                        $needsLeadingSpace = false;
                    }

                    return $match[0];
                }

                while ($valueIndex < count($values) && preg_match('/_{1,}/u', $text)) {
                    $nextValue = $this->leadingSpacedValue($values[$valueIndex++], $needsLeadingSpace);
                    $needsLeadingSpace = true;
                    $text = $this->replaceNextUnderscoreSlot($text, $nextValue);
                }

                if ($clearRemainingUnderscores) {
                    $text = $this->stripUnderscores($text);
                }

                return '<w:t' . $match[1] . '>' . $this->escapeXml($text) . '</w:t>';
            },
            $paragraph,
        ) ?? $paragraph;

        return $paragraph;
    }

    /**
     * @param  array<string, string>  $variables
     */
    private function fillDocumentDateFooter(
        string $xml,
        array $variables,
        bool $all = false,
        bool $last = true,
        string $mustContain = '',
    ): string {
        $day = trim((string) ($variables['date_day'] ?? ''));
        $month = trim((string) ($variables['date_month'] ?? ''));
        $yearShort = trim((string) ($variables['date_year_short'] ?? ''));

        if ($day === '' || $month === '' || $yearShort === '') {
            return $xml;
        }

        $formatted = sprintf('«%s» %s %s г.', $day, $month, '20' . $yearShort);
        $paragraphs = $this->collectParagraphs($xml);
        $targets = [];

        foreach ($paragraphs as $index => $paragraph) {
            $plain = $this->paragraphPlainText($paragraph);
            if (! preg_match('/«/u', $plain) || ! preg_match('/(?:20|202)_{1,}/u', $plain)) {
                continue;
            }

            if (preg_match('/на период с/ui', $plain)) {
                continue;
            }

            if ($mustContain !== '' && ! str_contains($plain, $mustContain)) {
                continue;
            }

            $targets[] = $index;
        }

        if ($targets === []) {
            return $xml;
        }

        if ($last && ! $all) {
            $targets = [(int) end($targets)];
        }

        foreach ($targets as $index) {
            $paragraphs[$index] = $this->replaceParagraphDateFooter($paragraphs[$index], $formatted);
        }

        return $this->replaceParagraphs($xml, $paragraphs);
    }

    private function replaceParagraphDateFooter(string $paragraph, string $formatted): string
    {
        $pattern = '/«\s*_{1,}\s*»\s*_{1,}\s*(?:20|202)_{1,}\s*г?\.?/u';
        $replaced = false;

        $paragraph = preg_replace_callback(
            '/<w:t([^>]*)>([^<]*)<\/w:t>/u',
            function (array $match) use ($pattern, $formatted, &$replaced) {
                if ($replaced) {
                    return $match[0];
                }

                $text = html_entity_decode($match[2], ENT_XML1, 'UTF-8');
                $newText = preg_replace($pattern, $formatted, $text, 1, $count);
                if ($count === 0) {
                    return $match[0];
                }

                $replaced = true;

                return '<w:t' . $match[1] . '>' . $this->escapeXml($newText) . '</w:t>';
            },
            $paragraph,
        ) ?? $paragraph;

        if ($replaced) {
            return $paragraph;
        }

        return $this->collapseSplitDateFooter($paragraph, $formatted);
    }

    private function collapseSplitDateFooter(string $paragraph, string $formatted): string
    {
        $inDate = false;
        $dateStarted = false;

        return preg_replace_callback(
            '/<w:t([^>]*)>([^<]*)<\/w:t>/u',
            function (array $match) use ($formatted, &$inDate, &$dateStarted) {
                $text = html_entity_decode($match[2], ENT_XML1, 'UTF-8');

                if (! $dateStarted && str_contains($text, '«')) {
                    $dateStarted = true;
                    $inDate = true;

                    return '<w:t' . $match[1] . '>' . $this->escapeXml($formatted) . '</w:t>';
                }

                if (! $inDate) {
                    return $match[0];
                }

                if ($text === '____' || preg_match('/^_{2,}$/u', trim($text)) || preg_match('/^»\s*$/u', $text) || preg_match('/(?:20|202)_{1,}/u', $text)) {
                    return '';
                }

                if (preg_match('/г\.?/u', $text)) {
                    $inDate = false;
                    $rest = preg_replace('/^.*?\s*г\.?\s*/u', '', $text) ?? '';

                    return $rest !== ''
                        ? '<w:t' . $match[1] . '>' . $this->escapeXml($rest) . '</w:t>'
                        : '';
                }

                return '';
            },
            $paragraph,
        ) ?? $paragraph;
    }

    private function setParagraphPlainText(string $paragraph, string $value): string
    {
        if (! preg_match('/^(<w:p[^>]*>)(.*?)(<\/w:p>)$/us', $paragraph, $match)) {
            return $paragraph;
        }

        $open = $match[1];
        $inner = $match[2];
        $close = $match[3];

        $pPr = '';
        if (preg_match('/^(<w:pPr>.*?<\/w:pPr>)(.*)$/us', $inner, $parts)) {
            $pPr = $parts[1];
        }

        $style = '';
        if (preg_match('/<w:rPr>.*?<\/w:rPr>/us', $inner, $styleMatch)) {
            $style = $styleMatch[0];
        }

        $value = trim($value);
        if ($value !== '' && ! preg_match('/[\s_]$/u', $value)) {
            $value .= ' ';
        }

        return $open . $pPr . '<w:r>' . $style . '<w:t xml:space="preserve">' . $this->escapeXml($value) . '</w:t></w:r>' . $close;
    }

    /**
     * @return list<string>
     */
    private function collectParagraphs(string $xml): array
    {
        preg_match_all('/<w:p\b.*?<\/w:p>/us', $xml, $matches);

        return $matches[0] ?? [];
    }

    /**
     * @param  list<string>  $paragraphs
     */
    private function replaceParagraphs(string $xml, array $paragraphs): string
    {
        $index = 0;

        return preg_replace_callback('/<w:p\b.*?<\/w:p>/us', function () use (&$index, $paragraphs) {
            return $paragraphs[$index++] ?? '';
        }, $xml) ?? $xml;
    }

    private function paragraphPlainText(string $paragraph): string
    {
        $text = strip_tags(str_replace(['<w:tab/>', '</w:p>'], [' ', ' '], $paragraph));

        return trim(html_entity_decode($text, ENT_XML1, 'UTF-8'));
    }

    private function paragraphContains(string $paragraph, string $needle): bool
    {
        return mb_stripos($this->paragraphPlainText($paragraph), $needle) !== false;
    }

    private function paragraphHasUnderscores(string $paragraph): bool
    {
        return (bool) preg_match('/_{2,}/', $paragraph);
    }

    private function leadingSpacedValue(string $value, bool $needsLeadingSpace): string
    {
        $value = trim($value);
        if ($value === '') {
            return $value;
        }

        return $needsLeadingSpace && ! preg_match('/^\s/u', $value)
            ? ' ' . $value
            : $value;
    }

    private function replaceNextUnderscoreSlot(string $text, string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return $text;
        }

        if (! preg_match('/_{1,}/u', $text, $match, PREG_OFFSET_CAPTURE)) {
            return $text;
        }

        $start = $match[0][1];
        $length = mb_strlen($match[0][0]);
        $before = mb_substr($text, 0, $start);
        $slot = $match[0][0];
        $after = mb_substr($text, $start + $length);

        $replacement = $this->substituteUnderscoreText($slot, $value, $before);

        if ($replacement === $slot) {
            $replacement = $value;
        }

        if ($before !== '' && ! preg_match('/[\s«(]$/u', $before) && ! preg_match('/^\s/u', $replacement)) {
            $replacement = ' ' . $replacement;
        }

        if ($before !== '' && preg_match('/[a-zA-Zа-яА-ЯёЁ0-9]$/u', $before) && preg_match('/^[a-zA-Zа-яА-ЯёЁ0-9(]/u', $replacement)) {
            $replacement = ' ' . $replacement;
        } elseif ($after !== '' && preg_match('/^[\(]/u', $after) && ! preg_match('/\s$/u', $replacement)) {
            $replacement .= ' ';
        } elseif ($after !== '' && ! preg_match('/^[\s,.;:)»]/u', $after) && ! preg_match('/\s$/u', $replacement)) {
            $replacement .= ' ';
        }

        return $before . $replacement . $after;
    }

    private function polishDocumentXml(string $xml): string
    {
        return preg_replace_callback(
            '/<w:t([^>]*)>([^<]*)<\/w:t>/u',
            function (array $match) {
                $text = html_entity_decode($match[2], ENT_XML1, 'UTF-8');
                if (! str_contains($text, '_') && ! preg_match('/\(\s*\)/u', $text)) {
                    return $match[0];
                }

                if (preg_match('/^_{1,}$/u', trim($text))) {
                    return '';
                }

                if (preg_match('/^_{1,}\)/u', $text)) {
                    return '<w:t' . $match[1] . '>)</w:t>';
                }

                $text = preg_replace('/\s*_{1,}\s*\)/u', ')', $text) ?? $text;
                $text = preg_replace('/\(\s*_{1,}\s*/u', '(', $text) ?? $text;
                $text = preg_replace('/\(\s*\)/u', '', $text) ?? $text;
                $text = preg_replace('/\s+,/u', ',', $text) ?? $text;
                $text = preg_replace('/,\s*,/u', ',', $text) ?? $text;
                $text = preg_replace('/\s{2,}/u', ' ', $text) ?? $text;

                return '<w:t' . $match[1] . '>' . $this->escapeXml(trim($text)) . '</w:t>';
            },
            $xml,
        ) ?? $xml;
    }

    private function stripUnderscores(string $text): string
    {
        $text = preg_replace('/_{1,}/u', '', $text) ?? $text;
        $text = preg_replace('/^\s*,\s*/u', '', $text) ?? $text;
        $text = preg_replace('/,([^\s])/u', ', $1', $text) ?? $text;
        $text = preg_replace('/\s{2,}/u', ' ', $text) ?? $text;

        return $text;
    }

    private function stripUnderscoreRunsBeforeAnchor(string $paragraph, string $anchor): string
    {
        $plain = $this->paragraphPlainText($paragraph);
        $anchorPos = mb_stripos($plain, $anchor);
        if ($anchorPos === false) {
            return $paragraph;
        }

        $offset = 0;

        return preg_replace_callback(
            '/<w:t([^>]*)>([^<]*)<\/w:t>/u',
            function (array $match) use ($anchorPos, &$offset) {
                $text = html_entity_decode($match[2], ENT_XML1, 'UTF-8');
                $length = mb_strlen($text);
                $runEnd = $offset + $length;

                if ($runEnd <= $anchorPos && preg_match('/^[\s_]+$/u', $text)) {
                    $offset += $length;

                    return '';
                }

                if ($offset < $anchorPos && preg_match('/^_{1,},\s*/u', $text)) {
                    $text = preg_replace('/^_{1,},\s*/u', '', $text) ?? $text;
                    $offset += $length;

                    return '<w:t' . $match[1] . '>' . $this->escapeXml($text) . '</w:t>';
                }

                $offset += $length;

                return $match[0];
            },
            $paragraph,
        ) ?? $paragraph;
    }

    private function substituteUnderscoreText(string $text, string $value, string $before = ''): string
    {
        if ($value === '') {
            return $text;
        }

        $trimmed = trim($text);
        if (preg_match('/^_{1,}$/u', $trimmed)) {
            if (str_ends_with($before, '«')) {
                return $value;
            }

            return $value;
        }

        if (preg_match('/^(20)(_{1,})$/u', $trimmed) && preg_match('/^\d{2}$/u', $value)) {
            return '20' . $value;
        }

        if (preg_match('/^202_{1,}$/u', $trimmed)) {
            if (preg_match('/^\d{4}$/u', $value)) {
                return $value;
            }

            if (preg_match('/^\d{2}$/u', $value)) {
                return '20' . $value;
            }
        }

        if (preg_match('/^20_{1,}$/u', $trimmed) && preg_match('/^\d{4}$/u', $value)) {
            return $value;
        }

        if (preg_match('/^(.*?)(_{1,})$/u', $text, $matches) && $matches[1] !== '') {
            $prefix = $matches[1];
            if (str_starts_with($value, $prefix)) {
                return $value;
            }
        }

        return preg_replace('/_{1,}/u', $value, $text, 1) ?? $text;
    }

    private function escapeXml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function applyAthletePhoto(ZipArchive $zip, string $photoPath, array $config): void
    {
        if (($config['strategy'] ?? 'replace_first_media') !== 'replace_first_media') {
            return;
        }

        $prepared = $this->preparePhotoBinary($photoPath);
        if ($prepared === null) {
            return;
        }

        $mediaTarget = $this->resolvePhotoMediaTarget($zip) ?? 'word/media/image1.' . $prepared['extension'];

        $targetExtension = strtolower(pathinfo($mediaTarget, PATHINFO_EXTENSION));
        if ($targetExtension !== $prepared['extension']) {
            $newTarget = preg_replace('/\.[^.]+$/', '.' . $prepared['extension'], $mediaTarget) ?? $mediaTarget;
            $this->updateImageRelationship($zip, $mediaTarget, $newTarget, $prepared['extension']);
            $mediaTarget = $newTarget;
        }

        if ($zip->locateName($mediaTarget) !== false) {
            $zip->deleteName($mediaTarget);
        }

        $zip->addFromString($mediaTarget, $prepared['binary']);
    }

    private function resolvePhotoMediaTarget(ZipArchive $zip): ?string
    {
        $xml = $zip->getFromName('word/document.xml');
        $rels = $zip->getFromName('word/_rels/document.xml.rels');
        if ($xml === false || $rels === false) {
            return null;
        }

        $relationId = null;
        if (preg_match('/ФОТО[\s\S]{0,800}?<v:imagedata[^>]+r:id="([^"]+)"/u', $xml, $match)) {
            $relationId = $match[1];
        } elseif (preg_match('/<v:imagedata[^>]+r:id="([^"]+)"/u', $xml, $match)) {
            $relationId = $match[1];
        }

        if ($relationId === null) {
            return null;
        }

        if (! preg_match('/Id="' . preg_quote($relationId, '/') . '"[^>]+Target="([^"]+)"/u', $rels, $targetMatch)) {
            return null;
        }

        return 'word/' . ltrim($targetMatch[1], '/');
    }

    /**
     * @return ?array{binary: string, extension: string}
     */
    private function preparePhotoBinary(string $photoPath): ?array
    {
        if ($photoPath === '') {
            return null;
        }

        if (is_file($photoPath)) {
            $contents = file_get_contents($photoPath);
        } else {
            return null;
        }
        if ($contents === false || $contents === '') {
            return null;
        }

        $sourceExtension = strtolower(pathinfo($photoPath, PATHINFO_EXTENSION));
        if (! in_array($sourceExtension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            $sourceExtension = $this->detectImageExtension($contents) ?? 'jpeg';
        }

        if (function_exists('imagecreatefromstring') && function_exists('imagejpeg')) {
            $image = @imagecreatefromstring($contents);
            if ($image !== false) {
                $width = imagesx($image);
                $height = imagesy($image);
                $maxWidth = 300;
                $maxHeight = 400;

                if ($width > $maxWidth || $height > $maxHeight) {
                    $ratio = min($maxWidth / $width, $maxHeight / $height);
                    $newWidth = max(1, (int) round($width * $ratio));
                    $newHeight = max(1, (int) round($height * $ratio));
                    $resized = imagecreatetruecolor($newWidth, $newHeight);
                    imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
                    imagedestroy($image);
                    $image = $resized;
                }

                ob_start();
                imagejpeg($image, null, 90);
                $jpeg = ob_get_clean() ?: null;
                imagedestroy($image);

                if ($jpeg !== null) {
                    return ['binary' => $jpeg, 'extension' => 'jpeg'];
                }
            }
        }

        $extension = in_array($sourceExtension, ['jpg', 'jpeg'], true) ? 'jpeg' : $sourceExtension;
        if ($extension === 'jpg') {
            $extension = 'jpeg';
        }

        return ['binary' => $contents, 'extension' => $extension];
    }

    private function detectImageExtension(string $binary): ?string
    {
        if (str_starts_with($binary, "\xFF\xD8\xFF")) {
            return 'jpeg';
        }

        if (str_starts_with($binary, "\x89PNG\r\n\x1a\n")) {
            return 'png';
        }

        if (str_starts_with($binary, 'GIF8')) {
            return 'gif';
        }

        if (str_starts_with($binary, 'RIFF') && str_contains(substr($binary, 0, 16), 'WEBP')) {
            return 'webp';
        }

        return null;
    }

    private function updateImageRelationship(ZipArchive $zip, string $oldTarget, string $newTarget, string $extension): void
    {
        $oldName = basename($oldTarget);
        $newName = basename($newTarget);

        $relsPath = 'word/_rels/document.xml.rels';
        $rels = $zip->getFromName($relsPath);
        if ($rels !== false) {
            $rels = str_replace('media/' . $oldName, 'media/' . $newName, $rels);
            $zip->addFromString($relsPath, $rels);
        }

        $contentTypesPath = '[Content_Types].xml';
        $contentTypes = $zip->getFromName($contentTypesPath);
        if ($contentTypes !== false) {
            $mime = match ($extension) {
                'png' => 'image/png',
                'gif' => 'image/gif',
                'webp' => 'image/webp',
                default => 'image/jpeg',
            };

            if (! str_contains($contentTypes, $mime)) {
                $contentTypes = str_replace(
                    '</Types>',
                    '<Default Extension="' . $extension . '" ContentType="' . $mime . '"/></Types>',
                    $contentTypes,
                );
            }

            $zip->addFromString($contentTypesPath, $contentTypes);
        }
    }
}
