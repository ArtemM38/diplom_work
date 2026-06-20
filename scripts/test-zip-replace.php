<?php

copy(__DIR__ . '/../storage/app/document-templates/template-3.docx', __DIR__ . '/../tmp-test.docx');
$z = new ZipArchive();
$z->open(__DIR__ . '/../tmp-test.docx');

$before = strlen((string) $z->getFromName('word/media/image1.jpeg'));
$z->addFromString('word/media/image1.jpeg', str_repeat('X', 100));
$z->close();

$z2 = new ZipArchive();
$z2->open(__DIR__ . '/../tmp-test.docx');
$count = 0;
for ($i = 0; $i < $z2->numFiles; $i++) {
    if ($z2->getNameIndex($i) === 'word/media/image1.jpeg') {
        $count++;
    }
}
$after = strlen((string) $z2->getFromName('word/media/image1.jpeg'));
$z2->close();

echo "before={$before} after={$after} duplicate_entries={$count}\n";
unlink(__DIR__ . '/../tmp-test.docx');

// test with delete first
copy(__DIR__ . '/../storage/app/document-templates/template-3.docx', __DIR__ . '/../tmp-test.docx');
$z = new ZipArchive();
$z->open(__DIR__ . '/../tmp-test.docx');
$z->deleteName('word/media/image1.jpeg');
$z->addFromString('word/media/image1.jpeg', str_repeat('Y', 200));
$z->close();
$z2 = new ZipArchive();
$z2->open(__DIR__ . '/../tmp-test.docx');
$count = 0;
for ($i = 0; $i < $z2->numFiles; $i++) {
    if ($z2->getNameIndex($i) === 'word/media/image1.jpeg') {
        $count++;
    }
}
$afterDelete = strlen((string) $z2->getFromName('word/media/image1.jpeg'));
$z2->close();
echo "with_delete after={$afterDelete} duplicate_entries={$count}\n";
unlink(__DIR__ . '/../tmp-test.docx');
