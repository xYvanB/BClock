<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TimeEntry;
use App\Models\User;

/**
 * Everyone logs and edits their own hours. The visibility of others' hours
 * (Boss, area head, HR) comes with the per-person filter.
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
