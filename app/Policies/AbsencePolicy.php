<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enum\AbsenceStatus;
use App\Enum\Role;
use App\Models\Absence;
use App\Models\User;

/**
 * Everyone enters their own absences and edits them while pending; the Boss's are approved by nobody
 * and managed by the Boss. HR confirms them; for employees
 * the approval of their area head is also required. Both can reject, with a reason.
 */
final class AbsencePolicy
{
    public function update(User $user, Absence $absence): bool
    {
        return $absence->user_id === $user->id && ($absence->isPending() || $user->hasRole(Role::Boss));
    }

    /**
     * HR can remove any absence (confirmed ones only with a reason);
     * the person only their own pending or rejected ones.
     */
    public function delete(User $user, Absence $absence): bool
    {
        if ($user->hasRole(Role::Hr)) {
            return true;
        }

        return $absence->user_id === $user->id && ($absence->status !== AbsenceStatus::Confirmed || $user->hasRole(Role::Boss));
    }

    /**
     * Final confirmation: by HR, or by the Boss if the absence is HR's (nobody confirms their own).
     */
    public function confirm(User $user, Absence $absence): bool
    {
        return $user->id !== $absence->user_id
            && $user->hasRole($absence->finalApproverRole())
            && $absence->isPending()
            && ! $absence->isApprovedByHr();
    }

    /**
     * Approval by the area head of the employee's area.
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

        return ($user->id !== $absence->user_id && $user->hasRole($absence->finalApproverRole())) || $this->isAreaHeadOf($user, $absence);
    }

    private function isAreaHeadOf(User $user, Absence $absence): bool
    {
        return $user->hasRole(Role::AreaHead)
            && $absence->isPending()
            && $absence->requiresAreaHead()
            && $absence->user->area_id === $user->area_id;
    }
}
