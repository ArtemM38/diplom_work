<?php

namespace Tests\Feature;

use App\Models\Athlete;
use App\Models\Attendance;
use App\Models\Group;
use App\Models\Guardian;
use App\Models\Location;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuardianChildViewsTest extends TestCase
{
    use RefreshDatabase;

    private function guardianWithChild(): array
    {
        $user = User::factory()->create(['role' => 'guardian', 'is_active' => true]);
        $guardian = Guardian::create([
            'user_id' => $user->id,
            'full_name' => 'Родитель Тестов',
            'phone' => '+7 (999) 111-22-33',
            'relation' => 'Отец',
        ]);
        $child = Athlete::create([
            'last_name_nom' => 'Ребёнок',
            'first_name_nom' => 'Тест',
            'middle_name_nom' => null,
            'birth_date' => '2012-01-01',
            'gender' => 'male',
        ]);
        $guardian->athletes()->attach($child->id);

        return [$user, $child];
    }

    public function test_guardian_without_children_sees_empty_state_not_404(): void
    {
        $user = User::factory()->create(['role' => 'guardian', 'is_active' => true]);
        Guardian::create([
            'user_id' => $user->id,
            'full_name' => 'Родитель Без Детей',
            'phone' => '+7 (999) 000-00-00',
            'relation' => 'Мать',
        ]);

        $this->actingAs($user)
            ->get(route('guardian.schedule'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Athlete/ScheduleCalendar')
                ->where('noChildren', true)
            );

        $this->actingAs($user)
            ->get(route('guardian.attendance'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Guardian/ChildAttendance')
                ->where('noChildren', true)
            );

        $this->actingAs($user)
            ->get(route('finance'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Finance/Show')
                ->where('noChildren', true)
            );
    }

    public function test_guardian_can_view_child_schedule(): void
    {
        [$user, $child] = $this->guardianWithChild();
        $coach = User::factory()->create(['role' => 'coach', 'is_active' => true]);
        $location = Location::create(['name' => 'Зал 1']);
        $group = Group::create(['name' => 'Группа А', 'status' => 'active', 'type' => 'Учебная']);
        $child->groups()->attach($group->id);

        Schedule::create([
            'group_id' => $group->id,
            'location_id' => $location->id,
            'coach_id' => $coach->id,
            'lesson_date' => '2026-05-20',
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'day_of_week' => 2,
            'lesson_type' => 'group',
        ]);

        $this->actingAs($user)
            ->get(route('guardian.schedule', ['athlete_id' => $child->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Athlete/ScheduleCalendar')
                ->where('isGuardian', true)
                ->where('selectedAthlete.id', $child->id)
                ->has('schedules', 1)
            );
    }

    public function test_guardian_cannot_view_unrelated_child_schedule(): void
    {
        [$user] = $this->guardianWithChild();
        $stranger = Athlete::create([
            'last_name_nom' => 'Чужой',
            'first_name_nom' => 'Ребёнок',
            'middle_name_nom' => null,
            'birth_date' => '2012-01-01',
            'gender' => 'male',
        ]);

        $this->actingAs($user)
            ->get(route('guardian.schedule', ['athlete_id' => $stranger->id]))
            ->assertForbidden();
    }

    public function test_athlete_can_view_own_attendance_journal(): void
    {
        $user = User::factory()->create(['role' => 'athlete', 'is_active' => true]);
        $child = Athlete::create([
            'user_id' => $user->id,
            'last_name_nom' => 'Спорт',
            'first_name_nom' => 'Смен',
            'middle_name_nom' => null,
            'birth_date' => '2010-01-01',
            'gender' => 'male',
        ]);

        $this->actingAs($user)
            ->get(route('athlete.attendance'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Guardian/ChildAttendance')
                ->where('isGuardian', false)
                ->where('selectedAthlete.id', $child->id)
            );
    }

    public function test_guardian_can_view_child_attendance_journal(): void
    {
        [$user, $child] = $this->guardianWithChild();
        $coach = User::factory()->create(['role' => 'coach', 'is_active' => true]);
        $location = Location::create(['name' => 'Зал 2']);
        $group = Group::create(['name' => 'Группа Б', 'status' => 'active', 'type' => 'Учебная']);
        $schedule = Schedule::create([
            'group_id' => $group->id,
            'location_id' => $location->id,
            'coach_id' => $coach->id,
            'lesson_date' => now()->format('Y-m-d'),
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'day_of_week' => (int) now()->dayOfWeekIso,
            'lesson_type' => 'group',
        ]);

        Attendance::create([
            'athlete_id' => $child->id,
            'schedule_id' => $schedule->id,
            'status' => 'Я',
        ]);

        $this->actingAs($user)
            ->get(route('guardian.attendance', ['athlete_id' => $child->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Guardian/ChildAttendance')
                ->where('selectedAthlete.id', $child->id)
                ->where('stats.present', 1)
            );
    }
}
