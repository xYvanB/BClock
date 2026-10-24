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
     * Salva i giorni della settimana lavorativi (1 = lunedì … 7 = domenica) e ricalcola le quote delle attività aperte,
     * che dipendono da quali giorni si lavora.
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
