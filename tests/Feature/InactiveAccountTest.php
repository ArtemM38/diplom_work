<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InactiveAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_user_sees_blocking_screen_after_login(): void
    {
        $user = User::factory()->create([
            'email' => 'inactive@example.com',
            'password' => bcrypt('password'),
            'is_active' => false,
            'role' => 'coach',
        ]);

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Аккаунт неактивен');
    }
}
