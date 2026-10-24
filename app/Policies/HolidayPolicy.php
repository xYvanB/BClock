<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enum\Role;
use App\Models\User;

/**
 * Holidays and closures are managed by the Boss and HR (company calendar settings); everyone sees them.
 */
final class HolidayPolicy
{
    public function create(User $user): bool
    {
        return $user->hasRole(Role::Boss, Role::Hr);
    }

    public function update(User $user): bool
    {
        return $user->hasRole(Role::Boss, Role::Hr);
    }

    public function delete(User $user): bool
    {
        return $user->hasRole(Role::Boss, Role::Hr);
    }
}
