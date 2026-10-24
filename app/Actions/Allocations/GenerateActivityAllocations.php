<?php

declare(strict_types=1);

namespace App\Actions\Allocations;

use App\Enum\WorkStatus;
use App\Models\Activity;
use App\Models\ActivityAllocation;
use Illuminate\Support\Facades\DB;
use Throwable;

final class GenerateActivityAllocations
{
    public function __construct(private readonly CalculateAvailability $availability) {}

    /**
     * Ricalcola le quote giornaliere di un'attività (le sostituisce tutte).
     *
     * Le ore previste si dividono sulle coppie persona–giorno lavorativo, ciascuna col suo peso: la
     * disponibilità del giorno (vedi CalculateAvailability) rispetto alla capacità giornaliera della persona.
     * Pesi uguali dividono in parti uguali; un permesso di 2 ore su 8 dà peso 0,75, le ferie 0. Il totale delle
     * quote è sempre uguale alle ore previste. Se non resta nessun giorno lavorativo (solo weekend/festivi) o
     * nessun peso, si ripiega su tutti i giorni o su pesi uguali, così le ore non si perdono.
     * Le attività archiviate non cambiano.
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
        $availability = $this->availability->handle($userIds, $activity->starts_on->toDateString(), $activity->due_on->toDateString());

        $days = $availability['working_days'] !== [] ? $availability['working_days'] : $availability['days'];

        $units = [];
        foreach ($days as $day) {
            foreach ($userIds as $userId) {
                $capacity = $availability['capacities'][$userId] ?? 0.0;
                $units[] = [$userId, $day, $capacity > 0 ? $availability['hours'][$userId][$day] / $capacity : 0.0];
            }
        }

        if (array_sum(array_column($units, 2)) <= 0) {
            return array_map(fn (array $unit): array => [$unit[0], $unit[1], 1.0], $units);
        }

        return $units;
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
