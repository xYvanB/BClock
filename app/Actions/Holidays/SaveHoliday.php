<?php

declare(strict_types=1);

namespace App\Actions\Holidays;

use App\Actions\Allocations\RegenerateAllocations;
use App\DTO\HolidayData;
use App\Models\Holiday;
use App\Models\Setting;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;
use Throwable;

final class SaveHoliday
{
    public function __construct(private readonly RegenerateAllocations $allocations) {}

    /**
     * Un periodo di chiusura: una riga per ogni giorno lavorativo (weekend e giorni già chiusi si saltano).
     *
     * @param  array<string, mixed>  $attributes
     */
    private function createPeriod(HolidayData $data, array $attributes): Holiday
    {
        $weekdays = Setting::workingWeekdays();
        $existing = Holiday::closedDates();
        $first = null;

        foreach (CarbonPeriod::create($data->date, (string) $data->until) as $day) {
            if (! in_array($day->dayOfWeekIso, $weekdays, true) || in_array($day->toDateString(), $existing, true)) {
                continue;
            }

            $created = Holiday::create(['date' => $day->toDateString()] + $attributes);
            $first ??= $created;
        }

        $this->allocations->handle($data->date, (string) $data->until);

        return $first ?? Holiday::where('date', $data->date)->firstOrFail();
    }

    /**
     * Crea un festivo o aggiorna quello indicato, in transazione.
     *
     * @throws Throwable
     */
    public function handle(HolidayData $data, ?Holiday $holiday = null): Holiday
    {
        return DB::transaction(function () use ($data, $holiday): Holiday {
            $attributes = ['date' => $data->date, 'name' => $data->name, 'kind' => $data->kind];
            $previousDate = $holiday?->date->toDateString();

            if ($holiday === null && $data->until !== null) {
                return $this->createPeriod($data, $attributes);
            }

            $holiday = $holiday === null ? Holiday::create($attributes) : tap($holiday)->update($attributes);

            foreach (array_unique(array_filter([$previousDate, $data->date])) as $date) {
                $this->allocations->handle($date, $date);
            }

            return $holiday;
        });
    }
}
