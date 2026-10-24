<?php

declare(strict_types=1);

namespace App\Actions\Settings;

use App\DTO\PreferencesData;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

final class UpdatePreferences
{
    /**
     * Saves the user's preferences in a transaction.
     *
     * @throws Throwable
     */
    public function handle(User $user, PreferencesData $data): User
    {
        return DB::transaction(function () use ($user, $data): User {
            $user->update([
                'locale' => $data->locale,
                'time_format' => $data->timeFormat,
                'palette' => $data->palette,
                'custom_colors' => $data->customColors ?? $user->custom_colors,
            ]);

            return $user;
        });
    }
}
