<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'login' => 'test.user',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'athlete',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('athlete.create', absolute: false));
    }

    public function test_user_can_register_without_email(): void
    {
        $response = $this->post('/register', [
            'name' => 'Child User',
            'login' => 'child.one',
            'email' => '',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'athlete',
        ]);

        $response->assertRedirect(route('athlete.create', absolute: false));
        $this->assertDatabaseHas('users', [
            'login' => 'child.one',
            'email' => null,
        ]);
    }

    public function test_duplicate_email_is_rejected(): void
    {
        User::factory()->create([
            'login' => 'parent.one',
            'email' => 'family@example.com',
        ]);

        $response = $this->post('/register', [
            'name' => 'Child User',
            'login' => 'child.one',
            'email' => 'family@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'athlete',
        ]);

        $response->assertSessionHasErrors('email');
    }
}
