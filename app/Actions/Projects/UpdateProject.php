<?php

declare(strict_types=1);

namespace App\Actions\Projects;

use App\DTO\ProjectData;
use App\Models\Project;
use Illuminate\Support\Facades\DB;
use Throwable;

final class UpdateProject
{
    /**
     * Aggiorna il progetto, i membri e i referenti in transazione. Il cliente non cambia.
     *
     * @throws Throwable
     */
    public function handle(Project $project, ProjectData $data): Project
    {
        return DB::transaction(function () use ($project, $data): Project {
            $project->update([
                'name' => $data->name,
                'description' => $data->description,
                'starts_on' => $data->startsOn,
                'due_on' => $data->dueOn,
                'planned_hours' => $data->plannedHours,
                'status' => $data->status,
            ]);

            $project->members()->sync($data->memberIds);
            $project->contacts()->sync($data->contactIds);

            return $project;
        });
    }
}
