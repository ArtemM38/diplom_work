<?php

namespace Tests\Feature;

use App\Models\Athlete;
use App\Models\AthleteFinance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceNegativeBalanceFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_index_filters_negative_balance(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $negative = Athlete::create([
            'last_name_nom' => 'Минус',
            'first_name_nom' => 'Баланс',
            'middle_name_nom' => null,
            'birth_date' => '2010-01-01',
            'gender' => 'male',
        ]);
        AthleteFinance::create(['athlete_id' => $negative->id, 'balance' => -50]);

        $positive = Athlete::create([
            'last_name_nom' => 'Плюс',
            'first_name_nom' => 'Баланс',
            'middle_name_nom' => null,
            'birth_date' => '2010-01-01',
            'gender' => 'male',
        ]);
        AthleteFinance::create(['athlete_id' => $positive->id, 'balance' => 100]);

        $response = $this->actingAs($admin)->get(route('admin.finance', ['balance' => 'negative']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('athletes.data', 1)
            ->where('athletes.data.0.id', $negative->id)
        );
    }
}
