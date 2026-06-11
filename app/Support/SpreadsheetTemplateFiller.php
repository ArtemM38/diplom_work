<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\IOFactory;

class SpreadsheetTemplateFiller
{
    /**
     * @param  array<string, string>  $variables
     * @param  array<string, mixed>  $config
     */
    public function fill(string $sourcePath, string $extension, array $variables, array $config): string
    {
        $spreadsheet = IOFactory::load($sourcePath);
        $sheet = $spreadsheet->getActiveSheet();

        foreach (($config['cells'] ?? []) as $cell => $key) {
            $value = trim((string) ($variables[$key] ?? ''));
            if ($value !== '') {
                $sheet->setCellValue($cell, $value);
            }
        }

        foreach ($sheet->getRowIterator() as $row) {
            foreach ($row->getCellIterator() as $cell) {
                $value = $cell->getValue();
                if (! is_string($value) || ! preg_match('/_{3,}/', $value)) {
                    continue;
                }

                $replaced = $this->replaceUnderscoreFragments($value, $variables, $config['text_slots'] ?? []);
                if ($replaced !== $value) {
                    $cell->setValue($replaced);
                }
            }
        }

        $temp = tempnam(sys_get_temp_dir(), 'xlsx-fill-');
        $target = $temp . '.' . $extension;
        if (file_exists($temp)) {
            unlink($temp);
        }

        $writerType = $extension === 'xls' ? 'Xls' : 'Xlsx';
        IOFactory::createWriter($spreadsheet, $writerType)->save($target);

        return $target;
    }

    /**
     * @param  array<int, string|null>  $slots
     * @param  array<string, string>  $variables
     */
    private function replaceUnderscoreFragments(string $text, array $variables, array $slots): string
    {
        $index = 0;

        return preg_replace_callback('/_{3,}/', function (array $matches) use ($slots, $variables, &$index) {
            $key = $slots[$index] ?? null;
            $index++;

            if ($key === null || $key === '') {
                return $matches[0];
            }

            $value = trim((string) ($variables[$key] ?? ''));

            return $value !== '' ? $value : $matches[0];
        }, $text) ?? $text;
    }
}
