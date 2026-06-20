<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Athlete;
use App\Models\Guardian;
use App\Support\AthleteDocumentVariables;
use App\Support\DocxTemplateFiller;

$n = (int) ($argv[1] ?? 2);
$path = storage_path("app/document-templates/template-{$n}.docx");

$athlete = new Athlete(['last_name_nom' => 'Иванов', 'first_name_nom' => 'Иван', 'middle_name_nom' => 'Иванович', 'birth_date' => '2012-05-15', 'occupation_type' => 'study', 'created_at' => '2024-09-01']);
$guardian = new Guardian(['full_name' => 'Петрова Мария Сергеевна']);
$athlete->setRelation('guardians', collect([$guardian]));
$athlete->setRelation('documents', collect([]));

$filled = app(DocxTemplateFiller::class)->fill($path, AthleteDocumentVariables::build($athlete), config("athlete_document_templates.fill.{$n}"));

$zip = new ZipArchive();
$zip->open($filled);
$xml = $zip->getFromName('word/document.xml');
$zip->close();

preg_match_all('/<w:p\b.*?<\/w:p>/us', $xml, $paragraphs);
foreach ($paragraphs[0] as $p) {
    if (! str_contains(strip_tags($p), 'Я,')) {
        continue;
    }

    preg_match_all('/<w:t[^>]*>([^<]*)<\/w:t>/u', $p, $runs);
    foreach ($runs[1] as $i => $run) {
        echo "run{$i}: [" . html_entity_decode($run, ENT_XML1, 'UTF-8') . "]\n";
    }
}

@unlink($filled);
