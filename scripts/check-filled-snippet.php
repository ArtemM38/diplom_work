<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Athlete;
use App\Models\Guardian;
use App\Support\AthleteDocumentVariables;
use App\Support\DocxTemplateFiller;

$n = (int) ($argv[1] ?? 5);
$needle = $argv[2] ?? 'ребенком';

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
echo "training_start_date={$vars['training_start_date']}\n";

$path = storage_path("app/document-templates/template-{$n}.docx");
$filled = app(DocxTemplateFiller::class)->fill($path, $vars, config("athlete_document_templates.fill.{$n}"));

$zip = new ZipArchive();
$zip->open($filled);
$xml = $zip->getFromName('word/document.xml');
$zip->close();
@unlink($filled);

preg_match_all('/<w:p\b.*?<\/w:p>/us', $xml, $matches);
foreach ($matches[0] as $p) {
    $plain = html_entity_decode(strip_tags($p), ENT_XML1, 'UTF-8');
    if (str_contains($plain, $needle)) {
        echo $plain . "\n";
        var_export($vars);
        break;
    }
}
