<?php

declare(strict_types=1);

namespace App\Actions\Allocations;

use App\Enum\AbsenceStatus;
use App\Enum\AbsenceType;
use App\Enum\WorkStatus;
use App\Models\Absence;
use App\Models\Activity;
use App\Models\ActivityAllocation;
use App\Models\Holiday;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;
use Throwable;

final class GenerateActivityAllocations
{
    /**
     * Ricalcola le quote giornaliere di un'attività (le sostituisce tutte).
     *
     * Le ore previste si dividono sulle coppie persona–giorno lavorativo, ciascuna col suo peso:
     * 1 per un giorno libero, 0 in weekend, festivi e giorni di ferie confermate, e ridotto in proporzione
     * alle ore di permesso confermate (un permesso di 2 ore su 8 dà peso 0,75). Con pesi uguali la divisione
     * è in parti uguali. Il totale delle quote è sempre uguale alle ore previste. Se non resta nessun giorno
     * lavorativo (solo weekend/festivi) o nessun peso, si ripiega su tutti i giorni o su pesi uguali, così le
     * ore non si perdono. Le attività archiviate non cambiano.
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

            $units = $this->weightedUnits($activity, $userIds);
            $cents = $this->splitByWeight($totalCents, array_column($units, 2));
            $now = now();

            $rows = [];
            foreach ($units as $index => [$userId, $date]) {
                if ($cents[$index] === 0) {
                    continue;
                }

                $rows[] = [
                    'activity_id' => $activity->id,
                    'user_id' => $userId,
                    'date' => $date,
                    'hours' => $cents[$index] / 100,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            ActivityAllocation::insert($rows);
        });
    }

    /**
     * Coppie [persona, data, peso] su cui dividere le ore.
     *
     * @param  list<int>  $userIds
     * @return list<array{int, string, float}>
     */
    private function weightedUnits(Activity $activity, array $userIds): array
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

        $capacities = User::whereIn('id', $userIds)->pluck('daily_capacity', 'id');

        $absences = Absence::query()
            ->whereIn('user_id', $userIds)
            ->where('status', AbsenceStatus::Confirmed)
            ->whereDate('starts_on', '<=', $end->toDateString())
            ->whereDate('ends_on', '>=', $start->toDateString())
            ->get();

        $units = [];
        foreach ($workingDays as $day) {
            foreach ($userIds as $userId) {
                $units[] = [$userId, $day, $this->weightOf($absences->where('user_id', $userId), $day, (float) ($capacities[$userId] ?? 8))];
            }
        }

        if (array_sum(array_column($units, 2)) <= 0) {
            return array_map(fn (array $unit): array => [$unit[0], $unit[1], 1.0], $units);
        }

        return $units;
    }

    /**
     * Quota di giornata disponibile (da 0 a 1) di una persona, date le sue assenze confermate.
     *
     * @param  iterable<Absence>  $absences
     */
    private function weightOf(iterable $absences, string $day, float $capacity): float
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

        return $capacity > 0 ? max(0.0, 1 - $leaveHours / $capacity) : 0.0;
    }

    /**
     * Divide i centesimi in proporzione ai pesi; il resto va a chi ha la parte frazionaria maggiore
     * (a parità, ai primi), così la somma è sempre esatta.
     *
     * @param  list<float>  $weights
     * @return list<int>
     */
    private function splitByWeight(int $totalCents, array $weights): array
    {
        $sum = array_sum($weights);
        $exact = array_map(fn (float $weight): float => $totalCents * $weight / $sum, $weights);
        $cents = array_map(fn (float $value): int => (int) floor($value + 1e-9), $exact);

        $leftover = $totalCents - array_sum($cents);
        $order = array_keys($exact);
        usort($order, fn (int $a, int $b): int => ($exact[$b] - $cents[$b]) <=> ($exact[$a] - $cents[$a]));

        foreach (array_slice($order, 0, $leftover) as $index) {
            $cents[$index]++;
        }

        return $cents;
    }
}
