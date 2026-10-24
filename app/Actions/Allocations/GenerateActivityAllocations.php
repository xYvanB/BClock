<?php

declare(strict_types=1);

namespace App\Actions\Allocations;

use App\Enum\WorkStatus;
use App\Models\Activity;
use App\Models\ActivityAllocation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

final class GenerateActivityAllocations
{
    public function __construct(private readonly CalculateAvailability $availability) {}

    /**
     * Ricalcola le quote giornaliere di un'attività.
     *
     * Le quote spostate a mano nel calendario (`is_manual`) restano, purché la persona sia ancora assegnata e il
     * giorno cada ancora nel periodo dell'attività; le altre si sostituiscono. Le ore previste che non sono
     * coperte dalle quote manuali si dividono sulle coppie persona–giorno lavorativo rimaste, ciascuna col suo
     * peso: la disponibilità del giorno (vedi CalculateAvailability) rispetto alla capacità giornaliera della
     * persona. Pesi uguali dividono in parti uguali; un permesso di 2 ore su 8 dà peso 0,75, le ferie 0. Il
     * totale delle quote è sempre uguale alle ore previste. Se non resta nessun giorno lavorativo (solo
     * weekend/festivi) o nessun peso, si ripiega su tutti i giorni o su pesi uguali, così le ore non si perdono.
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
            $userIds = $activity->assignees()->pluck('users.id')->all();
            $start = $activity->starts_on->toDateString();
            $end = $activity->due_on->toDateString();

            $kept = $activity->allocations()
                ->where('is_manual', true)
                ->whereIn('user_id', $userIds)
                ->get()
                ->filter(fn (ActivityAllocation $allocation): bool => $allocation->date->toDateString() >= $start && $allocation->date->toDateString() <= $end)
                ->values();

            $activity->allocations()->whereNotIn('id', $kept->modelKeys())->delete();

            $keptCents = (int) $kept->sum(fn (ActivityAllocation $allocation): int => (int) round((float) $allocation->hours * 100));
            $remainingCents = (int) round((float) $activity->planned_hours * 100) - $keptCents;

            if ($userIds === [] || $remainingCents <= 0) {
                return;
            }

            $units = $this->weightedUnits($activity, $userIds, $kept);

            if ($units === [] && $kept->isNotEmpty()) {
                // le quote manuali occupano ogni giorno rimasto: si scartano, così le ore previste non si perdono
                $activity->allocations()->delete();
                $remainingCents += $keptCents;
                $units = $this->weightedUnits($activity, $userIds, collect());
            }

            if ($units === []) {
                return;
            }

            $cents = $this->splitByWeight($remainingCents, array_column($units, 2));
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
                    'is_manual' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            ActivityAllocation::insert($rows);
        });
    }

    /**
     * Coppie [persona, data, peso] su cui dividere le ore, escluse quelle che hanno già una quota manuale.
     *
     * @param  list<int>  $userIds
     * @param  Collection<int, ActivityAllocation>  $manual
     * @return list<array{int, string, float}>
     */
    private function weightedUnits(Activity $activity, array $userIds, Collection $manual): array
    {
        $availability = $this->availability->handle($userIds, $activity->starts_on->toDateString(), $activity->due_on->toDateString());

        $days = $availability['working_days'] !== [] ? $availability['working_days'] : $availability['days'];

        $taken = $manual
            ->mapWithKeys(fn (ActivityAllocation $allocation): array => [$allocation->user_id.'|'.$allocation->date->toDateString() => true]);

        $units = [];
        foreach ($days as $day) {
            foreach ($userIds as $userId) {
                if ($taken->has($userId.'|'.$day)) {
                    continue;
                }

                $capacity = $availability['capacities'][$userId] ?? 0.0;
                $units[] = [$userId, $day, $capacity > 0 ? $availability['hours'][$userId][$day] / $capacity : 0.0];
            }
        }

        if ($units !== [] && array_sum(array_column($units, 2)) <= 0) {
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
