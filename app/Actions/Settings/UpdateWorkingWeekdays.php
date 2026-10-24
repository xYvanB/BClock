<?php

declare(strict_types=1);

namespace App\Actions\Settings;

use App\Actions\Allocations\RegenerateAllocations;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Throwable;

final class UpdateWorkingWeekdays
{
    public function __construct(private readonly RegenerateAllocations $allocations) {}

    /**
     * Saves the working weekdays (1 = Monday … 7 = Sunday) and recalculates the allocations of open activities,
     * which depend on which days are worked.
     *
     * @param  list<int>  $weekdays
     *
     * @throws Throwable
     */
    public function handle(array $weekdays): void
    {
        DB::transaction(function () use ($weekdays): void {
            sort($weekdays);
            Setting::write(Setting::WORKING_WEEKDAYS, implode(',', array_unique($weekdays)));

            $this->allocations->handle();
        });
    }
}
