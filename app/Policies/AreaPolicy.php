<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enum\Role;
use App\Models\User;

/**
 * Le aree le gestisce solo il Capo.
 */
final class AreaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(Role::Boss);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(Role::Boss);
    }

    public function update(User $user): bool
    {
        return $user->hasRole(Role::Boss);
    }
}
