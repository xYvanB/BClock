<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enum\Role;
use App\Models\User;

/**
 * Users: the Boss and HR manage everyone, the area head only those of their own area.
 */
final class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasRole(Role::Boss, Role::AreaHead, Role::Hr);
    }

    public function create(User $actor): bool
    {
        return $actor->hasRole(Role::Boss, Role::AreaHead, Role::Hr);
    }

    public function update(User $actor, User $target): bool
    {
        if ($actor->hasRole(Role::Boss)) {
            return true;
        }

        // HR manages everyone except the Boss.
        if ($actor->hasRole(Role::Hr)) {
            return ! $target->hasRole(Role::Boss);
        }

        return $actor->hasRole(Role::AreaHead)
            && $actor->area_id !== null
            && $actor->area_id === $target->area_id
            && $target->hasRole(Role::Employee);
    }
}
