<?php

namespace Tests\Feature;

use App\Models\Athlete;
use App\Models\AthleteDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminMedicalCertificatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_medical_and_insurance_documents(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'roles' => ['admin'],
            'is_active' => true,
        ]);

        $athlete = Athlete::create([
            'last_name_nom' => 'Иванов',
            'first_name_nom' => 'Иван',
            'middle_name_nom' => 'Иванович',
            'birth_date' => '2012-05-15',
            'gender' => 'male',
            'occupation_type' => 'kindergarten',
            'kindergarten_name' => 'ДС №1',
        ]);

        AthleteDocument::create([
            'athlete_id' => $athlete->id,
            'type' => 'medical',
            'issue_date' => '2025-01-01',
            'expiry_date' => '2026-06-01',
            'file_path' => 'athletes/documents/med.pdf',
        ]);

        AthleteDocument::create([
            'athlete_id' => $athlete->id,
            'type' => 'insurance',
            'issue_date' => '2025-01-01',
            'expiry_date' => '2026-12-01',
            'file_path' => 'athletes/documents/ins.pdf',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.medical-certificates'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/MedicalCertificates/Index')
            ->has('documents', 2)
            ->where('documents.0.type', 'medical')
            ->where('documents.1.type', 'insurance')
        );
    }

    public function test_admin_can_filter_by_insurance_only(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'roles' => ['admin'],
            'is_active' => true,
        ]);

        $athlete = Athlete::create([
            'last_name_nom' => 'Петров',
            'first_name_nom' => 'Пётр',
            'middle_name_nom' => 'Петрович',
            'birth_date' => '2012-05-15',
            'gender' => 'male',
            'occupation_type' => 'kindergarten',
            'kindergarten_name' => 'ДС №1',
        ]);

        AthleteDocument::create([
            'athlete_id' => $athlete->id,
            'type' => 'medical',
            'issue_date' => '2025-01-01',
            'expiry_date' => '2026-06-01',
            'file_path' => 'athletes/documents/med.pdf',
        ]);

        AthleteDocument::create([
            'athlete_id' => $athlete->id,
            'type' => 'insurance',
            'issue_date' => '2025-01-01',
            'expiry_date' => '2026-12-01',
            'file_path' => 'athletes/documents/ins.pdf',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.medical-certificates', ['type' => 'insurance']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/MedicalCertificates/Index')
            ->has('documents', 1)
            ->where('documents.0.type', 'insurance')
            ->where('documents.0.type_label', 'Страховой полис')
        );
    }
}
