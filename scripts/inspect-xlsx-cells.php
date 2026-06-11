<?php

require __DIR__ . '/../vendor/autoload.php';

$n = (int) ($argv[1] ?? 7);
$ext = $n === 8 ? 'xls' : 'xlsx';
$path = __DIR__ . "/../storage/app/document-templates/template-{$n}.{$ext}";
$sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path)->getActiveSheet();

foreach ($sheet->getRowIterator(1, (int) $sheet->getHighestRow()) as $row) {
    foreach ($row->getCellIterator() as $cell) {
        $v = $cell->getCalculatedValue();
        if (! is_string($v) || $v === '') {
            continue;
        }
        if (str_contains($v, '_') || preg_match('/№|Дана|Директору|Покупатель|Поставщик/i', $v)) {
            echo $cell->getCoordinate() . ': ' . mb_substr($v, 0, 120) . "\n";
        }
    }
}
