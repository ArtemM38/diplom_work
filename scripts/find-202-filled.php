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
    'school_name' => 'Школа №1',
    'school_director_dat' => 'Ивановой М.П.',
    'created_at' => '2024-09-01',
]);
$athlete->setRelation('guardians', collect([]));
$athlete->setRelation('documents', collect([]));

$vars = AthleteDocumentVariables::build($athlete);
$filled = app(DocxTemplateFiller::class)->fill(
    storage_path('app/document-templates/template-4.docx'),
    $vars,
    config('athlete_document_templates.fill.4'),
);

$z = new ZipArchive();
$z->open($filled);
$x = $z->getFromName('word/document.xml');
$z->close();

preg_match_all('/<w:p\b.*?<\/w:p>/us', $x, $m);
foreach ($m[0] as $p) {
    $t = html_entity_decode(strip_tags($p));
    if (str_contains($t, '202')) {
        echo $t . "\n\n";
    }
}
@unlink($filled);
