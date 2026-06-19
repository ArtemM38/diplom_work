<?php

namespace App\Support;

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
        $zip->open($target);

        foreach (['word/document.xml', 'word/header1.xml', 'word/header2.xml', 'word/footer1.xml', 'word/footer2.xml'] as $part) {
            $xml = $zip->getFromName($part);
            if ($xml === false) {
                continue;
            }

            foreach ($config['rules'] ?? [] as $rule) {
                $xml = $this->applyRule($xml, $rule, $variables);
            }

            $xml = $this->polishDocumentXml($xml);

            $zip->addFromString($part, $xml);
        }

        $zip->close();

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
        $multiValueTypes = ['slots_after_anchor', 'underscores_in_paragraph', 'period_range', 'date_russian', 'birth_date_line', 'table_cell_after_label', 'document_date_footer'];

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

            if ($clearNextUnderscoreParagraph && isset($paragraphs[$index + 1]) && $this->paragraphHasUnderscores($paragraphs[$index + 1])) {
                $paragraphs[$index + 1] = $this->setParagraphPlainText($paragraphs[$index + 1], '');
            }
        }

        return $this->replaceParagraphs($xml, $paragraphs);
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

            $slot = 0;
            $paragraphs[$index] = preg_replace_callback(
                '/<w:t([^>]*)>([^<]*)<\/w:t>/u',
                function (array $match) use ($varKeys, $variables, &$slot) {
                    $text = html_entity_decode($match[2], ENT_XML1, 'UTF-8');
                    if (! preg_match('/_{1,}/u', $text)) {
                        return $match[0];
                    }

                    $key = $varKeys[$slot] ?? null;
                    $slot++;
                    if ($key === null) {
                        return $match[0];
                    }

                    $value = trim((string) ($variables[$key] ?? ''));
                    if ($value === '') {
                        return $match[0];
                    }

                    return '<w:t' . $match[1] . '>' . $this->escapeXml($this->substituteUnderscoreText($text, $value)) . '</w:t>';
                },
                $paragraph,
            ) ?? $paragraph;

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
            if (! str_contains($plain, '«') || ! preg_match('/20_{1,}\s*г/u', $plain)) {
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
            $slot = 0;
            $values = [$day, $month, $yearShort];
            $paragraphs[$index] = preg_replace_callback(
                '/<w:t([^>]*)>([^<]*)<\/w:t>/u',
                function (array $match) use ($values, &$slot) {
                    $text = html_entity_decode($match[2], ENT_XML1, 'UTF-8');
                    if (! preg_match('/_{1,}/u', $text)) {
                        return $match[0];
                    }

                    if ($slot >= count($values)) {
                        return $match[0];
                    }

                    $value = $values[$slot];
                    $slot++;

                    return $value !== ''
                        ? '<w:t' . $match[1] . '>' . $this->escapeXml($this->substituteUnderscoreText($text, $value)) . '</w:t>'
                        : $match[0];
                },
                $paragraphs[$index],
            ) ?? $paragraphs[$index];
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

        if (implode('', $slots) === '') {
            return $xml;
        }

        $paragraphs = $this->collectParagraphs($xml);

        foreach ($paragraphs as $index => $paragraph) {
            if (! $this->paragraphContains($paragraph, 'на период с')) {
                continue;
            }

            $slot = 0;
            $paragraphs[$index] = preg_replace_callback(
                '/<w:t([^>]*)>([^<]*)<\/w:t>/u',
                function (array $match) use ($slots, &$slot) {
                    $text = html_entity_decode($match[2], ENT_XML1, 'UTF-8');
                    if (! preg_match('/_{1,}/u', $text)) {
                        return $match[0];
                    }

                    if ($slot >= count($slots)) {
                        return $match[0];
                    }

                    $value = trim((string) $slots[$slot]);
                    $slot++;

                    return $value !== ''
                        ? '<w:t' . $match[1] . '>' . $this->escapeXml($this->substituteUnderscoreText($text, $value)) . '</w:t>'
                        : $match[0];
                },
                $paragraph,
            ) ?? $paragraph;

            return $this->replaceParagraphs($xml, $paragraphs);
        }

        return $xml;
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
            if (! preg_match('/«/u', $plain) || ! preg_match('/20_{1,}/u', $plain)) {
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
        $pattern = '/«\s*_{1,}\s*»\s*_{1,}\s*20_{1,}\s*г?\.?/u';
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

                if ($text === '____' || preg_match('/^_{2,}$/u', trim($text)) || preg_match('/^»\s*$/u', $text) || preg_match('/20_{1,}/u', $text)) {
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
        if ($value !== '' && ! preg_match('/\s$/u', $value)) {
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
}
