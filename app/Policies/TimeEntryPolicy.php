<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TimeEntry;
use App\Models\User;

/**
 * Ognuno registra e modifica le proprie ore. La visibilità delle ore altrui
 * (Capo, Capo area, HR) arriva con il filtro per persona.
 */
final class TimeEntryPolicy
{
    public function update(User $user, TimeEntry $entry): bool
    {
        return $entry->user_id === $user->id;
    }

    public function delete(User $user, TimeEntry $entry): bool
    {
        return $entry->user_id === $user->id;
    }
}
