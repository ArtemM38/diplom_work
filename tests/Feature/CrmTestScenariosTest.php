<?php

namespace Tests\Feature;

use App\Models\Athlete;
use App\Models\EventType;
use App\Models\Group;
use App\Models\PortfolioAchievement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Набор функциональных сценариев для CRM «Айкидо» (ПП).
 *
 * Покрытие:
 * - 3 позитивных сценария основного функционала
 * - 1 негативный сценарий валидации данных
 * - 2 негативных сценария проверки ролей
 * - 1 сценарий тестирования веб-интерфейса (Inertia)
 */
class CrmTestScenariosTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Позитивный сценарий 1.
     * Администратор создаёт группу, зачисляет спортсмена и видит его в составе.
     */
    public function test_positive_admin_creates_group_and_enrolls_athlete(): void
    {
        $admin = $this->makeUser('admin');

        $athlete = Athlete::create([
            'last_name_nom' => 'Сидоров',
            'first_name_nom' => 'Сидор',
            'middle_name_nom' => 'Сидорович',
            'birth_date' => '2012-03-15',
            'gender' => 'male',
        ]);

        $this->actingAs($admin)->post(route('admin.groups.store'), [
            'name' => 'Группа начинающих',
            'type' => 'Учебная',
            'tariff_amount' => 400,
        ])->assertRedirect();

        $group = Group::query()->where('name', 'Группа начинающих')->first();
        $this->assertNotNull($group);

        $this->actingAs($admin)->post(route('admin.groups.attach', $group), [
            'athlete_id' => $athlete->id,
        ])->assertRedirect();

        $this->assertTrue($group->fresh()->athletes()->where('athletes.id', $athlete->id)->exists());
    }

    /**
     * Позитивный сценарий 2.
     * Бухгалтер изменяет стоимость тренировки в группе (разрешённый функционал роли).
     */
    public function test_positive_accountant_updates_group_training_price(): void
    {
        $accountant = $this->makeUser('accountant');

        $group = Group::create([
            'name' => 'Группа Б',
            'type' => 'Спортивная',
            'tariff_amount' => 500,
            'status' => 'active',
        ]);

        $this->actingAs($accountant)->patch(route('admin.groups.update', $group), [
            'tariff_amount' => 750,
        ])->assertRedirect();

        $this->assertDatabaseHas('groups', [
            'id' => $group->id,
            'tariff_amount' => 750,
        ]);
    }

    /**
     * Позитивный сценарий 3.
     * Спортсмен с заполненной анкетой открывает своё портфолио достижений.
     */
    public function test_positive_athlete_can_view_own_portfolio(): void
    {
        $athleteUser = $this->makeUser('athlete');
        $athlete = Athlete::create([
            'user_id' => $athleteUser->id,
            'last_name_nom' => 'Козлов',
            'first_name_nom' => 'Артём',
            'middle_name_nom' => null,
            'birth_date' => '2011-08-20',
            'gender' => 'male',
        ]);

        $eventType = EventType::create(['name' => 'Турнир']);

        PortfolioAchievement::create([
            'athlete_id' => $athlete->id,
            'event_type_id' => $eventType->id,
            'event_name' => 'Кубок города',
            'event_date' => '2025-04-10',
            'result_place' => 1,
        ]);

        $response = $this->actingAs($athleteUser)->get(route('athlete.portfolio'));

        $response->assertOk();
        $this->assertStringContainsString('Athlete/Portfolio', $response->getContent());
    }

    /**
     * Негативный сценарий (валидация).
     * При выборе «Учусь» без данных об ОО анкета спортсмена не сохраняется.
     */
    public function test_negative_validation_rejects_incomplete_study_profile(): void
    {
        $athleteUser = $this->makeUser('athlete');

        $response = $this->actingAs($athleteUser)->post(route('athlete.store'), [
            'last_name_nom' => 'Новиков',
            'first_name_nom' => 'Илья',
            'middle_name_nom' => 'Петрович',
            'birth_date' => '2013-01-10',
            'gender' => 'male',
            'occupation_type' => 'study',
            'school_name' => '',
            'school_director_dat' => '',
            'school_class' => '',
        ]);

        $response->assertSessionHasErrors(['school_name', 'school_director_dat', 'school_class']);
        $this->assertDatabaseMissing('athletes', [
            'last_name_nom' => 'Новиков',
            'first_name_nom' => 'Илья',
        ]);
    }

    /**
     * Негативный сценарий (роли) 1.
     * Спортсмен не может получить доступ к реестру спортсменов (админ-панель).
     */
    public function test_negative_role_athlete_cannot_access_admin_athletes_registry(): void
    {
        $athleteUser = $this->makeUser('athlete');
        Athlete::create([
            'user_id' => $athleteUser->id,
            'last_name_nom' => 'Орлов',
            'first_name_nom' => 'Максим',
            'birth_date' => '2010-05-05',
            'gender' => 'male',
        ]);

        $this->actingAs($athleteUser)
            ->get(route('admin.athletes'))
            ->assertForbidden();
    }

    /**
     * Негативный сценарий (роли) 2.
     * Бухгалтер не может создавать новые группы (только изменение тарифа).
     */
    public function test_negative_role_accountant_cannot_create_group(): void
    {
        $accountant = $this->makeUser('accountant');

        $this->actingAs($accountant)->post(route('admin.groups.store'), [
            'name' => 'Запрещённая группа',
            'type' => 'Учебная',
            'tariff_amount' => 300,
        ])->assertForbidden();

        $this->assertDatabaseMissing('groups', [
            'name' => 'Запрещённая группа',
        ]);
    }

    /**
     * Сценарий тестирования веб-интерфейса.
     * Администратор открывает страницу «Группы и секции» — отдаётся Inertia-компонент списка групп.
     */
    public function test_web_ui_admin_groups_page_renders_inertia_component(): void
    {
        $admin = $this->makeUser('admin');

        Group::create([
            'name' => 'UI-группа',
            'type' => 'Учебная',
            'tariff_amount' => 350,
            'status' => 'active',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.groups'));

        $response->assertOk();
        $response->assertViewIs('app');
        $content = $response->getContent();
        $this->assertStringContainsString('Admin/Groups/Index', $content);
        $this->assertStringContainsString('canCreateGroups', $content);
        $this->assertStringContainsString('tariffOnlyMode', $content);
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
