<?php

$path = __DIR__ . '/../storage/app/document-templates/template-3.docx';
$zip = new ZipArchive();
$zip->open($path);
$xml = $zip->getFromName('word/document.xml');
$rels = $zip->getFromName('word/_rels/document.xml.rels');

if (preg_match('/<w:pict[\s\S]{0,4000}?<\/w:pict>/u', $xml, $m)) {
    echo $m[0] . "\n\n";
}

if (preg_match('/<v:imagedata[^>]+>/u', $xml, $m)) {
    echo $m[0] . "\n\n";
}

preg_match_all('/Relationship Id="([^"]+)"[^>]+Target="([^"]+)"/u', $rels, $matches, PREG_SET_ORDER);
foreach ($matches as $match) {
    if (str_contains($match[2], 'media')) {
        echo "rel {$match[1]} -> {$match[2]}\n";
    }
}

$zip->close();
