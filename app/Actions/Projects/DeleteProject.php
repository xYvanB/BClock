<?php

declare(strict_types=1);

namespace App\Actions\Projects;

use App\Models\Project;
use App\Models\Timer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

final class DeleteProject
{
    /**
     * Deletes a project only if it has no logged hours or active timers (otherwise archive it); its activities go with it.
     *
     * @throws Throwable
     */
    public function handle(Project $project): void
    {
        DB::transaction(function () use ($project): void {
            if ($project->timeEntries()->exists()) {
                throw ValidationException::withMessages([
                    'project' => __('Il progetto ha ore registrate: non si può eliminare, solo archiviare.'),
                ]);
            }

            if (Timer::whereIn('activity_id', $project->activities()->select('id'))->exists()) {
                throw ValidationException::withMessages([
                    'project' => __('Ci sono timer attivi su questo progetto: fermali prima di eliminarlo, oppure archivia il progetto.'),
                ]);
            }

            $project->activities()->delete();
            $project->delete();
        });
    }
}
