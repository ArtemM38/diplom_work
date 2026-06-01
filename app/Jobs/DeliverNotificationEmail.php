<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\AthleteNotificationService;
use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;

class DeliverNotificationEmail
{
    use Dispatchable, Queueable;

    public function __construct(
        public int $userId,
        public string $dispatchKey,
        public Mailable $mailable,
    ) {}

    public function handle(AthleteNotificationService $notifications): void
    {
        $user = User::find($this->userId);
        if (! $user) {
            return;
        }

        $notifications->deliverEmail($user, $this->dispatchKey, $this->mailable);
    }
}
