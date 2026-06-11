<?php

$n = (int) ($argv[1] ?? 1);
$needle = $argv[2] ?? 'Тел';
$path = __DIR__ . "/../storage/app/document-templates/template-{$n}.docx";
$zip = new ZipArchive();
$zip->open($path);
$xml = $zip->getFromName('word/document.xml');
$zip->close();

$pos = mb_stripos($xml, $needle);
if ($pos === false) {
    echo "not found\n";
    exit(1);
}

$before = (int) ($argv[3] ?? 400);
$len = (int) ($argv[4] ?? 1200);
echo mb_substr($xml, max(0, $pos - $before), $len);
