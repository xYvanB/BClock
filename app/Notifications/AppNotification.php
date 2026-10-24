<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enum\NotificationKind;
use Illuminate\Notifications\Notification;

/**
 * In-app notification (bell). The e-mail is sent by NotifyUsers, outside transactions, only if the person wants it.
 */
class AppNotification extends Notification
{
    public function __construct(
        public NotificationKind $kind,
        public string $message,
        public string $url,
        public ?string $actorName = null,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => $this->kind->value,
            'message' => $this->message,
            'url' => $this->url,
            'actor_name' => $this->actorName,
        ];
    }
}
