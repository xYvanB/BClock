<?php

declare(strict_types=1);

namespace App\Actions\Holidays;

use App\Actions\Allocations\RegenerateAllocations;
use App\Models\Holiday;
use Illuminate\Support\Facades\DB;
use Throwable;

final class DeleteHoliday
{
    public function __construct(private readonly RegenerateAllocations $allocations) {}

    /**
     * @throws Throwable
     */
    public function handle(Holiday $holiday): void
    {
        DB::transaction(function () use ($holiday): void {
            $date = $holiday->date->toDateString();

            $holiday->delete();
            $this->allocations->handle($date, $date);
        });
    }
}
