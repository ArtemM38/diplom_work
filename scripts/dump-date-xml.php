<?php

$n = (int) ($argv[1] ?? 5);
$path = __DIR__ . "/../storage/app/document-templates/template-{$n}.docx";
$zip = new ZipArchive();
$zip->open($path);
$xml = $zip->getFromName('word/document.xml');
$zip->close();

preg_match_all('/<w:p\b.*?<\/w:p>/us', $xml, $matches);
foreach ($matches[0] as $p) {
    $plain = trim(html_entity_decode(strip_tags(str_replace('<w:tab/>', "\t", $p)), ENT_XML1, 'UTF-8'));
    if (! str_contains($plain, '«') || ! preg_match('/20_{2,}/', $plain)) {
        continue;
    }

    echo "PLAIN: {$plain}\n";
    preg_match_all('/<w:t[^>]*>([^<]*)<\/w:t>/u', $p, $runs);
    foreach ($runs[1] as $i => $t) {
        $t = html_entity_decode($t, ENT_XML1, 'UTF-8');
        if ($t !== '' && $t !== ' ') {
            echo "  run{$i}: [{$t}]\n";
        }
    }
    echo "\n";
}
