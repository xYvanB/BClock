<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enum\Role;
use App\Models\User;

/**
 * Templates are seen and managed only by the Boss and area head. Not by HR or the Employee
 * (HR cannot even use them: creating a project from a template creates activities, and HR is read-only on those).
 */
final class ProjectTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(Role::Boss, Role::AreaHead);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user): bool
    {
        return $this->viewAny($user);
    }
}
