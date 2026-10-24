<?php

declare(strict_types=1);

namespace App\Actions\Holidays;

use App\DTO\HolidayData;
use App\Models\Holiday;
use Illuminate\Support\Facades\DB;
use Throwable;

final class SaveHoliday
{
    /**
     * Crea un festivo o aggiorna quello indicato, in transazione.
     *
     * @throws Throwable
     */
    public function handle(HolidayData $data, ?Holiday $holiday = null): Holiday
    {
        return DB::transaction(function () use ($data, $holiday): Holiday {
            $attributes = ['date' => $data->date, 'name' => $data->name];

            if ($holiday === null) {
                return Holiday::create($attributes);
            }

            $holiday->update($attributes);

            return $holiday;
        });
    }
}
