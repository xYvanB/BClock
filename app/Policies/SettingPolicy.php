<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enum\Role;
use App\Models\User;

/**
 * The application settings are managed by the Boss and HR.
 */
final class SettingPolicy
{
    public function manage(User $user): bool
    {
        return $user->hasRole(Role::Boss, Role::Hr);
    }
}
