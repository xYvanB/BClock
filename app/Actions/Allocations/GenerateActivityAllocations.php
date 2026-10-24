<?php

declare(strict_types=1);

namespace App\Actions\Allocations;

use App\Enum\AbsenceStatus;
use App\Enum\WorkStatus;
use App\Models\Absence;
use App\Models\Activity;
use App\Models\ActivityAllocation;
use App\Models\Holiday;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;
use Throwable;

final class GenerateActivityAllocations
{
    /**
     * Ricalcola le quote giornaliere di un'attività (le sostituisce tutte).
     *
     * Le ore previste si dividono in parti uguali sulle coppie persona–giorno lavorativo disponibili:
     * weekend e festivi sono esclusi, e una persona non ha quota nei giorni di un'assenza confermata.
     * Il totale delle quote è sempre uguale alle ore previste. Se non resta nessun giorno lavorativo
     * (periodo di soli weekend/festivi) o nessuna persona disponibile, si ripiega su tutti i giorni
     * o su tutte le persone, così le ore non si perdono. Le attività archiviate non cambiano.
     *
     * @throws Throwable
     */
    public function handle(Activity $activity): void
    {
        if ($activity->status === WorkStatus::Archived) {
            return;
        }

        DB::transaction(function () use ($activity): void {
            $activity->allocations()->delete();

            $userIds = $activity->assignees()->pluck('users.id')->all();
            $totalCents = (int) round((float) $activity->planned_hours * 100);

            if ($userIds === [] || $totalCents <= 0) {
                return;
            }

            $units = $this->availableUnits($activity, $userIds);

            $base = intdiv($totalCents, count($units));
            $remainder = $totalCents % count($units);
            $now = now();

            $rows = [];
            foreach ($units as $index => [$userId, $date]) {
                $rows[] = [
                    'activity_id' => $activity->id,
                    'user_id' => $userId,
                    'date' => $date,
                    'hours' => ($base + ($index < $remainder ? 1 : 0)) / 100,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            ActivityAllocation::insert($rows);
        });
    }

    /**
     * Coppie [persona, data] su cui dividere le ore.
     *
     * @param  list<int>  $userIds
     * @return list<array{int, string}>
     */
    private function availableUnits(Activity $activity, array $userIds): array
    {
        $start = CarbonImmutable::parse($activity->starts_on)->startOfDay();
        $end = CarbonImmutable::parse($activity->due_on)->startOfDay();

        $allDays = collect(CarbonPeriod::create($start, $end))->map(fn ($day): string => $day->toDateString());

        $holidays = Holiday::query()
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->map(fn (Holiday $holiday): string => $holiday->date->toDateString());

        $workingDays = $allDays
            ->reject(fn (string $day): bool => CarbonImmutable::parse($day)->isWeekend() || $holidays->contains($day))
            ->values();

        if ($workingDays->isEmpty()) {
            $workingDays = $allDays->values();
        }

        $absences = Absence::query()
            ->whereIn('user_id', $userIds)
            ->where('status', AbsenceStatus::Confirmed)
            ->whereDate('starts_on', '<=', $end->toDateString())
            ->whereDate('ends_on', '>=', $start->toDateString())
            ->get();

        $isAbsent = fn (int $userId, string $day): bool => $absences->contains(
            fn (Absence $absence): bool => $absence->user_id === $userId
                && $absence->starts_on->toDateString() <= $day
                && $absence->ends_on->toDateString() >= $day,
        );

        $units = [];
        $availableUnits = [];
        foreach ($workingDays as $day) {
            foreach ($userIds as $userId) {
                $units[] = [$userId, $day];
                if (! $isAbsent($userId, $day)) {
                    $availableUnits[] = [$userId, $day];
                }
            }
        }

        return $availableUnits !== [] ? $availableUnits : $units;
    }
}
