<?php

declare(strict_types=1);

namespace App\Actions\Activities;

use App\Enum\WorkStatus;
use App\Models\Activity;
use Illuminate\Support\Facades\DB;
use Throwable;

final class ArchiveActivity
{
    /**
     * Archivia l'attività: sugli archiviati non si registrano più ore né si avviano timer.
     *
     * @throws Throwable
     */
    public function handle(Activity $activity): Activity
    {
        return DB::transaction(function () use ($activity): Activity {
            $activity->update(['status' => WorkStatus::Archived]);

            return $activity;
        });
    }
}
