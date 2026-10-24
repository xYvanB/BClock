<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\DTO\AcceptInvitationData;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

final class AcceptInvitation
{
    /**
     * Primo accesso: imposta password e preferenze e attiva l'utente, in transazione.
     *
     * @throws Throwable
     */
    public function handle(User $user, AcceptInvitationData $data): User
    {
        return DB::transaction(function () use ($user, $data): User {
            $user->forceFill([
                'password' => $data->password,
                'locale' => $data->locale,
                'time_format' => $data->timeFormat,
                'invited_at' => null,
                'email_verified_at' => now(),
            ])->save();

            return $user;
        });
    }
}
