<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_staff_account_with_roles(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'roles' => ['admin'],
            'is_active' => true,
        ]);

        $email = 'new-coach-' . uniqid() . '@example.com';

        $this->actingAs($admin)->post(route('admin.coaches.store'), [
            'name' => 'Новый Тренер',
            'email' => $email,
            'password' => 'password123',
            'roles' => ['coach'],
            'is_active' => true,
        ])->assertRedirect();

        $this->assertDatabaseHas('users', [
            'email' => $email,
            'name' => 'Новый Тренер',
            'role' => 'coach',
            'is_active' => 1,
        ]);

        $user = User::where('email', $email)->first();
        $this->assertNotNull($user);
        $this->assertSame(['coach'], $user->getRolesList());
    }
}
