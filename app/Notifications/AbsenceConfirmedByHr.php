<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Absence;
use Illuminate\Notifications\Notification;

/**
 * Per il Capo: l'HR ha confermato una propria assenza (nessun altro l'ha approvata).
 */
class AbsenceConfirmedByHr extends Notification
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
        $period = $this->absence->starts_on->format('d/m/Y').' – '.$this->absence->ends_on->format('d/m/Y');

        return [
            'absence_id' => $this->absence->id,
            'message' => $this->absence->user->fullName().' ha confermato da sé la propria assenza ('.$period.').',
        ];
    }
}
