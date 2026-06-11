<?php

namespace App\Services;

use App\Jobs\DeliverNotificationEmail;
use App\Mail\DocumentExpiryMail;
use App\Mail\TrainingReminderMail;
use App\Mail\UserNotificationMail;
use Illuminate\Contracts\Mail\Mailable;
use App\Models\Athlete;
use App\Models\Event;
use App\Models\NotificationDispatch;
use App\Models\Schedule;
use App\Models\User;
use App\Models\UserNotification;
use App\Support\AthleteDocumentStatus;
use App\Support\DateFormatter;
use App\Support\GuardianChildAccess;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

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
        ], $dispatchKey, sendEmail: false);

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
            $lessonAt = DateFormatter::toDateTime($schedule->lesson_date, $schedule->start_time);

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
        ], $dispatchKey, sendEmail: false);

        $this->sendEmailIfNeeded($user, $dispatchKey, new TrainingReminderMail(
            $user,
            $groupName,
            $locationName,
            $lessonAt,
            $schedule->coach?->name
        ));

        return true;
    }

    public function notifyEventRegistration(Event $event, Athlete $athlete): void
    {
        $event->loadMissing(['eventType', 'eventLevel', 'eventHost']);
        $athlete->loadMissing(['user', 'guardians.user']);

        $details = $this->eventDetailsMessage($event);
        $title = 'Регистрация на мероприятие';
        $type = 'event_registration';

        $data = [
            'event_id' => $event->id,
            'athlete_id' => $athlete->id,
            'event_name' => $event->name,
            'event_date' => $event->event_date?->format('Y-m-d'),
        ];

        $athleteUser = $athlete->user;
        if ($athleteUser?->is_active) {
            $dispatchKey = sprintf('event_registration:%s:%s:%s', $athleteUser->id, $event->id, $athlete->id);
            $this->createInAppNotification(
                $athleteUser,
                $type,
                $title,
                'Вас зарегистрировали на мероприятие: '.$details,
                $data,
                $dispatchKey
            );
        }

        $childName = GuardianChildAccess::fullName($athlete);
        foreach ($athlete->guardians as $guardian) {
            $guardianUser = $guardian->user;
            if (! $guardianUser?->is_active) {
                continue;
            }

            $dispatchKey = sprintf('event_registration:%s:%s:%s:%s', $guardianUser->id, $event->id, $athlete->id, 'guardian');
            $this->createInAppNotification(
                $guardianUser,
                $type,
                $title,
                sprintf('Ребёнка (%s) зарегистрировали на мероприятие: %s', $childName, $details),
                $data,
                $dispatchKey
            );
        }
    }

    public function notifyScheduleCreated(Schedule $schedule): void
    {
        $this->notifyScheduleEvent($schedule, 'training_scheduled', 'Назначена тренировка');
    }

    public function notifyScheduleCancelled(Schedule $schedule): void
    {
        $this->notifyScheduleEvent($schedule, 'training_cancelled', 'Тренировка отменена', true);
    }

    public function notifyScheduleUpdated(Schedule $schedule): void
    {
        $this->notifyScheduleEvent($schedule, 'training_updated', 'Изменена тренировка');
    }

    public function notifyPasswordChanged(User $user): void
    {
        if (! $user->is_active) {
            return;
        }

        $dispatchKey = sprintf('password_changed:%s:%s', $user->id, now()->format('Y-m-d-His'));

        $this->createInAppNotification(
            $user,
            'password_changed',
            'Пароль изменён',
            'Пароль вашего аккаунта был изменён. Если это были не вы, немедленно свяжитесь с администрацией.',
            [],
            $dispatchKey
        );
    }

    public function notifyBalanceBecameNegative(Athlete $athlete, float $before, float $after, ?int $balanceHistoryId = null): void
    {
        if (! ($before >= 0 && $after < 0)) {
            return;
        }

        $athlete->loadMissing(['user', 'guardians.user']);
        $childName = GuardianChildAccess::fullName($athlete);
        $balanceLabel = number_format($after, 2, ',', ' ').' ₽';
        $suffix = $balanceHistoryId ?? now()->timestamp;

        if ($athlete->user?->is_active) {
            $dispatchKey = sprintf('balance_negative:%s:%s:%s', $athlete->user->id, $athlete->id, $suffix);
            $this->createInAppNotification(
                $athlete->user,
                'balance_negative',
                'Отрицательный баланс',
                sprintf('Баланс стал отрицательным: %s. Пополните счёт.', $balanceLabel),
                ['athlete_id' => $athlete->id, 'balance' => $after],
                $dispatchKey
            );
        }

        foreach ($athlete->guardians as $guardian) {
            $guardianUser = $guardian->user;
            if (! $guardianUser?->is_active) {
                continue;
            }

            $dispatchKey = sprintf('balance_negative:%s:%s:%s:%s', $guardianUser->id, $athlete->id, $suffix, 'guardian');
            $this->createInAppNotification(
                $guardianUser,
                'balance_negative',
                'Отрицательный баланс',
                sprintf('Баланс ребёнка (%s) стал отрицательным: %s.', $childName, $balanceLabel),
                ['athlete_id' => $athlete->id, 'balance' => $after],
                $dispatchKey
            );
        }
    }

    private function eventDetailsMessage(Event $event): string
    {
        $parts = [sprintf('«%s»', $event->name)];

        $dateLabel = $event->event_date_display
            ?? ($event->event_date ? DateFormatter::toDisplayDate($event->event_date) : null);
        if ($dateLabel) {
            $parts[] = 'дата '.$dateLabel;
        }
        if ($event->event_period) {
            $parts[] = $event->event_period;
        }
        if ($event->event_place) {
            $parts[] = 'место: '.$event->event_place;
        }
        if ($event->eventType?->name) {
            $parts[] = 'тип: '.$event->eventType->name;
        }
        if ($event->eventLevel?->name) {
            $parts[] = 'уровень: '.$event->eventLevel->name;
        }
        if ($event->eventHost?->full_name) {
            $parts[] = 'организатор: '.$event->eventHost->full_name;
        }

        return implode(', ', $parts).'.';
    }

    private function notifyScheduleEvent(
        Schedule $schedule,
        string $type,
        string $title,
        bool $includeCancellationReason = false
    ): void {
        $schedule->loadMissing(['group.athletes.user', 'group.athletes.guardians.user', 'location', 'coach']);

        if (! $schedule->group) {
            return;
        }

        $lessonAt = DateFormatter::toDateTime($schedule->lesson_date, $schedule->start_time);
        $endTime = $schedule->end_time
            ? Carbon::parse($schedule->end_time)->format('H:i')
            : null;
        $timeRange = $endTime
            ? $lessonAt->format('d.m.Y').' '.$lessonAt->format('H:i').'–'.$endTime
            : $lessonAt->format('d.m.Y H:i');

        $groupName = $schedule->group->name ?? 'группа';
        $locationName = $schedule->location?->name ?? 'зал';
        $address = $schedule->location?->address;
        $coachName = $schedule->coach?->name ?? '—';

        $locationPart = $address
            ? sprintf('зал «%s», %s', $locationName, $address)
            : sprintf('зал «%s»', $locationName);

        $details = sprintf(
            '%s: %s, %s, тренер %s.',
            $timeRange,
            $groupName,
            $locationPart,
            $coachName
        );

        if ($includeCancellationReason && $schedule->cancellation_reason) {
            $details .= ' Причина: '.$schedule->cancellation_reason;
        }

        $data = [
            'schedule_id' => $schedule->id,
            'group' => $groupName,
            'location' => $locationName,
            'address' => $address,
            'lesson_at' => $lessonAt->toIso8601String(),
        ];

        foreach ($schedule->group->athletes as $athlete) {
            $athleteUser = $athlete->user;
            if ($athleteUser?->is_active) {
                $dispatchKey = sprintf('%s:%s:%s', $type, $athleteUser->id, $schedule->id);
                $this->createInAppNotification(
                    $athleteUser,
                    $type,
                    $title,
                    $details,
                    array_merge($data, ['athlete_id' => $athlete->id]),
                    $dispatchKey
                );
            }

            $childName = GuardianChildAccess::fullName($athlete);
            foreach ($athlete->guardians as $guardian) {
                $guardianUser = $guardian->user;
                if (! $guardianUser?->is_active) {
                    continue;
                }

                $guardianMessage = sprintf('(%s) %s', $childName, $details);
                $dispatchKey = sprintf('%s:%s:%s:%s', $type, $guardianUser->id, $schedule->id, $athlete->id);
                $this->createInAppNotification(
                    $guardianUser,
                    $type,
                    $title,
                    $guardianMessage,
                    array_merge($data, ['athlete_id' => $athlete->id]),
                    $dispatchKey
                );
            }
        }
    }

    private function createInAppNotification(
        User $user,
        string $type,
        string $title,
        string $message,
        array $data,
        string $dispatchKey,
        bool $sendEmail = true,
    ): void {
        $inAppKey = $dispatchKey.':in_app';

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

        if ($sendEmail) {
            $this->sendEmailIfNeeded(
                $user,
                $dispatchKey,
                new UserNotificationMail(
                    $user,
                    $title.' — '.config('app.name'),
                    $title,
                    $message,
                    $this->actionUrlFor($user, $type),
                    $this->actionLabelFor($user, $type),
                )
            );
        }
    }

    private function actionUrlFor(User $user, string $type): ?string
    {
        $isGuardian = $user->hasRole('guardian') && ! $user->hasRole('athlete');

        return match ($type) {
            'training_scheduled', 'training_cancelled', 'training_reminder' => $isGuardian
                ? url('/guardian/schedule')
                : url('/athlete/schedule-calendar'),
            'event_registration' => $isGuardian
                ? url('/dashboard')
                : url('/athlete/portfolio'),
            'balance_negative' => url('/finance'),
            'document_expired', 'document_expiring' => url('/profile'),
            'password_changed' => url('/profile'),
            default => url('/dashboard'),
        };
    }

    private function actionLabelFor(User $user, string $type): ?string
    {
        if ($type === 'event_registration' && $user->hasRole('guardian') && ! $user->hasRole('athlete')) {
            return 'Личный кабинет';
        }

        return match ($type) {
            'training_scheduled', 'training_cancelled', 'training_reminder' => 'Расписание',
            'event_registration' => 'Портфолио',
            'balance_negative' => 'Финансы',
            'document_expired', 'document_expiring', 'password_changed' => 'Личный кабинет',
            default => 'Открыть CRM',
        };
    }

    private function sendEmailIfNeeded(User $user, string $dispatchKey, Mailable $mailable): void
    {
        $emailKey = $dispatchKey.':mail';

        if (NotificationDispatch::where('dispatch_key', $emailKey)->exists()) {
            return;
        }

        if (! $user->email) {
            return;
        }

        if (config('mail.send_async', true)) {
            DeliverNotificationEmail::dispatch($user->id, $dispatchKey, $mailable)->afterResponse();

            return;
        }

        $this->deliverEmail($user, $dispatchKey, $mailable);
    }

    public function deliverEmail(User $user, string $dispatchKey, Mailable $mailable): void
    {
        $emailKey = $dispatchKey.':mail';

        if (NotificationDispatch::where('dispatch_key', $emailKey)->exists()) {
            return;
        }

        if (! $user->email) {
            return;
        }

        try {
            Mail::to($user->email)->send($mailable);
        } catch (Throwable $e) {
            Log::warning('Не удалось отправить email-уведомление', [
                'user_id' => $user->id,
                'email' => $user->email,
                'dispatch_key' => $dispatchKey,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        NotificationDispatch::create([
            'user_id' => $user->id,
            'dispatch_key' => $emailKey,
            'channel' => 'mail',
            'sent_at' => now(),
        ]);

        Log::info('Email-уведомление отправлено', [
            'user_id' => $user->id,
            'email' => $user->email,
            'dispatch_key' => $dispatchKey,
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
