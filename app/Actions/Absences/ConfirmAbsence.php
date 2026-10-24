<?php

declare(strict_types=1);

namespace App\Actions\Absences;

use App\Actions\Allocations\RegenerateAllocations;
use App\Actions\Notifications\NotifyUsers;
use App\Enum\AbsenceStatus;
use App\Enum\NotificationKind;
use App\Enum\Role;
use App\Models\Absence;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

final class ConfirmAbsence
{
    public function __construct(
        private readonly RegenerateAllocations $allocations,
        private readonly NotifyUsers $notify,
    ) {}

    /**
     * Registra la conferma finale (HR, o Capo per le assenze dell'HR) o l'approvazione del Capo area. Quando ci sono
     * tutte le approvazioni richieste l'assenza diventa confermata e da lì riduce la capacità della persona.
     *
     * @throws Throwable
     */
    public function handle(User $approver, Absence $absence): Absence
    {
        return DB::transaction(function () use ($approver, $absence): Absence {
            if ($approver->hasRole(Role::AreaHead)) {
                $absence->update(['area_head_confirmed_by' => $approver->id, 'area_head_confirmed_at' => now()]);
            } else {
                $absence->update(['confirmed_by' => $approver->id, 'confirmed_at' => now()]);
            }

            $role = $approver->hasRole(Role::AreaHead) ? 'Capo area' : ($approver->hasRole(Role::Boss) ? 'Capo' : 'HR');
            $what = "{$absence->type->value} ({$absence->periodLabel()})";

            if (! $absence->isFullyApproved()) {
                $this->notify->handle([$absence->user], NotificationKind::AbsenceDecision, "{$approver->fullName()} ({$role}) ha approvato la tua richiesta di {$what}: manca ancora una conferma.", route('absences.index'), $approver);

                return $absence;
            }

            $absence->update(['status' => AbsenceStatus::Confirmed]);
            $this->notify->handle([$absence->user], NotificationKind::AbsenceDecision, "{$approver->fullName()} ({$role}) ha approvato la tua richiesta di {$what}: ora è confermata.", route('absences.index'), $approver);

            $this->allocations->handle($absence->starts_on->toDateString(), $absence->ends_on->toDateString(), [$absence->user_id]);

            return $absence;
        });
    }
}
