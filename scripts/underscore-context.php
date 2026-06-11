<?php

$n = (int) ($argv[1] ?? 1);
$path = __DIR__ . "/../storage/app/document-templates/template-{$n}.docx";
$zip = new ZipArchive();
$zip->open($path);
$xml = $zip->getFromName('word/document.xml');
$zip->close();

$text = html_entity_decode(strip_tags(str_replace(['</w:p>', '<w:tab/>'], ["\n", "\t"], $xml)));
$text = preg_replace('/\s+/u', ' ', $text);

$pos = 0;
$i = 0;
while (preg_match('/_{3,}/', $text, $m, PREG_OFFSET_CAPTURE, $pos)) {
    $i++;
    $start = max(0, $m[0][1] - 60);
    $snippet = mb_substr($text, $start, 120);
    echo $i . ': ...' . trim($snippet) . "...\n";
    $pos = $m[0][1] + strlen($m[0][0]);
}
