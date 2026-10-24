<?php

declare(strict_types=1);

namespace App\Actions\Notifications;

use App\Enum\NotificationKind;
use App\Events\NotificationsChanged;
use App\Mail\AppNotificationMail;
use App\Models\User;
use App\Notifications\AppNotification;
use Illuminate\Support\Facades\Mail;

final class NotifyUsers
{
    /**
     * Avvisa le persone indicate, una volta sola ciascuna e mai chi ha fatto l'azione, secondo le loro preferenze:
     * nell'app (sempre per le richieste da approvare, con un avviso in tempo reale al browser) e/o per e-mail. Si usa dentro le transazioni delle Action:
     * la notifica nell'app ne segue l'esito, l'e-mail parte in coda solo dopo il commit.
     *
     * @param  iterable<User>  $users
     */
    public function handle(iterable $users, NotificationKind $kind, string $message, string $url, ?User $actor = null): void
    {
        $recipients = [];
        foreach ($users as $user) {
            if ($actor === null || $user->id !== $actor->id) {
                $recipients[$user->id] = $user;
            }
        }

        foreach ($recipients as $user) {
            if ($user->wantsNotification($kind, 'app')) {
                $user->notify(new AppNotification($kind, $message, $url, $actor?->fullName()));
                NotificationsChanged::dispatch($user->id);
            }

            if ($user->wantsNotification($kind, 'mail')) {
                Mail::to($user->email)->queue(new AppNotificationMail($message, $url));
            }
        }
    }
}
