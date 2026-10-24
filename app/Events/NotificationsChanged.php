<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Avvisa il browser della persona che ci sono notifiche nuove: il campanello si ricarica subito, senza aggiornamento
 * periodico. Parte solo dopo il commit della transazione che ha creato la notifica e non porta dati.
 */
class NotificationsChanged implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;

    public bool $afterCommit = true;

    public function __construct(public int $userId) {}

    /**
     * @return list<Channel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("App.Models.User.{$this->userId}")];
    }

    public function broadcastAs(): string
    {
        return 'notifications.changed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [];
    }
}
