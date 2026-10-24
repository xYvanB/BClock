<?php

declare(strict_types=1);

namespace App\Actions\Activities;

use App\DTO\ActivityData;
use App\Models\Activity;
use Illuminate\Support\Facades\DB;
use Throwable;

final class UpdateActivity
{
    /**
     * Aggiorna l'attività e i suoi assegnatari in transazione. Il progetto non cambia.
     *
     * @throws Throwable
     */
    public function handle(Activity $activity, ActivityData $data): Activity
    {
        return DB::transaction(function () use ($activity, $data): Activity {
            $activity->update([
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
