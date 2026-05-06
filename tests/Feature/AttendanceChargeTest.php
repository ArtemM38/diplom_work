<?php

namespace Tests\Feature;

use App\Models\Athlete;
use App\Models\AthleteFinance;
use App\Models\Group;
use App\Models\Location;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceChargeTest extends TestCase
{
    use RefreshDatabase;

    public function test_attendance_charges_balance_by_group_training_price(): void
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
            'last_name_nom' => 'Петров',
            'first_name_nom' => 'Петр',
            'middle_name_nom' => 'Петрович',
            'birth_date' => '2011-05-05',
            'gender' => 'male',
        ]);

        $group = Group::create([
            'name' => 'Группа А',
            'status' => 'active',
            'type' => 'Учебная',
            'tariff_amount' => 500,
        ]);

        $location = Location::create(['name' => 'Зал 1']);

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
            'balance' => 500.00,
        ]);

        $this->assertDatabaseHas('athlete_balance_histories', [
            'athlete_id' => $athlete->id,
            'change_amount' => -500.00,
            'status' => 'Я',
        ]);
    }
}
