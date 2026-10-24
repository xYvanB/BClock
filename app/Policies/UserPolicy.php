<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enum\Role;
use App\Models\User;

/**
 * Utenti: il Capo e l'HR gestiscono tutti, il Capo area solo quelli della propria area.
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

        // L'HR gestisce tutti tranne il Capo.
        if ($actor->hasRole(Role::Hr)) {
            return ! $target->hasRole(Role::Boss);
        }

        return $actor->hasRole(Role::AreaHead)
            && $actor->area_id !== null
            && $actor->area_id === $target->area_id
            && $target->hasRole(Role::Employee);
    }
}
