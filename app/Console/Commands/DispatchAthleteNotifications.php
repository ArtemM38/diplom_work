<?php

namespace App\Console\Commands;

use App\Services\AthleteNotificationService;
use Illuminate\Console\Command;

class DispatchAthleteNotifications extends Command
{
    protected $signature = 'notifications:dispatch-athletes';

    protected $description = 'Отправить спортсменам уведомления о документах и тренировках';

    public function handle(AthleteNotificationService $service): int
    {
        $stats = $service->dispatchAll();

        $this->info(sprintf(
            'Готово: документы — %d, напоминания о тренировках — %d',
            $stats['documents'],
            $stats['trainings']
        ));

        return self::SUCCESS;
    }
}
