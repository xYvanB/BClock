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
     * Salva le preferenze dell'utente in transazione.
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
            ]);

            return $user;
        });
    }
}
