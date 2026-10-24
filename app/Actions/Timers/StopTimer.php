<?php

declare(strict_types=1);

namespace App\Actions\Timers;

use App\Actions\TimeEntries\CreateTimeEntry;
use App\DTO\TimeEntryData;
use App\Enum\TimeEntrySource;
use App\Models\Timer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Throwable;

final class StopTimer
{
    public function __construct(private readonly CreateTimeEntry $createTimeEntry) {}

    /**
     * Stops the timer and turns it into editable time entries (source "timer"), in a transaction.
     *
     * - Times are in the local timezone (`app.local_timezone`), like manual hours.
     * - An entry lies within a single day: if the timer crosses midnight it is split by day
     *   (the first day ends at 23:59, the following ones start at 00:00).
     * - Under a minute (start and end truncated to the minute) nothing is created.
     * - The timer is deleted first: with two simultaneous requests (double click) only one finds it.
     * - It is logged even if the activity was archived in the meantime: the work was done.
     *
     * @return int|null number of entries created, or null if the timer had already been stopped
     *
     * @throws Throwable
     */
    public function handle(Timer $timer): ?int
    {
        return DB::transaction(function () use ($timer): ?int {
            if (Timer::whereKey($timer->id)->delete() !== 1) {
                return null;
            }

            $timezone = (string) config('app.local_timezone');
            $cursor = CarbonImmutable::instance($timer->started_at)->setTimezone($timezone)->startOfMinute();
            $end = CarbonImmutable::now($timezone)->startOfMinute();
            $created = 0;

            while ($cursor < $end) {
                $dayEnd = $cursor->endOfDay()->startOfMinute();
                $segmentEnd = $end <= $dayEnd ? $end : $dayEnd;

                if ($segmentEnd > $cursor) {
                    $this->createTimeEntry->handle($timer->user, new TimeEntryData(
                        activityId: $timer->activity_id,
                        date: $cursor->startOfDay(),
                        startedAt: $cursor->format('H:i'),
                        endedAt: $segmentEnd->format('H:i'),
                        description: $timer->description,
                    ), TimeEntrySource::Timer);

                    $created++;
                }

                $cursor = $cursor->addDay()->startOfDay();
            }

            return $created;
        });
    }
}
