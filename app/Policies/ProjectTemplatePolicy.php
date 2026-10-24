<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enum\Role;
use App\Models\User;

/**
 * I template li vedono e li gestiscono solo Capo e Capo area. L'HR e il Dipendente no
 * (l'HR non può nemmeno usarli: creare un progetto da template crea attività, e l'HR è in sola lettura su di esse).
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
