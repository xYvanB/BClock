<?php

declare(strict_types=1);

namespace App\Actions\Allocations;

use App\Enum\AbsenceStatus;
use App\Enum\AbsenceType;
use App\Models\Absence;
use App\Models\Holiday;
use App\Models\Setting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;

final class CalculateAvailability
{
    /**
     * Ore disponibili di ogni persona in ogni giorno dell'intervallo (estremi inclusi).
     *
     * Un giorno lavorativo cade in un giorno della settimana lavorativo (impostazioni) e non è festivo né di chiusura. La disponibilità è la capacità giornaliera della persona,
     * ridotta delle ore dei permessi confermati e azzerata da ferie confermate; fuori dai giorni lavorativi è 0.
     * Contano solo le assenze confermate (con tutte le approvazioni richieste).
     *
     * @param  list<int>  $userIds
     * @return array{days: list<string>, working_days: list<string>, capacities: array<int, float>, hours: array<int, array<string, float>>}
     */
    public function handle(array $userIds, string $from, string $to): array
    {
        $days = collect(CarbonPeriod::create($from, $to))->map(fn ($day): string => $day->toDateString());

        $holidays = Holiday::query()
            ->whereBetween('date', [$from, $to])
            ->get()
            ->map(fn (Holiday $holiday): string => $holiday->date->toDateString());

        $weekdays = Setting::workingWeekdays();

        $workingDays = $days
            ->reject(fn (string $day): bool => ! in_array(CarbonImmutable::parse($day)->dayOfWeekIso, $weekdays, true) || $holidays->contains($day))
            ->values();

        $capacities = User::whereIn('id', $userIds)->pluck('daily_capacity', 'id')
            ->map(fn ($capacity): float => (float) $capacity)
            ->all();

        $absences = Absence::query()
            ->whereIn('user_id', $userIds)
            ->where('status', AbsenceStatus::Confirmed)
            ->whereDate('starts_on', '<=', $to)
            ->whereDate('ends_on', '>=', $from)
            ->get();

        $hours = [];
        foreach ($userIds as $userId) {
            $capacity = $capacities[$userId] ?? 0.0;
            $mine = $absences->where('user_id', $userId);

            foreach ($days as $day) {
                $hours[$userId][$day] = $workingDays->contains($day)
                    ? $this->availableOn($mine, $day, $capacity)
                    : 0.0;
            }
        }

        return [
            'days' => $days->all(),
            'working_days' => $workingDays->all(),
            'capacities' => $capacities,
            'hours' => $hours,
        ];
    }

    /**
     * @param  iterable<Absence>  $absences
     */
    private function availableOn(iterable $absences, string $day, float $capacity): float
    {
        $leaveHours = 0.0;

        foreach ($absences as $absence) {
            if ($absence->starts_on->toDateString() > $day || $absence->ends_on->toDateString() < $day) {
                continue;
            }

            if ($absence->type === AbsenceType::Vacation) {
                return 0.0;
            }

            $leaveHours += $absence->leaveHours() ?? 0.0;
        }

        return max(0.0, $capacity - $leaveHours);
    }
}
