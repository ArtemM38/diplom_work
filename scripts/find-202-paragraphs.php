<?php

$z = new ZipArchive();
$z->open(__DIR__ . '/../storage/app/document-templates/template-4.docx');
$x = $z->getFromName('word/document.xml');
$z->close();

preg_match_all('/<w:p\b.*?<\/w:p>/us', $x, $m);
$c = 0;
foreach ($m[0] as $p) {
    $t = html_entity_decode(strip_tags($p));
    if (str_contains($t, '202')) {
        echo $t . "\n\n";
        $c++;
    }
}
echo 'count=' . $c . "\n";
