<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enum\Role;
use App\Models\User;

/**
 * Areas are managed only by the Boss.
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
