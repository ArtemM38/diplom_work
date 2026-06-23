<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Support\InstitutionSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstitutionSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_creates_institution_from_user_input(): void
    {
        $id = InstitutionSync::resolveId([
            'occupation_type' => 'study',
            'school_name' => 'Школа № 12',
            'school_director_dat' => 'директору школы № 12',
        ]);

        $this->assertNotNull($id);
        $this->assertDatabaseHas('institutions', [
            'id' => $id,
            'type' => 'study',
            'name' => 'Школа № 12',
            'director_dat' => 'директору школы № 12',
        ]);
    }

    public function test_sync_reuses_existing_institution_with_same_name(): void
    {
        $existing = Institution::create([
            'type' => 'kindergarten',
            'name' => 'ДС «Радуга»',
        ]);

        $id = InstitutionSync::resolveId([
            'occupation_type' => 'kindergarten',
            'kindergarten_name' => 'ДС «Радуга»',
        ]);

        $this->assertSame($existing->id, $id);
        $this->assertSame(1, Institution::count());
    }
}
