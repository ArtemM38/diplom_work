<?php

namespace Tests\Feature;

use App\Models\Athlete;
use App\Models\AthleteFinance;
use App\Models\Group;
use App\Models\Location;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AttendanceRefundTest extends TestCase
{
    use RefreshDatabase;

    public function test_excused_absence_refunds_training_charge(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $coach = User::factory()->create([
            'role' => 'coach',
            'is_active' => true,
        ]);

        $athlete = Athlete::create([
            'last_name_nom' => 'Иванов',
            'first_name_nom' => 'Иван',
            'middle_name_nom' => 'Иванович',
            'birth_date' => '2011-05-05',
            'gender' => 'male',
        ]);

        $group = Group::create([
            'name' => 'Группа Б',
            'status' => 'active',
            'type' => 'Учебная',
            'tariff_amount' => 400,
        ]);

        $location = Location::create(['name' => 'Зал 2']);

        $this->actingAs($admin)->post(route('admin.groups.attach', $group), [
            'athlete_id' => $athlete->id,
        ])->assertRedirect();

        AthleteFinance::updateOrCreate(['athlete_id' => $athlete->id], [
            'athlete_id' => $athlete->id,
            'balance' => 1000,
        ]);

        $schedule = Schedule::create([
            'group_id' => $group->id,
            'location_id' => $location->id,
            'coach_id' => $coach->id,
            'day_of_week' => 1,
            'lesson_date' => now()->subDay()->toDateString(),
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'lesson_type' => 'group',
        ]);

        $this->actingAs($admin)->post(route('admin.attendance.store', $schedule), [
            'attendance' => [
                $athlete->id => 'Я',
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('athlete_finances', [
            'athlete_id' => $athlete->id,
            'balance' => 600.00,
        ]);

        $this->actingAs($admin)->post(route('admin.attendance.store', $schedule), [
            'attendance' => [
                $athlete->id => 'У',
            ],
            'certificates' => [
                $athlete->id => UploadedFile::fake()->create('spravka.pdf', 100, 'application/pdf'),
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('athlete_finances', [
            'athlete_id' => $athlete->id,
            'balance' => 1000.00,
        ]);

        $this->assertDatabaseHas('athlete_balance_histories', [
            'athlete_id' => $athlete->id,
            'change_amount' => 400.00,
            'status' => 'У',
        ]);
    }
}
