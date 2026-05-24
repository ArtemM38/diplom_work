<?php

namespace Tests\Feature;

use App\Models\Athlete;
use App\Models\AthleteRankHistory;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\EventType;
use App\Models\Rank;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventRankSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigning_rank_in_event_updates_athlete_rank_history(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $rankOld = Rank::create(['name' => '3 юношеский']);
        $rankNew = Rank::create(['name' => '2 юношеский']);

        $athlete = Athlete::create([
            'last_name_nom' => 'Сидоров',
            'first_name_nom' => 'Сидор',
            'birth_date' => '2010-01-01',
            'gender' => 'male',
        ]);

        $athlete->rankHistories()->create([
            'rank_id' => $rankOld->id,
            'assigned_at' => '2024-01-01',
        ]);

        $eventType = EventType::create(['name' => 'Аттестация']);

        $event = Event::create([
            'name' => 'Весенняя аттестация',
            'cost' => 500,
            'event_type_id' => $eventType->id,
            'event_date' => '2026-05-20',
            'status' => 'planned',
        ]);

        $participant = EventParticipant::create([
            'event_id' => $event->id,
            'athlete_id' => $athlete->id,
        ]);

        $this->actingAs($admin)->post(route('admin.events.results.update', $event), [
            '_method' => 'patch',
            'status' => 'completed',
            'participants' => [
                [
                    'id' => $participant->id,
                    'result_rank_id' => $rankNew->id,
                ],
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('athlete_rank_histories', [
            'athlete_id' => $athlete->id,
            'rank_id' => $rankNew->id,
            'event_participant_id' => $participant->id,
        ]);

        $current = $athlete->fresh()->rankHistories()->orderByDesc('assigned_at')->first();
        $this->assertSame($rankNew->id, $current->rank_id);
    }
}
