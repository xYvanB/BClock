<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * Invitation e-mail with a signed link to choose the password (valid 7 days).
 */
class UserInvited extends Notification
{
    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $url = URL::temporarySignedRoute('invitation.show', now()->addDays(7), ['user' => $notifiable->id]);

        return (new MailMessage)
            ->subject(__('Sei stato invitato su :app', ['app' => config('app.name')]))
            ->greeting(__('Ciao :name,', ['name' => $notifiable->name]))
            ->line(__('Ti hanno invitato a usare :app per registrare le ore di lavoro.', ['app' => config('app.name')]))
            ->action(__('Imposta la password'), $url)
            ->line(__('Il link è valido per 7 giorni.'));
    }
}
