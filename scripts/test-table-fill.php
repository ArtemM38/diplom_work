<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Athlete;
use App\Support\AthleteDocumentVariables;
use App\Support\DocxTemplateFiller;

$athlete = new Athlete([
    'last_name_nom' => 'Иванов',
    'first_name_nom' => 'Иван',
    'middle_name_nom' => 'Иванович',
    'birth_date' => '2012-05-15',
    'occupation_type' => 'study',
]);
$athlete->setRelation('guardians', collect([]));
$athlete->setRelation('documents', collect([]));

$vars = AthleteDocumentVariables::build($athlete);
echo "birth formatted: {$vars['athlete_birth_formatted']}\n";

$path = storage_path('app/document-templates/template-3.docx');
$filled = app(DocxTemplateFiller::class)->fill($path, $vars, config('athlete_document_templates.fill.3'));

$z = new ZipArchive();
$z->open($filled);
$x = $z->getFromName('word/document.xml');
$z->close();

echo str_contains($x, '«15»') || str_contains($x, 'мая') ? "filled ok\n" : "not filled\n";
@unlink($filled);
