<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enum\Role;
use App\Models\User;

/**
 * I festivi li gestisce solo l'HR (decisione 2026-10-22); tutti li vedono.
 */
final class HolidayPolicy
{
    public function create(User $user): bool
    {
        return $user->hasRole(Role::Hr);
    }

    public function update(User $user): bool
    {
        return $user->hasRole(Role::Hr);
    }

    public function delete(User $user): bool
    {
        return $user->hasRole(Role::Hr);
    }
}
