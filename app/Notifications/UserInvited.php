<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * Email di invito con link firmato per scegliere la password (valido 7 giorni).
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
