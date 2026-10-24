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
     * Elimina un progetto solo se non ha ore registrate né timer attivi (altrimenti si archivia); le sue attività vanno con lui.
     *
     * @throws Throwable
     */
    public function handle(Project $project): void
    {
        DB::transaction(function () use ($project): void {
            if ($project->timeEntries()->exists()) {
                throw ValidationException::withMessages([
                    'project' => 'Il progetto ha ore registrate: non si può eliminare, solo archiviare.',
                ]);
            }

            if (Timer::whereIn('activity_id', $project->activities()->select('id'))->exists()) {
                throw ValidationException::withMessages([
                    'project' => 'Ci sono timer attivi su questo progetto: fermali prima di eliminarlo, oppure archivia il progetto.',
                ]);
            }

            $project->activities()->delete();
            $project->delete();
        });
    }
}
