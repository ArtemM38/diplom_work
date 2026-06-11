<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Athlete;
use App\Models\Guardian;
use App\Support\AthleteDocumentVariables;
use App\Support\DocxTemplateFiller;

$n = (int) ($argv[1] ?? 1);
$path = storage_path("app/document-templates/template-{$n}.docx");

$athlete = new Athlete([
    'last_name_nom' => 'Иванов',
    'first_name_nom' => 'Иван',
    'middle_name_nom' => 'Иванович',
    'birth_date' => '2012-05-15',
    'registration_address' => 'г. Иркутск, ул. Ленина, 1',
    'occupation_type' => 'study',
    'school_name' => 'Школа №1',
    'school_class' => '5А',
    'school_director_dat' => 'Ивановой Марии Петровне',
    'created_at' => '2024-09-01',
]);
$guardian = new Guardian([
    'full_name' => 'Петрова Мария Сергеевна',
    'phone' => '+7 (914) 111-22-33',
    'relation' => 'Мать',
]);
$athlete->setRelation('guardians', collect([$guardian]));
$athlete->setRelation('documents', collect([]));

$vars = AthleteDocumentVariables::build($athlete);
$config = config("athlete_document_templates.fill.{$n}");
$filled = app(DocxTemplateFiller::class)->fill($path, $vars, $config);

foreach ([$path, $filled] as $file) {
    $zip = new ZipArchive();
    $zip->open($file);
    $xml = $zip->getFromName('word/document.xml');
    $zip->close();
    $text = html_entity_decode(strip_tags(str_replace(['</w:p>', '<w:tab/>'], ["\n", "\t"], $xml)));
    $underscores = preg_match_all('/_{3,}/', $xml, $m);
    echo "\n=== " . basename($file) . " underscores left: {$underscores} ===\n";
    echo preg_replace('/\n{3,}/', "\n\n", trim($text)) . "\n";
}

@unlink($filled);
