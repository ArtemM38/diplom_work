<?php

namespace Tests\Feature;

use App\Models\Athlete;
use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Сценарии тестирования веб-интерфейса (Inertia + Vue).
 */
class WebInterfaceScenariosTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Сценарий 1. Страница регистрации отображает единую форму (роль, ФИО, email, пароль).
     */
    public function test_web_ui_registration_page_renders_single_step_form(): void
    {
        $response = $this->get(route('register'));

        $this->assertInertiaPage($response, 'Auth/Register');
        $content = $response->getContent();
        $this->assertStringContainsString('\/register', $content);
        $this->assertStringContainsString('Pages/Auth/Register.vue', $content);
    }

    /**
     * Сценарий 2. Администратор открывает реестр спортсменов.
     */
    public function test_web_ui_admin_athletes_registry_page(): void
    {
        $admin = $this->makeUser('admin');

        Athlete::create([
            'last_name_nom' => 'Иванов',
            'first_name_nom' => 'Иван',
            'birth_date' => '2012-01-01',
            'gender' => 'male',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.athletes'));

        $this->assertInertiaPage($response, 'Admin/AthletesList');
        $this->assertStringContainsString('athletes', $response->getContent());
    }

    /**
     * Сценарий 3. Администратор открывает страницу управления аккаунтами.
     */
    public function test_web_ui_admin_account_management_page(): void
    {
        $admin = $this->makeUser('admin');

        User::factory()->create([
            'name' => 'Тренер UI',
            'email' => 'coach-ui@example.com',
            'role' => 'coach',
            'roles' => ['coach'],
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.coaches'));

        $this->assertInertiaPage($response, 'Admin/Coaches/Index');
        $content = $response->getContent();
        $this->assertStringContainsString('roleLabels', $content);
        $this->assertStringContainsString('coach-ui@example.com', $content);
    }

    /**
     * Сценарий 4. Тренер открывает табель посещаемости с режимами просмотра.
     */
    public function test_web_ui_coach_attendance_journal_page(): void
    {
        $coach = $this->makeUser('coach');

        Group::create([
            'name' => 'Группа для табеля',
            'type' => 'Учебная',
            'tariff_amount' => 400,
            'status' => 'active',
        ]);

        $response = $this->actingAs($coach)->get(route('admin.attendance.journal'));

        $this->assertInertiaPage($response, 'Admin/Attendance/Journal');
        $content = $response->getContent();
        $this->assertStringContainsString('athletes', $content);
        $this->assertStringContainsString('groups', $content);
        $this->assertStringContainsString('calendar_month', $content);
    }

    private function assertInertiaPage(TestResponse $response, string $component): void
    {
        $response->assertOk();
        $response->assertViewIs('app');
        $this->assertStringContainsString($component, $response->getContent());
    }

    private function makeUser(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'roles' => [$role],
            'is_active' => true,
        ]);
    }
}
