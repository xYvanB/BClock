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
     * Archives the activity: no more hours are logged and no timers started on archived ones.
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
