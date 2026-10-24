<?php

declare(strict_types=1);

namespace App\Actions\Holidays;

use App\Actions\Allocations\RegenerateAllocations;
use App\DTO\HolidayData;
use App\Models\Holiday;
use Illuminate\Support\Facades\DB;
use Throwable;

final class SaveHoliday
{
    public function __construct(private readonly RegenerateAllocations $allocations) {}

    /**
     * Crea un festivo o aggiorna quello indicato, in transazione.
     *
     * @throws Throwable
     */
    public function handle(HolidayData $data, ?Holiday $holiday = null): Holiday
    {
        return DB::transaction(function () use ($data, $holiday): Holiday {
            $attributes = ['date' => $data->date, 'name' => $data->name];
            $previousDate = $holiday?->date->toDateString();

            $holiday = $holiday === null ? Holiday::create($attributes) : tap($holiday)->update($attributes);

            foreach (array_unique(array_filter([$previousDate, $data->date])) as $date) {
                $this->allocations->handle($date, $date);
            }

            return $holiday;
        });
    }
}
