<?php

declare(strict_types=1);

namespace App\Actions\Projects;

use App\Enum\WorkStatus;
use App\Models\Project;
use Illuminate\Support\Facades\DB;
use Throwable;

final class ArchiveProject
{
    /**
     * Archives the project and its activities: no more hours are logged on archived ones.
     *
     * @throws Throwable
     */
    public function handle(Project $project): Project
    {
        return DB::transaction(function () use ($project): Project {
            $project->update(['status' => WorkStatus::Archived]);
            $project->activities()->update(['status' => WorkStatus::Archived]);

            return $project;
        });
    }
}
