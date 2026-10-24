<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enum\Role;
use App\Models\User;

/**
 * I clienti li vedono e li gestiscono Capo, Capo area e HR; il Dipendente no.
 */
final class ClientPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(Role::Boss, Role::AreaHead, Role::Hr);
    }

    public function view(User $user): bool
    {
        return $this->viewAny($user);
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
