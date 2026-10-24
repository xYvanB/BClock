<?php

declare(strict_types=1);

namespace App\Actions\Timers;

use App\DTO\TimerData;
use App\Models\Timer;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

final class StartTimer
{
    /**
     * Starts a timer for the user, in a transaction. One can have several, but only one per activity:
     * with two simultaneous requests the second hits the unique index and gets the same error as validation.
     *
     * @throws Throwable
     */
    public function handle(User $user, TimerData $data): Timer
    {
        try {
            return DB::transaction(fn (): Timer => Timer::create([
                'user_id' => $user->id,
                'activity_id' => $data->activityId,
                'description' => $data->description,
                'started_at' => now(),
            ]));
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['activity_id' => __('Hai già un timer attivo su questa attività.')]);
        }
    }
}
