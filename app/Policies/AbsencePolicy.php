<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enum\AbsenceStatus;
use App\Enum\Role;
use App\Models\Absence;
use App\Models\User;

/**
 * Ognuno inserisce le proprie assenze e le modifica finché sono in attesa; le conferma o rifiuta l'HR.
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

    public function confirm(User $user, Absence $absence): bool
    {
        return $user->hasRole(Role::Hr) && $absence->isPending();
    }

    public function reject(User $user, Absence $absence): bool
    {
        return $this->confirm($user, $absence);
    }
}
