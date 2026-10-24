<?php

declare(strict_types=1);

namespace App\Actions\Absences;

use App\Actions\Allocations\RegenerateAllocations;
use App\Enum\AbsenceStatus;
use App\Enum\Role;
use App\Models\Absence;
use App\Models\User;
use App\Notifications\AbsenceConfirmedByHr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Throwable;

final class ConfirmAbsence
{
    public function __construct(private readonly RegenerateAllocations $allocations) {}

    /**
     * Registra la conferma dell'HR o l'approvazione del Capo area. Quando ci sono tutte le approvazioni
     * richieste l'assenza diventa confermata e da lì riduce la capacità della persona.
     * Se l'HR conferma una propria assenza, il Capo viene avvisato.
     *
     * @throws Throwable
     */
    public function handle(User $approver, Absence $absence): Absence
    {
        return DB::transaction(function () use ($approver, $absence): Absence {
            if ($approver->hasRole(Role::Hr)) {
                $absence->update(['confirmed_by' => $approver->id, 'confirmed_at' => now()]);
            } else {
                $absence->update(['area_head_confirmed_by' => $approver->id, 'area_head_confirmed_at' => now()]);
            }

            if (! $absence->isFullyApproved()) {
                return $absence;
            }

            $absence->update(['status' => AbsenceStatus::Confirmed]);

            $this->allocations->handle($absence->starts_on->toDateString(), $absence->ends_on->toDateString(), [$absence->user_id]);

            if ($absence->user_id === $approver->id) {
                Notification::send(User::where('role', Role::Boss)->get(), new AbsenceConfirmedByHr($absence));
            }

            return $absence;
        });
    }
}
