<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enum\AbsenceStatus;
use App\Enum\Role;
use App\Models\Absence;
use App\Models\User;

/**
 * Ognuno inserisce le proprie assenze e le modifica finché sono in attesa. Le conferma l'HR; per i dipendenti
 * serve anche l'approvazione del loro Capo area. Entrambi possono rifiutare, con un motivo.
 */
final class AbsencePolicy
{
    public function update(User $user, Absence $absence): bool
    {
        return $absence->user_id === $user->id && $absence->isPending();
    }

    /**
     * L'HR può rimuovere qualunque assenza (quelle confermate solo con un motivo);
     * la persona solo le proprie in attesa o rifiutate.
     */
    public function delete(User $user, Absence $absence): bool
    {
        if ($user->hasRole(Role::Hr)) {
            return true;
        }

        return $absence->user_id === $user->id && $absence->status !== AbsenceStatus::Confirmed;
    }

    /**
     * Conferma dell'HR.
     */
    public function confirm(User $user, Absence $absence): bool
    {
        return $user->hasRole(Role::Hr) && $absence->isPending() && ! $absence->isApprovedByHr();
    }

    /**
     * Approvazione del Capo area dell'area del dipendente.
     */
    public function approve(User $user, Absence $absence): bool
    {
        return $this->isAreaHeadOf($user, $absence) && ! $absence->isApprovedByAreaHead();
    }

    public function reject(User $user, Absence $absence): bool
    {
        if (! $absence->isPending()) {
            return false;
        }

        return $user->hasRole(Role::Hr) || $this->isAreaHeadOf($user, $absence);
    }

    private function isAreaHeadOf(User $user, Absence $absence): bool
    {
        return $user->hasRole(Role::AreaHead)
            && $absence->isPending()
            && $absence->requiresAreaHead()
            && $absence->user->area_id === $user->area_id;
    }
}
