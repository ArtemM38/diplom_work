<?php

namespace Tests\Feature;

use App\Models\Athlete;
use App\Models\Guardian;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuardianRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guardian_setup_does_not_link_athlete_automatically(): void
    {
        $athleteUser = User::factory()->create(['role' => 'athlete', 'is_active' => true]);
        $athlete = Athlete::create([
            'user_id' => $athleteUser->id,
            'last_name_nom' => 'Петров',
            'first_name_nom' => 'Пётр',
            'middle_name_nom' => null,
            'birth_date' => '2012-05-01',
            'gender' => 'male',
        ]);

        $guardianUser = User::factory()->create(['role' => 'guardian', 'is_active' => true]);

        $this->actingAs($guardianUser)
            ->post(route('guardian.store'), [
                'full_name' => 'Петрова Анна Ивановна',
                'phone' => '+7 (999) 123-45-67',
                'relation' => 'Мать',
                'athlete_id' => $athlete->id,
            ])
            ->assertRedirect(route('dashboard'));

        $guardianUser->refresh();
        $this->assertNotNull($guardianUser->guardian);
        $this->assertFalse($guardianUser->guardian->athletes()->where('athletes.id', $athlete->id)->exists());
    }

    public function test_guardian_can_complete_setup_without_athlete(): void
    {
        $guardianUser = User::factory()->create(['role' => 'guardian', 'is_active' => true]);

        $this->actingAs($guardianUser)
            ->post(route('guardian.store'), [
                'full_name' => 'Иванова Мария Петровна',
                'phone' => '+7 (999) 123-45-67',
                'relation' => 'Мать',
            ])
            ->assertRedirect(route('dashboard'));

        $guardianUser->refresh();
        $this->assertNotNull($guardianUser->guardian);
        $this->assertSame(0, $guardianUser->guardian->athletes()->count());
    }

    public function test_guardian_cannot_attach_athlete_from_dashboard(): void
    {
        $athleteUser = User::factory()->create(['role' => 'athlete', 'is_active' => true]);
        $athlete = Athlete::create([
            'user_id' => $athleteUser->id,
            'last_name_nom' => 'Соколов',
            'first_name_nom' => 'Артём',
            'middle_name_nom' => null,
            'birth_date' => '2013-02-02',
            'gender' => 'male',
        ]);

        $guardianUser = User::factory()->create(['role' => 'guardian', 'is_active' => true]);
        Guardian::create([
            'user_id' => $guardianUser->id,
            'full_name' => 'Соколова Анна',
            'phone' => '+7 (999) 111-22-33',
            'relation' => 'Мать',
        ]);

        $this->actingAs($guardianUser)
            ->post(route('guardian.athletes.attach'), ['athlete_id' => $athlete->id])
            ->assertForbidden();

        $this->assertFalse($guardianUser->fresh()->guardian->athletes()->where('athletes.id', $athlete->id)->exists());
    }

    public function test_admin_can_attach_guardian_to_athlete(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $athlete = Athlete::create([
            'last_name_nom' => 'Сидоров',
            'first_name_nom' => 'Сидор',
            'middle_name_nom' => null,
            'birth_date' => '2012-05-01',
            'gender' => 'male',
        ]);
        $guardianUser = User::factory()->create(['role' => 'guardian', 'is_active' => true]);
        $guardian = Guardian::create([
            'user_id' => $guardianUser->id,
            'full_name' => 'Сидорова Анна',
            'phone' => '+7 (999) 123-45-67',
            'relation' => 'Мать',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.athletes.guardians.attach', $athlete), [
                'guardian_id' => $guardian->id,
            ])
            ->assertRedirect();

        $this->assertTrue($athlete->fresh()->guardians()->where('guardians.id', $guardian->id)->exists());
    }
}
