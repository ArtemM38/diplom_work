<?php

namespace App\Console\Commands;

use App\Mail\UserNotificationMail;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

class TestMailCommand extends Command
{
    protected $signature = 'mail:test {email? : Адрес получателя (по умолчанию MAIL_FROM_ADDRESS)}';

    protected $description = 'Проверка отправки email через текущие настройки MAIL_* в .env';

    public function handle(): int
    {
        $to = $this->argument('email') ?: config('mail.from.address');

        if (! $to) {
            $this->error('Укажите email: php artisan mail:test user@example.com');

            return self::FAILURE;
        }

        $this->line('Mailer: '.config('mail.default'));
        $this->line('Host: '.config('mail.mailers.smtp.host'));
        $this->line('Port: '.config('mail.mailers.smtp.port'));
        $this->line('From: '.config('mail.from.address'));
        $this->line('To: '.$to);

        $user = User::query()->where('email', $to)->first()
            ?? new User(['name' => 'Тест', 'email' => $to]);

        try {
            Mail::to($to)->send(new UserNotificationMail(
                $user,
                'Тест почты — '.config('app.name'),
                'Тест почты',
                'Если вы видите это письмо, SMTP настроен правильно.',
                url('/'),
                'Открыть CRM',
            ));
        } catch (Throwable $e) {
            $this->error('Ошибка: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Письмо отправлено. Проверьте входящие и папку «Спам».');

        return self::SUCCESS;
    }
}
