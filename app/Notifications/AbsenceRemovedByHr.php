<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * Per la persona: l'HR ha eliminato una sua assenza già confermata, con il motivo.
 */
class AbsenceRemovedByHr extends Notification
{
    public function __construct(
        public string $period,
        public string $reason,
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
            'message' => 'L\'HR ha eliminato la tua assenza confermata ('.$this->period.'). Motivo: '.$this->reason,
        ];
    }
}
