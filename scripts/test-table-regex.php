<?php

$z = new ZipArchive();
$z->open(__DIR__ . '/../storage/app/document-templates/template-3.docx');
$x = $z->getFromName('word/document.xml');
$z->close();

$label = 'Дата рождения';
$pattern = '/(' . preg_quote($label, '/') . '[\s\S]*?<\/w:tc>[\s\S]*?<w:tc[^>]*>[\s\S]*?<w:t[^>]*>)([^<]*)(<\/w:t>)/u';

if (preg_match($pattern, $x, $m)) {
    echo "match: [{$m[2]}]\n";
} else {
    echo "no match\n";
    $pos = stripos($x, $label);
    echo substr($x, $pos, 400) . "\n";
}
