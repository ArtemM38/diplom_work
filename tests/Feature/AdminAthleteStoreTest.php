<?php

namespace Tests\Feature;

use App\Models\Athlete;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAthleteStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_creates_athlete_with_user_account(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'roles' => ['admin'],
            'is_active' => true,
        ]);

        $login = 'ivanov-ivan-' . uniqid();
        $email = 'ivanov-' . uniqid() . '@example.com';

        $response = $this->actingAs($admin)->post(route('admin.athletes.store'), [
            'last_name_nom' => 'Иванов',
            'first_name_nom' => 'Иван',
            'middle_name_nom' => 'Иванович',
            'birth_date' => '2012-05-15',
            'gender' => 'male',
            'occupation_type' => 'kindergarten',
            'kindergarten_name' => 'ДС №10',
            'login' => $login,
            'email' => $email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect();

        $athlete = Athlete::query()->where('last_name_nom', 'Иванов')->first();
        $this->assertNotNull($athlete);
        $this->assertNotNull($athlete->user_id);

        $this->assertDatabaseHas('users', [
            'id' => $athlete->user_id,
            'login' => $login,
            'email' => $email,
            'is_active' => 1,
        ]);

        $athleteUser = User::find($athlete->user_id);
        $this->assertNotNull($athleteUser);
        $this->assertSame(['athlete'], $athleteUser->getRolesList());

        $this->actingAs($athleteUser)
            ->get(route('admin.coaches'))
            ->assertForbidden();
    }
}
