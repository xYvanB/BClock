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
     * Recalculates the daily allocations of an activity.
     *
     * Allocations moved by hand in the calendar (`is_manual`) stay, as long as the person is still assigned and the
     * day still falls within the activity period; the others are replaced. The planned hours not
     * covered by manual allocations are split over the remaining person–working day pairs, each with its
     * weight: the day's availability (see CalculateAvailability) relative to the person's daily
     * capacity. Equal weights split evenly; a 2-hour leave out of 8 gives weight 0.75, a vacation 0. The
     * total of the allocations always equals the planned hours. If no working day is left (only
     * weekends/holidays) or no weight, it falls back to all days or to equal weights, so hours are not lost.
     * Archived activities do not change.
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
                // manual allocations fill every remaining day: discard them, so the planned hours are not lost
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
     * [person, date, weight] pairs to split the hours over, excluding those that already have a manual allocation.
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
     * Splits the cents in proportion to the weights; the remainder goes to whoever has the largest fractional part
     * (ties go to the first ones), so the sum is always exact.
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
