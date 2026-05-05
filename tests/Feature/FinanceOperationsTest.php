<?php

namespace Tests\Feature;

use App\Models\Athlete;
use App\Models\AthleteFinance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_add_and_subtract_balance_with_history(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $athlete = Athlete::create([
            'last_name_nom' => 'Иванов',
            'first_name_nom' => 'Иван',
            'middle_name_nom' => 'Иванович',
            'birth_date' => '2012-01-01',
            'gender' => 'male',
        ]);

        AthleteFinance::create([
            'athlete_id' => $athlete->id,
            'balance' => 1000,
            'training_price' => 0,
        ]);

        $this->actingAs($admin)->patch(route('admin.finance.update', $athlete), [
            'operation' => 'add',
            'amount' => 300,
            'reason' => 'Пополнение',
        ])->assertRedirect();

        $this->assertDatabaseHas('athlete_finances', [
            'athlete_id' => $athlete->id,
            'balance' => 1300.00,
        ]);

        $this->actingAs($admin)->patch(route('admin.finance.update', $athlete), [
            'operation' => 'subtract',
            'amount' => 200,
            'reason' => 'Списание',
        ])->assertRedirect();

        $this->assertDatabaseHas('athlete_finances', [
            'athlete_id' => $athlete->id,
            'balance' => 1100.00,
        ]);

        $this->assertDatabaseHas('athlete_balance_histories', [
            'athlete_id' => $athlete->id,
            'change_amount' => 300.00,
            'reason' => 'Пополнение',
        ]);

        $this->assertDatabaseHas('athlete_balance_histories', [
            'athlete_id' => $athlete->id,
            'change_amount' => -200.00,
            'reason' => 'Списание',
        ]);
    }
}
