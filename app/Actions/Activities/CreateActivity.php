<?php

declare(strict_types=1);

namespace App\Actions\Activities;

use App\DTO\ActivityData;
use App\Models\Activity;
use Illuminate\Support\Facades\DB;
use Throwable;

final class CreateActivity
{
    /**
     * Crea un'attività con i suoi assegnatari in transazione.
     *
     * @throws Throwable
     */
    public function handle(ActivityData $data): Activity
    {
        return DB::transaction(function () use ($data): Activity {
            $activity = Activity::create([
                'project_id' => $data->projectId,
                'name' => $data->name,
                'description' => $data->description,
                'starts_on' => $data->startsOn,
                'due_on' => $data->dueOn,
                'planned_hours' => $data->plannedHours,
                'status' => $data->status,
            ]);

            $activity->assignees()->sync($data->assigneeIds);

            return $activity;
        });
    }
}
