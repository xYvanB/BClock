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
     * Deletes an activity only if it has no logged hours or active timers (otherwise archive it).
     * Assignees, dependencies and daily allocations go with it.
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
