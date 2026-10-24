<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enum\Role;
use App\Models\Activity;
use App\Models\User;

/**
 * Attività: il Capo gestisce tutto; il Capo area solo quelle i cui assegnatari sono tutti
 * della propria area; l'HR è in sola lettura; il Dipendente non ha la pagina.
 */
final class ActivityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(Role::Boss, Role::AreaHead, Role::Hr);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(Role::Boss, Role::AreaHead);
    }

    public function update(User $user, Activity $activity): bool
    {
        if ($user->hasRole(Role::Boss)) {
            return true;
        }

        if (! $user->hasRole(Role::AreaHead) || $user->area_id === null) {
            return false;
        }

        $assignees = $activity->assignees;

        return $assignees->isNotEmpty()
            && $assignees->every(fn (User $assignee): bool => $assignee->area_id === $user->area_id);
    }
}
