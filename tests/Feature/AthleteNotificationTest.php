<?php

namespace Tests\Feature;

use App\Mail\DocumentExpiryMail;
use App\Models\Athlete;
use App\Models\AthleteDocument;
use App\Models\Group;
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
