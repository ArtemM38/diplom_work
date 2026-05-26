<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendTestMail extends Command
{
    protected $signature = 'mail:test {email? : Адрес получателя (по умолчанию MAIL_FROM_ADDRESS)}';

    protected $description = 'Проверить отправку почты (Yandex SMTP)';

    public function handle(): int
    {
        $to = $this->argument('email') ?: config('mail.from.address');

        if (! $to || $to === 'hello@example.com') {
            $this->error('Укажите email: php artisan mail:test your@yandex.ru');
            $this->line('Или задайте MAIL_FROM_ADDRESS в .env');

            return self::FAILURE;
        }

        $mailer = config('mail.default');
        $host = config('mail.mailers.smtp.host');
        $port = config('mail.mailers.smtp.port');

        $this->info("Mailer: {$mailer}");
        $this->info("SMTP: {$host}:{$port}");

        try {
            Mail::raw(
                'Тестовое письмо из CRM Айкидо. Если вы видите это сообщение — Yandex SMTP настроен верно.',
                function ($message) use ($to) {
                    $message->to($to)->subject('Тест почты — Айкидо CRM');
                }
            );

            $this->info("Письмо отправлено на {$to}");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Ошибка отправки: ' . $e->getMessage());
            $this->line('Проверьте .env и пароль приложения Yandex. См. docs/MAIL_YANDEX.md');

            return self::FAILURE;
        }
    }
}
