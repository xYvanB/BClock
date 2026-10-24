<?php

declare(strict_types=1);

namespace App\Actions\Settings;

use App\Models\User;
use Laravel\Passport\Passport;

class RevokeConnectedApp
{
    /**
     * Revokes every access and refresh token the person gave to one OAuth client.
     */
    public function handle(User $user, string $clientId): void
    {
        $tokenIds = $user->tokens()->where('client_id', $clientId)->pluck('id');

        $user->tokens()->where('client_id', $clientId)->update(['revoked' => true]);
        Passport::refreshToken()->newQuery()->whereIn('access_token_id', $tokenIds)->update(['revoked' => true]);
    }
}
