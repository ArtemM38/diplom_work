<?php

$path = __DIR__ . '/../storage/app/document-templates/template-1.docx';
$zip = new ZipArchive();
$zip->open($path);
$xml = $zip->getFromName('word/document.xml');
$zip->close();

preg_match_all('/<w:t[^>]*>([^<]*_{3,}[^<]*)<\/w:t>/', $xml, $m);
foreach (array_slice(array_unique($m[1]), 0, 15) as $t) {
    echo strlen($t) . ': ' . mb_substr($t, 0, 80) . "\n";
}

echo "\nTotal underscore runs: " . count($m[1]) . "\n";
