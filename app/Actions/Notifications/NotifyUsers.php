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
     * Notifies the given people, once each and never the person who performed the action, according to their preferences:
     * in the app (always for approval requests, with a real-time alert to the browser) and/or by e-mail. Used inside the Actions' transactions:
     * the in-app notification follows their outcome, the e-mail is queued only after the commit.
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
