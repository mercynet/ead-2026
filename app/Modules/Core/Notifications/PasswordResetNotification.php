<?php

namespace App\Modules\Core\Notifications;

use App\Modules\Core\Models\PasswordReset;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordResetNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $token) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Redefinição de senha')
            ->line('Recebemos um pedido para redefinir a sua senha.')
            ->line('Use o token abaixo (válido por '.PasswordReset::EXPIRES_IN_MINUTES.' minutos):')
            ->line($this->token)
            ->line('Se você não solicitou a redefinição, ignore este e-mail.');
    }
}
