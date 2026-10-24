<?php

declare(strict_types=1);

namespace App\Actions\TimeEntries;

use App\DTO\TimeEntryData;
use App\Enum\TimeEntrySource;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

final class CreateTimeEntry
{
    /**
     * Logs hours for the user, in a transaction (manual by default, or from the timer).
     * Exceeding the planned hours is only a warning and does not block the entry.
     *
     * @throws Throwable
     */
    public function handle(User $user, TimeEntryData $data, TimeEntrySource $source = TimeEntrySource::Manual): TimeEntry
    {
        return DB::transaction(fn (): TimeEntry => TimeEntry::create([
            'user_id' => $user->id,
            'activity_id' => $data->activityId,
            'date' => $data->date->toDateString(),
            'started_at' => $data->startedAt,
            'ended_at' => $data->endedAt,
            'duration_minutes' => $data->durationMinutes(),
            'description' => $data->description,
            'source' => $source,
        ]));
    }
}
