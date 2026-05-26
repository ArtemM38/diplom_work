<?php

namespace App\Mail;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TrainingReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $groupName,
        public string $locationName,
        public Carbon $lessonAt,
        public ?string $coachName = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Напоминание: тренировка через 2 часа — Айкидо CRM');
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.training-reminder',
        );
    }
}
