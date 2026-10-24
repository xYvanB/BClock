<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enum\Role;
use App\Models\Activity;
use App\Models\User;

/**
 * Activities: the Boss manages everything; the area head only those whose assignees are all
 * of their own area; HR is read-only; the Employee does not have the page.
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

    public function archive(User $user, Activity $activity): bool
    {
        return $this->update($user, $activity);
    }

    public function delete(User $user, Activity $activity): bool
    {
        return $this->update($user, $activity);
    }
}
