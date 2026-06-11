<?php

require __DIR__ . '/../vendor/autoload.php';

$n = (int) ($argv[1] ?? 7);
$coord = $argv[2] ?? 'G15';
$ext = $n === 8 ? 'xls' : 'xlsx';
$path = __DIR__ . "/../storage/app/document-templates/template-{$n}.{$ext}";
$sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path)->getActiveSheet();
echo $coord . ': [' . $sheet->getCell($coord)->getCalculatedValue() . "]\n";
