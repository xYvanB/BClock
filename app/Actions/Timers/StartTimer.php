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
     * Avvia un timer per l'utente, in transazione. Se ne possono avere più d'uno, ma uno solo per attività:
     * con due richieste insieme la seconda trova l'indice unico e riceve lo stesso errore della validazione.
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
            throw ValidationException::withMessages(['activity_id' => 'Hai già un timer attivo su questa attività.']);
        }
    }
}
