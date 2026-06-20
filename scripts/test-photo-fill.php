<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Athlete;
use App\Support\AthleteDocumentVariables;
use App\Support\DocxTemplateFiller;

$photoPath = storage_path('app/public/athletes/photos/test-photo.jpg');
@mkdir(dirname($photoPath), 0755, true);
$z = new ZipArchive();
$z->open(storage_path('app/document-templates/template-3.docx'));
$templateImage = $z->getFromName('word/media/image1.jpeg');
$z->close();
file_put_contents($photoPath, $templateImage);
$bin = file_get_contents($photoPath);
$bin[50] = chr(ord($bin[50]) ^ 0xFF);
file_put_contents($photoPath, $bin);

$athlete = new Athlete(['photo' => 'athletes/photos/test-photo.jpg', 'occupation_type' => 'study', 'created_at' => '2024-01-01']);
$athlete->setRelation('guardians', collect([]));
$athlete->setRelation('documents', collect([]));

$vars = AthleteDocumentVariables::build($athlete);
$filled = app(DocxTemplateFiller::class)->fill(
    storage_path('app/document-templates/template-3.docx'),
    $vars,
    config('athlete_document_templates.fill.3'),
);

$z = new ZipArchive();
$z->open($filled);
$filledImage = $z->getFromName('word/media/image1.jpeg');
$z->close();

echo 'template md5: ' . md5($templateImage) . "\n";
echo 'athlete  md5: ' . md5_file($photoPath) . "\n";
echo 'filled   md5: ' . md5($filledImage) . "\n";
echo 'replaced: ' . (md5($filledImage) === md5_file($photoPath) ? 'YES' : 'NO') . "\n";
@unlink($filled);
