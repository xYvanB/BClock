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
            ->subject('Sei stato invitato su '.config('app.name'))
            ->greeting("Ciao {$notifiable->name},")
            ->line('Ti hanno invitato a usare '.config('app.name').' per registrare le ore di lavoro.')
            ->action('Imposta la password', $url)
            ->line('Il link è valido per 7 giorni.');
    }
}
