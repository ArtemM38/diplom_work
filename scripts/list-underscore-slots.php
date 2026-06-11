<?php

$n = (int) ($argv[1] ?? 1);
$path = __DIR__ . "/../storage/app/document-templates/template-{$n}.docx";
$zip = new ZipArchive();
$zip->open($path);
$xml = $zip->getFromName('word/document.xml');
$zip->close();

preg_match_all('/_{3,}/', $xml, $m);
echo 'template-' . $n . ': ' . count($m[0]) . " slots\n";
foreach ($m[0] as $i => $u) {
    echo ($i + 1) . ': len=' . strlen($u) . "\n";
}
