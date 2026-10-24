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
     * Registra ore per l'utente, in transazione (manuali per impostazione predefinita, o dal timer).
     * Lo sforamento delle ore previste è solo un avviso e non blocca la registrazione.
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
