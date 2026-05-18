<?php

namespace Tests\Feature;

use App\Models\Athlete;
use App\Models\AthleteBalanceHistory;
use App\Models\AthleteFinance;
use App\Models\Guardian;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_athlete_can_view_own_finances_read_only(): void
    {
        $user = User::factory()->create(['role' => 'athlete', 'is_active' => true]);
        $athlete = Athlete::create([
            'user_id' => $user->id,
            'last_name_nom' => 'Петров',
            'first_name_nom' => 'Пётр',
            'middle_name_nom' => null,
            'birth_date' => '2010-05-01',
            'gender' => 'male',
        ]);

        AthleteFinance::create(['athlete_id' => $athlete->id, 'balance' => 500]);
        AthleteBalanceHistory::create([
            'athlete_id' => $athlete->id,
            'change_amount' => 500,
            'balance_before' => 0,
            'balance_after' => 500,
            'reason' => 'Пополнение',
            'status' => 'manual',
        ]);

        $this->actingAs($user)
            ->get(route('finance'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Finance/Show')
                ->where('selectedAthlete.balance', 500)
                ->where('isGuardian', false)
            );
    }

    public function test_guardian_can_view_linked_child_finances(): void
    {
        $user = User::factory()->create(['role' => 'guardian', 'is_active' => true]);
        $guardian = Guardian::create([
            'user_id' => $user->id,
            'full_name' => 'Иванова Мария',
            'phone' => '+7 (999) 111-22-33',
            'relation' => 'Мать',
        ]);

        $child = Athlete::create([
            'last_name_nom' => 'Иванов',
            'first_name_nom' => 'Сергей',
            'middle_name_nom' => null,
            'birth_date' => '2012-01-01',
            'gender' => 'male',
        ]);

        $guardian->athletes()->attach($child->id);
        AthleteFinance::create(['athlete_id' => $child->id, 'balance' => 1200]);

        $this->actingAs($user)
            ->get(route('finance'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Finance/Show')
                ->where('isGuardian', true)
                ->where('selectedAthlete.id', $child->id)
                ->where('selectedAthlete.balance', 1200)
                ->has('children', 1)
            );
    }

    public function test_guardian_cannot_view_unrelated_athlete_finances(): void
    {
        $user = User::factory()->create(['role' => 'guardian', 'is_active' => true]);
        $guardian = Guardian::create([
            'user_id' => $user->id,
            'full_name' => 'Иванова Мария',
            'phone' => '+7 (999) 111-22-33',
            'relation' => 'Мать',
        ]);

        $ownChild = Athlete::create([
            'last_name_nom' => 'Свой',
            'first_name_nom' => 'Ребёнок',
            'middle_name_nom' => null,
            'birth_date' => '2012-01-01',
            'gender' => 'male',
        ]);
        $guardian->athletes()->attach($ownChild->id);

        $stranger = Athlete::create([
            'last_name_nom' => 'Чужой',
            'first_name_nom' => 'Ребёнок',
            'middle_name_nom' => null,
            'birth_date' => '2012-01-01',
            'gender' => 'male',
        ]);

        $this->actingAs($user)
            ->get(route('finance', ['athlete_id' => $stranger->id]))
            ->assertForbidden();
    }

    public function test_coach_cannot_access_finance_view_route(): void
    {
        $coach = User::factory()->create(['role' => 'coach', 'is_active' => true]);

        $this->actingAs($coach)
            ->get(route('finance'))
            ->assertForbidden();
    }
}
