<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as BaseResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPassword extends BaseResetPassword
{
    /**
     * @param  mixed  $notifiable
     */
    protected function resetUrl($notifiable): string
    {
        return url(route('password.reset', [
            'token' => $this->token,
            'login' => $notifiable->login,
        ], false));
    }

    /**
     * @param  mixed  $notifiable
     */
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Сброс пароля')
            ->line('Вы получили это письмо, потому что для вашего аккаунта был запрошен сброс пароля.')
            ->action('Сбросить пароль', $this->resetUrl($notifiable))
            ->line('Если вы не запрашивали сброс пароля, просто проигнорируйте это письмо.');
    }
}
