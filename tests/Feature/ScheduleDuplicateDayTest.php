<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Location;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleDuplicateDayTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_duplicate_day_to_target_dates(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $coach = User::factory()->create(['role' => 'coach', 'is_active' => true]);
        $group = Group::create([
            'name' => 'Группа А',
            'status' => 'active',
            'type' => 'Учебная',
            'tariff_amount' => 500,
        ]);
        $location = Location::create(['name' => 'Зал 1']);

        $sourceDate = now()->addDays(3)->toDateString();
        $targetDate = now()->addDays(10)->toDateString();

        Schedule::create([
            'group_id' => $group->id,
            'location_id' => $location->id,
            'coach_id' => $coach->id,
            'initial_coach_id' => $coach->id,
            'day_of_week' => now()->addDays(3)->dayOfWeekIso,
            'lesson_date' => $sourceDate,
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'lesson_type' => 'group',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.schedule.duplicate-day'), [
                'source_date' => $sourceDate,
                'target_dates' => [$targetDate],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(2, Schedule::query()->count());
        $this->assertTrue(
            Schedule::query()
                ->where('lesson_date', $targetDate)
                ->where('group_id', $group->id)
                ->where('coach_id', $coach->id)
                ->exists()
        );
    }

    public function test_duplicate_day_fails_when_source_day_empty(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->post(route('admin.schedule.duplicate-day'), [
                'source_date' => now()->addDay()->toDateString(),
                'target_dates' => [now()->addDays(2)->toDateString()],
            ])
            ->assertSessionHasErrors('source_date');
    }
}
