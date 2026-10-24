<?php

declare(strict_types=1);

namespace App\Actions\Projects;

use App\DTO\ProjectData;
use App\Models\Project;
use Illuminate\Support\Facades\DB;
use Throwable;

final class CreateProject
{
    /**
     * Crea un progetto con membri e referenti in transazione.
     *
     * @throws Throwable
     */
    public function handle(ProjectData $data): Project
    {
        return DB::transaction(function () use ($data): Project {
            $project = Project::create([
                'client_id' => $data->clientId,
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
