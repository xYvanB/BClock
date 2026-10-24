<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enum\Role;
use App\Models\User;

/**
 * Festività e chiusure le gestiscono Capo e HR (impostazioni del calendario aziendale); tutti le vedono.
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
