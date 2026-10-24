<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Absence;
use Illuminate\Notifications\Notification;

/**
 * Per il Capo area: un dipendente della sua area ha chiesto un'assenza che lui deve approvare.
 */
class AbsenceRequested extends Notification
{
    public function __construct(public Absence $absence) {}

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
            'absence_id' => $this->absence->id,
            'message' => "{$this->absence->user->fullName()} ha chiesto {$this->absence->type->value} ({$this->absence->periodLabel()}): serve la tua approvazione.",
        ];
    }
}
