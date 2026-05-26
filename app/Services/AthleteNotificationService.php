<?php

namespace App\Services;

use App\Mail\DocumentExpiryMail;
use App\Mail\TrainingReminderMail;
use App\Models\Athlete;
use App\Models\NotificationDispatch;
use App\Models\Schedule;
use App\Models\User;
use App\Models\UserNotification;
use App\Support\AthleteDocumentStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;

class AthleteNotificationService
{
    private const TRAINING_REMINDER_MINUTES = 120;

    private const TRAINING_WINDOW_MINUTES = 12;

    public function dispatchAll(): array
    {
        $stats = ['documents' => 0, 'trainings' => 0];

        Athlete::query()
            ->with(['documents', 'user', 'groups'])
            ->whereNotNull('user_id')
            ->whereHas('user', fn ($q) => $q->where('is_active', true))
            ->chunkById(50, function ($athletes) use (&$stats) {
                foreach ($athletes as $athlete) {
                    $stats['documents'] += $this->notifyDocumentsForAthlete($athlete);
                }
            });

        $stats['trainings'] = $this->notifyUpcomingTrainings();

        return $stats;
    }

    public function notifyDocumentsForAthlete(Athlete $athlete): int
    {
        $user = $athlete->user;
        if (! $user || ! $user->is_active) {
            return 0;
        }

        $count = 0;
        foreach (AthleteDocumentStatus::expiringDocumentsForAthlete($athlete) as $doc) {
            if ($this->notifyDocument($user, $athlete, $doc)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param  array{document_id: int, type: string, label: string, status: string, days_left: int|null, expiry_date: string}  $doc
     */
    private function notifyDocument(User $user, Athlete $athlete, array $doc): bool
    {
        $type = $doc['status'] === 'expired' ? 'document_expired' : 'document_expiring';
        $dateKey = now()->format('Y-m-d');

        if ($doc['status'] === 'expired') {
            $title = 'Документ просрочен';
            $message = sprintf(
                '%s: срок действия истёк %s. Обновите документ в анкете.',
                $doc['label'],
                Carbon::parse($doc['expiry_date'])->format('d.m.Y')
            );
        } else {
            $title = 'Срок документа истекает';
            $days = $doc['days_left'];
            $message = sprintf(
                '%s: срок действия до %s (осталось %d %s).',
                $doc['label'],
                Carbon::parse($doc['expiry_date'])->format('d.m.Y'),
                $days,
                $this->daysWord($days)
            );
        }

        $dispatchKey = sprintf('doc:%s:%s:%s:%s', $user->id, $doc['document_id'], $doc['status'], $dateKey);

        $this->createInAppNotification($user, $type, $title, $message, [
            'athlete_id' => $athlete->id,
            'document_id' => $doc['document_id'],
            'document_type' => $doc['type'],
            'expiry_date' => $doc['expiry_date'],
            'status' => $doc['status'],
        ], $dispatchKey);

        $this->sendEmailIfNeeded($user, $dispatchKey, new DocumentExpiryMail(
            $user,
            $doc['label'],
            $doc['expiry_date'],
            $doc['status'],
            $doc['days_left']
        ));

        return true;
    }

    public function notifyUpcomingTrainings(): int
    {
        $now = now();
        $count = 0;

        $schedules = Schedule::query()
            ->with(['group.athletes.user', 'location', 'coach'])
            ->whereDate('lesson_date', '>=', $now->toDateString())
            ->whereDate('lesson_date', '<=', $now->copy()->addDay()->toDateString())
            ->get();

        foreach ($schedules as $schedule) {
            $lessonAt = Carbon::parse(
                $schedule->lesson_date . ' ' . $schedule->start_time,
                config('app.timezone')
            );

            $minutesUntil = $now->diffInMinutes($lessonAt, false);
            if ($minutesUntil < self::TRAINING_REMINDER_MINUTES - self::TRAINING_WINDOW_MINUTES
                || $minutesUntil > self::TRAINING_REMINDER_MINUTES + self::TRAINING_WINDOW_MINUTES) {
                continue;
            }

            $athleteIds = $schedule->group?->athletes()->pluck('athletes.id') ?? collect();
            if ($athleteIds->isEmpty()) {
                continue;
            }

            $athletes = Athlete::query()
                ->with('user')
                ->whereIn('id', $athleteIds)
                ->whereNotNull('user_id')
                ->get();

            foreach ($athletes as $athlete) {
                $user = $athlete->user;
                if (! $user || ! $user->is_active) {
                    continue;
                }

                if ($this->notifyTraining($user, $athlete, $schedule, $lessonAt)) {
                    $count++;
                }
            }
        }

        return $count;
    }

    private function notifyTraining(User $user, Athlete $athlete, Schedule $schedule, Carbon $lessonAt): bool
    {
        $dispatchKey = sprintf('training:%s:%s:2h', $user->id, $schedule->id);

        $groupName = $schedule->group?->name ?? 'группа';
        $locationName = $schedule->location?->name ?? 'зал';
        $timeLabel = $lessonAt->format('d.m.Y H:i');

        $title = 'Напоминание о тренировке';
        $message = sprintf(
            'Через 2 часа тренировка: %s, %s, %s.',
            $groupName,
            $locationName,
            $timeLabel
        );

        $this->createInAppNotification($user, 'training_reminder', $title, $message, [
            'athlete_id' => $athlete->id,
            'schedule_id' => $schedule->id,
            'lesson_at' => $lessonAt->toIso8601String(),
            'group' => $groupName,
            'location' => $locationName,
        ], $dispatchKey);

        $this->sendEmailIfNeeded($user, $dispatchKey, new TrainingReminderMail(
            $user,
            $groupName,
            $locationName,
            $lessonAt,
            $schedule->coach?->name
        ));

        return true;
    }

    private function createInAppNotification(
        User $user,
        string $type,
        string $title,
        string $message,
        array $data,
        string $dispatchKey
    ): void {
        $inAppKey = $dispatchKey . ':in_app';

        if (NotificationDispatch::where('dispatch_key', $inAppKey)->exists()) {
            return;
        }

        UserNotification::create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data,
        ]);

        NotificationDispatch::create([
            'user_id' => $user->id,
            'dispatch_key' => $inAppKey,
            'channel' => 'database',
            'sent_at' => now(),
        ]);
    }

    private function sendEmailIfNeeded(User $user, string $dispatchKey, $mailable): void
    {
        $emailKey = $dispatchKey . ':mail';

        if (NotificationDispatch::where('dispatch_key', $emailKey)->exists()) {
            return;
        }

        if (! $user->email) {
            return;
        }

        Mail::to($user->email)->send($mailable);

        NotificationDispatch::create([
            'user_id' => $user->id,
            'dispatch_key' => $emailKey,
            'channel' => 'mail',
            'sent_at' => now(),
        ]);
    }

    private function daysWord(int $days): string
    {
        $mod100 = $days % 100;
        if ($mod100 >= 11 && $mod100 <= 14) {
            return 'дней';
        }

        return match ($days % 10) {
            1 => 'день',
            2, 3, 4 => 'дня',
            default => 'дней',
        };
    }
}
