<?php

namespace Tests\Feature;

use App\Mail\DocumentExpiryMail;
use App\Mail\UserNotificationMail;
use App\Models\Athlete;
use App\Models\AthleteDocument;
use App\Models\AthleteFinance;
use App\Models\Event;
use App\Models\EventHost;
use App\Models\EventLevel;
use App\Models\EventType;
use App\Models\Group;
use App\Models\Guardian;
use App\Models\Location;
use App\Models\Schedule;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\AthleteNotificationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AthleteNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mail.send_async' => false]);
    }

    public function test_document_expiry_creates_in_app_and_email_notifications(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'role' => 'athlete',
            'is_active' => true,
            'email' => 'athlete@example.com',
            'email_verified_at' => now(),
        ]);

        $athlete = Athlete::create([
            'user_id' => $user->id,
            'last_name_nom' => 'Иванов',
            'first_name_nom' => 'Иван',
            'birth_date' => '2010-01-01',
            'gender' => 'male',
        ]);

        AthleteDocument::create([
            'athlete_id' => $athlete->id,
            'type' => 'medical',
            'expiry_date' => now()->addDays(2)->toDateString(),
            'file_path' => 'athletes/documents/test.pdf',
        ]);

        $service = app(AthleteNotificationService::class);
        $count = $service->notifyDocumentsForAthlete($athlete->fresh('documents'));

        $this->assertSame(1, $count);
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $user->id,
            'type' => 'document_expiring',
        ]);

        Mail::assertSent(DocumentExpiryMail::class, fn ($mail) => $mail->hasTo('athlete@example.com'));
    }

    public function test_training_reminder_two_hours_before(): void
    {
        Mail::fake();
        Carbon::setTestNow(Carbon::parse('2026-05-24 08:00:00', 'Asia/Irkutsk'));

        $user = User::factory()->create([
            'role' => 'athlete',
            'is_active' => true,
            'email' => 'train@example.com',
            'email_verified_at' => now(),
        ]);

        $athlete = Athlete::create([
            'user_id' => $user->id,
            'last_name_nom' => 'Петров',
            'first_name_nom' => 'Пётр',
            'birth_date' => '2010-01-01',
            'gender' => 'male',
        ]);

        $group = Group::create([
            'name' => 'Группа А',
            'status' => 'active',
            'type' => 'Учебная',
            'tariff_amount' => 300,
        ]);
        $group->athletes()->attach($athlete->id, ['training_price' => 300]);

        $location = Location::create(['name' => 'Зал 1']);
        $coach = User::factory()->create(['role' => 'coach', 'is_active' => true]);

        Schedule::create([
            'group_id' => $group->id,
            'location_id' => $location->id,
            'coach_id' => $coach->id,
            'day_of_week' => 1,
            'lesson_date' => '2026-05-24',
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'lesson_type' => 'group',
        ]);

        $count = app(AthleteNotificationService::class)->notifyUpcomingTrainings();

        $this->assertSame(1, $count);
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $user->id,
            'type' => 'training_reminder',
        ]);

        Carbon::setTestNow();
    }

    public function test_schedule_created_notifies_athlete_and_guardian(): void
    {
        Mail::fake();

        $athleteUser = User::factory()->create([
            'role' => 'athlete',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $guardianUser = User::factory()->create([
            'role' => 'guardian',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $athlete = Athlete::create([
            'user_id' => $athleteUser->id,
            'last_name_nom' => 'Сидоров',
            'first_name_nom' => 'Сидор',
            'birth_date' => '2010-01-01',
            'gender' => 'male',
        ]);

        $guardian = Guardian::create([
            'user_id' => $guardianUser->id,
            'full_name' => 'Сидорова Мария',
            'phone' => '+79000000000',
            'relation' => 'mother',
        ]);
        $athlete->guardians()->attach($guardian->id);

        $group = Group::create([
            'name' => 'Группа Б',
            'status' => 'active',
            'type' => 'Учебная',
            'tariff_amount' => 300,
        ]);
        $group->athletes()->attach($athlete->id, ['training_price' => 300]);

        $location = Location::create(['name' => 'Зал 2', 'address' => 'ул. Ленина, 1']);
        $coach = User::factory()->create(['role' => 'coach', 'is_active' => true]);

        $schedule = Schedule::create([
            'group_id' => $group->id,
            'location_id' => $location->id,
            'coach_id' => $coach->id,
            'day_of_week' => 3,
            'lesson_date' => '2026-06-01',
            'start_time' => '14:00:00',
            'end_time' => '15:00:00',
            'lesson_type' => 'group',
        ]);

        app(AthleteNotificationService::class)->notifyScheduleCreated($schedule);

        Mail::assertSent(UserNotificationMail::class, fn ($mail) => $mail->hasTo($athleteUser->email));
        Mail::assertSent(UserNotificationMail::class, fn ($mail) => $mail->hasTo($guardianUser->email));

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $athleteUser->id,
            'type' => 'training_scheduled',
        ]);
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $guardianUser->id,
            'type' => 'training_scheduled',
        ]);
    }

    public function test_schedule_cancelled_notifies_athlete(): void
    {
        $user = User::factory()->create([
            'role' => 'athlete',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $athlete = Athlete::create([
            'user_id' => $user->id,
            'last_name_nom' => 'Козлов',
            'first_name_nom' => 'Козёл',
            'birth_date' => '2010-01-01',
            'gender' => 'male',
        ]);

        $group = Group::create([
            'name' => 'Группа В',
            'status' => 'active',
            'type' => 'Учебная',
            'tariff_amount' => 300,
        ]);
        $group->athletes()->attach($athlete->id, ['training_price' => 300]);

        $location = Location::create(['name' => 'Зал 3']);
        $coach = User::factory()->create(['role' => 'coach', 'is_active' => true]);

        $schedule = Schedule::create([
            'group_id' => $group->id,
            'location_id' => $location->id,
            'coach_id' => $coach->id,
            'day_of_week' => 5,
            'lesson_date' => '2026-06-10',
            'start_time' => '16:00:00',
            'end_time' => '17:00:00',
            'lesson_type' => 'group',
            'cancelled_at' => now(),
            'cancellation_reason' => 'Болезнь тренера',
        ]);

        app(AthleteNotificationService::class)->notifyScheduleCancelled($schedule);

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $user->id,
            'type' => 'training_cancelled',
        ]);

        $notification = UserNotification::where('user_id', $user->id)->first();
        $this->assertStringContainsString('Болезнь тренера', $notification->message);
    }

    public function test_event_registration_notifies_athlete_and_guardian(): void
    {
        Mail::fake();

        $athleteUser = User::factory()->create([
            'role' => 'athlete',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $guardianUser = User::factory()->create([
            'role' => 'guardian',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $athlete = Athlete::create([
            'user_id' => $athleteUser->id,
            'last_name_nom' => 'Морозов',
            'first_name_nom' => 'Михаил',
            'birth_date' => '2010-01-01',
            'gender' => 'male',
        ]);

        $guardian = Guardian::create([
            'user_id' => $guardianUser->id,
            'full_name' => 'Морозова Анна',
            'phone' => '+79000000001',
            'relation' => 'mother',
        ]);
        $athlete->guardians()->attach($guardian->id);

        $eventType = EventType::create(['name' => 'Соревнование']);
        $eventLevel = EventLevel::create(['name' => 'Городской']);
        $eventHost = EventHost::create(['full_name' => 'Федерация дзюдо']);

        $event = Event::create([
            'name' => 'Кубок Иркутска',
            'cost' => 500,
            'event_type_id' => $eventType->id,
            'event_level_id' => $eventLevel->id,
            'event_host_id' => $eventHost->id,
            'event_date' => '2026-07-15',
            'event_place' => 'ДС «Труд»',
            'status' => 'planned',
        ]);

        app(AthleteNotificationService::class)->notifyEventRegistration($event, $athlete);

        Mail::assertSent(UserNotificationMail::class, 2);

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $athleteUser->id,
            'type' => 'event_registration',
        ]);
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $guardianUser->id,
            'type' => 'event_registration',
        ]);

        $notification = UserNotification::where('user_id', $athleteUser->id)->first();
        $this->assertStringContainsString('Кубок Иркутска', $notification->message);
        $this->assertStringContainsString('ДС «Труд»', $notification->message);
    }

    public function test_in_app_notification_created_when_mail_sending_fails(): void
    {
        $user = User::factory()->create([
            'role' => 'athlete',
            'is_active' => true,
            'email' => 'fail@example.com',
            'email_verified_at' => now(),
        ]);

        Mail::shouldReceive('to')->once()->with('fail@example.com')->andReturnSelf();
        Mail::shouldReceive('send')->once()->andThrow(new \RuntimeException('SMTP connection failed'));

        app(AthleteNotificationService::class)->notifyPasswordChanged($user);

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $user->id,
            'type' => 'password_changed',
        ]);
        $this->assertDatabaseMissing('notification_dispatches', [
            'user_id' => $user->id,
            'channel' => 'mail',
        ]);
    }

    public function test_password_changed_notification(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'role' => 'coach',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        app(AthleteNotificationService::class)->notifyPasswordChanged($user);

        Mail::assertSent(UserNotificationMail::class, fn ($mail) => $mail->hasTo($user->email));

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $user->id,
            'type' => 'password_changed',
        ]);
    }

    public function test_balance_negative_notification_on_crossing_zero(): void
    {
        $user = User::factory()->create([
            'role' => 'athlete',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $athlete = Athlete::create([
            'user_id' => $user->id,
            'last_name_nom' => 'Орлов',
            'first_name_nom' => 'Олег',
            'birth_date' => '2010-01-01',
            'gender' => 'male',
        ]);

        AthleteFinance::create(['athlete_id' => $athlete->id, 'balance' => 100]);

        app(AthleteNotificationService::class)->notifyBalanceBecameNegative($athlete, 100, -50, 1);

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $user->id,
            'type' => 'balance_negative',
        ]);

        app(AthleteNotificationService::class)->notifyBalanceBecameNegative($athlete, -50, -100, 2);
        $this->assertSame(1, UserNotification::where('user_id', $user->id)->where('type', 'balance_negative')->count());
    }

    public function test_athlete_can_mark_notification_read(): void
    {
        $user = User::factory()->create([
            'role' => 'athlete',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $notification = UserNotification::create([
            'user_id' => $user->id,
            'type' => 'document_expiring',
            'title' => 'Test',
            'message' => 'Test message',
        ]);

        $this->actingAs($user)
            ->postJson(route('notifications.read', $notification))
            ->assertOk();

        $this->assertNotNull($notification->fresh()->read_at);
    }
}
