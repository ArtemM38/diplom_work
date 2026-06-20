<?php

namespace Tests\Feature;

use App\Models\Athlete;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAthleteUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_athlete_profile(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'roles' => ['admin'],
            'is_active' => true,
        ]);

        $athleteUser = User::factory()->create([
            'role' => 'athlete',
            'roles' => ['athlete'],
            'is_active' => true,
        ]);

        $athlete = Athlete::create([
            'user_id' => $athleteUser->id,
            'last_name_nom' => 'Иванов',
            'first_name_nom' => 'Иван',
            'middle_name_nom' => 'Иванович',
            'birth_date' => '2012-05-15',
            'gender' => 'male',
            'occupation_type' => 'kindergarten',
            'kindergarten_name' => 'ДС №1',
        ]);

        $response = $this->actingAs($admin)->patch(route('athlete.update', $athlete), [
            'last_name_nom' => 'Петров',
            'first_name_nom' => 'Пётр',
            'middle_name_nom' => 'Петрович',
            'birth_date' => '2012-05-15',
            'gender' => 'male',
            'occupation_type' => 'kindergarten',
            'kindergarten_name' => 'ДС №2',
        ]);

        $response->assertRedirect(route('admin.athletes.show', $athlete));

        $athlete->refresh();
        $this->assertSame('Петров', $athlete->last_name_nom);
        $this->assertSame('ДС №2', $athlete->kindergarten_name);
    }
}
