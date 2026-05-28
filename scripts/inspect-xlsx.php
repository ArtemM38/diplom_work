<?php

require __DIR__ . '/../vendor/autoload.php';

foreach ([7, 8] as $n) {
    $ext = $n === 7 ? 'xlsx' : 'xls';
    $path = __DIR__ . "/../storage/app/document-templates/template-{$n}.{$ext}";
    echo "\n=== template-{$n}.{$ext} ===\n";
    $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path)->getActiveSheet();
    $maxRow = min(40, $sheet->getHighestRow());
    $maxCol = min(8, \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestColumn()));
    for ($r = 1; $r <= $maxRow; $r++) {
        for ($c = 1; $c <= $maxCol; $c++) {
            $v = $sheet->getCell([$c, $r])->getCalculatedValue();
            if ($v !== null && $v !== '') {
                $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
                echo "{$col}{$r}: {$v}\n";
            }
        }
    }
}
