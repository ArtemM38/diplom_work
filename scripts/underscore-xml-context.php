<?php

$n = (int) ($argv[1] ?? 1);
$path = __DIR__ . "/../storage/app/document-templates/template-{$n}.docx";
$zip = new ZipArchive();
$zip->open($path);
$xml = $zip->getFromName('word/document.xml');
$zip->close();

$parts = preg_split('/(<w:t[^>]*>.*?<\/w:t>)/s', $xml, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
$context = [];
$buffer = '';

foreach ($parts as $part) {
    if (! preg_match('/^<w:t[^>]*>(.*)<\/w:t>$/s', $part, $m)) {
        continue;
    }

    $text = html_entity_decode($m[1], ENT_XML1, 'UTF-8');
    $buffer .= $text;

    if (preg_match('/_{3,}/', $text)) {
        $context[] = mb_substr($buffer, -100);
        $buffer = '';
    }
}

foreach ($context as $i => $snippet) {
    $snippet = preg_replace('/\s+/u', ' ', $snippet);
    echo ($i + 1) . ': ' . trim($snippet) . "\n";
}

echo 'total: ' . count($context) . "\n";
