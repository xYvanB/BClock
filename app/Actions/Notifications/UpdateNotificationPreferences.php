<?php

declare(strict_types=1);

namespace App\Actions\Notifications;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

final class UpdateNotificationPreferences
{
    /**
     * Saves the person's choices in a transaction.
     *
     * @param  array<string, array{app: bool, mail: bool}>  $preferences
     *
     * @throws Throwable
     */
    public function handle(User $user, array $preferences): User
    {
        return DB::transaction(function () use ($user, $preferences): User {
            $user->update(['notification_preferences' => $preferences]);

            return $user;
        });
    }
}
