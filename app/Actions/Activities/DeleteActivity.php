<?php

declare(strict_types=1);

namespace App\Actions\Activities;

use App\Models\Activity;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

final class DeleteActivity
{
    /**
     * Elimina un'attività solo se non ha ore registrate né timer attivi (altrimenti si archivia).
     * Assegnatari, dipendenze e quote giornaliere vanno con lei.
     *
     * @throws Throwable
     */
    public function handle(Activity $activity): void
    {
        DB::transaction(function () use ($activity): void {
            if ($activity->timeEntries()->exists()) {
                throw ValidationException::withMessages([
                    'activity' => "L'attività ha ore registrate: non si può eliminare, solo archiviare.",
                ]);
            }

            if ($activity->timers()->exists()) {
                throw ValidationException::withMessages([
                    'activity' => 'Ci sono timer attivi su questa attività: fermali prima di eliminarla, oppure archiviala.',
                ]);
            }

            $activity->delete();
        });
    }
}
