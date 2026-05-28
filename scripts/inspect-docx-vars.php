<?php

$dir = __DIR__ . '/../storage/app/document-templates';

foreach (glob($dir . '/template-*.docx') as $path) {
    echo "\n=== " . basename($path) . " ===\n";
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        echo "cannot open\n";
        continue;
    }
    $xml = $zip->getFromName('word/document.xml');
    $zip->close();

    preg_match_all('/\$\{[^}]+\}/', $xml, $m);
    $vars = array_unique($m[0] ?? []);
    if ($vars) {
        echo implode("\n", $vars) . "\n";
        continue;
    }

    $text = html_entity_decode(strip_tags(str_replace(['</w:p>', '<w:tab/>'], ["\n", "\t"], $xml)));
    $text = preg_replace('/\s+/u', ' ', $text);
    echo mb_substr(trim($text), 0, 500) . "\n";
}
