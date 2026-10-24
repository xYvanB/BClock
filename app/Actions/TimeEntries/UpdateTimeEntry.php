<?php

declare(strict_types=1);

namespace App\Actions\TimeEntries;

use App\DTO\TimeEntryData;
use App\Models\TimeEntry;
use Illuminate\Support\Facades\DB;
use Throwable;

final class UpdateTimeEntry
{
    /**
     * Edits a time entry in a transaction. Past hours are always editable.
     *
     * @throws Throwable
     */
    public function handle(TimeEntry $entry, TimeEntryData $data): TimeEntry
    {
        return DB::transaction(function () use ($entry, $data): TimeEntry {
            $entry->update([
                'activity_id' => $data->activityId,
                'date' => $data->date->toDateString(),
                'started_at' => $data->startedAt,
                'ended_at' => $data->endedAt,
                'duration_minutes' => $data->durationMinutes(),
                'description' => $data->description,
            ]);

            return $entry;
        });
    }
}
