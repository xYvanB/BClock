<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enum\Role;
use App\Models\User;

/**
 * Le impostazioni dell'applicazione le gestiscono Capo e HR.
 */
final class SettingPolicy
{
    public function manage(User $user): bool
    {
        return $user->hasRole(Role::Boss, Role::Hr);
    }
}
