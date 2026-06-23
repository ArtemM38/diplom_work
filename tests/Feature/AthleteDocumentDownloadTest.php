<?php

namespace Tests\Feature;

use App\Models\Athlete;
use App\Models\Guardian;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class AthleteDocumentDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_application_document_is_filled_from_profile(): void
    {
        if (! file_exists(storage_path('app/document-templates/template-1.docx'))) {
            $this->markTestSkipped('Шаблон template-1.docx не найден.');
        }

        $user = User::factory()->create([
            'role' => 'athlete',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $school = Institution::create([
            'type' => 'study',
            'name' => 'Школа № 1',
            'director_dat' => 'директору школы № 1',
        ]);

        $athlete = Athlete::create([
            'user_id' => $user->id,
            'last_name_nom' => 'Иванов',
            'first_name_nom' => 'Иван',
            'middle_name_nom' => 'Иванович',
            'phone' => '+7 (914) 000-00-01',
            'birth_date' => '2012-05-15',
            'gender' => 'male',
            'occupation_type' => 'study',
            'institution_id' => $school->id,
            'registration_address' => 'г. Иркутск, ул. Ленина, 1',
            'school_class' => '5А',
            'full_name_gen' => 'Иванова Ивана Ивановича',
            'full_name_dat' => 'Иванову Ивану Ивановичу',
            'full_name_ins' => 'Ивановым Иваном Ивановичем',
        ]);

        $guardianUser = User::factory()->create([
            'role' => 'guardian',
            'is_active' => true,
            'name' => 'Петрова Мария Сергеевна',
        ]);

        $guardian = Guardian::create([
            'user_id' => $guardianUser->id,
            'full_name' => 'Петрова Мария Сергеевна',
            'phone' => '+7 (914) 111-22-33',
            'relation' => 'Мать',
        ]);

        $athlete->guardians()->attach($guardian->id);

        $response = $this->actingAs($user)->get(route('athlete.documents.download', [
            'template' => 1,
            'format' => 'docx',
        ]));

        $response->assertOk();
        $response->assertDownload();

        $file = $response->baseResponse->getFile();
        $this->assertNotNull($file);

        $zip = new ZipArchive();
        $zip->open($file->getPathname());
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        $text = html_entity_decode(strip_tags(str_replace('</w:p>', "\n", $xml)), ENT_XML1, 'UTF-8');

        $this->assertStringContainsString('Иванова Ивана Ивановича', $text);
        $this->assertStringNotContainsString('моего ребенка Иванов Иван', $text);
        $this->assertStringContainsString('2012', $text);
        $this->assertStringContainsString('г. Иркутск, ул. Ленина, 1', $text);
        $this->assertStringContainsString('Петровой', $text);
        $this->assertStringContainsString('+7 (914) 111-22-33', $text);
        $this->assertStringNotContainsString('202026', $text);
    }
}
