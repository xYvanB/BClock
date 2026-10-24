<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Timer;
use App\Models\User;

/**
 * Ognuno avvia e ferma i propri timer.
 */
final class TimerPolicy
{
    public function stop(User $user, Timer $timer): bool
    {
        return $timer->user_id === $user->id;
    }
}
