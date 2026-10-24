<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Support\Carbon;
use Laravel\Passport\Token;

/**
 * Passport's access token, with the moment an AI app last used it.
 *
 * @property Carbon|null $last_used_at
 */
class AccessToken extends Token
{
    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return ['last_used_at' => 'datetime'];
    }
}
