<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_user_data_successfully(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'role' => 'coach',
            'is_active' => true,
            'email' => 'old@example.com',
            'name' => 'Old Name',
        ]);

        $this->actingAs($admin)->patch(route('admin.coaches.update', $user), [
            'name' => 'New Name',
            'login' => $user->login,
            'email' => 'new@example.com',
            'roles' => ['accountant'],
            'is_active' => false,
            'password' => '',
        ])->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'New Name',
            'email' => 'new@example.com',
            'is_active' => 0,
        ]);

        $user->refresh();
        $this->assertTrue($user->hasRole('accountant'));
    }
}
