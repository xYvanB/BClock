<?php

declare(strict_types=1);

namespace App\Actions\Allocations;

use App\Enum\LoadLevel;
use App\Enum\WorkStatus;
use App\Models\ActivityAllocation;
use App\Models\Setting;

final class CalculateDailyLoad
{
    public function __construct(private readonly CalculateAvailability $availability) {}

    /**
     * Load of each person on each day of the range: assigned hours (sum of the allocations of open
     * activities), available hours and level. Completed or archived activities do not count.
     *
     * The level compares the two values to the hundredth of an hour: less is "under capacity", equal "full", more
     * "overloaded". If the Boss or HR enabled the tolerance (settings), "full" also covers a difference
     * up to that many hours, over or under; it does not apply to days without available hours. A day with no available hours and no allocations (weekend, holiday, vacation) has no level (null);
     * with assigned allocations it stays "overloaded".
     *
     * @param  list<int>  $userIds
     * @return array<int, array<string, array{capacity: float, load: float, level: LoadLevel|null}>>
     */
    public function handle(array $userIds, string $from, string $to): array
    {
        $availability = $this->availability->handle($userIds, $from, $to);

        $loads = ActivityAllocation::query()
            ->whereIn('user_id', $userIds)
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $to)
            ->whereHas('activity', fn ($query) => $query->whereNotIn('status', [WorkStatus::Done, WorkStatus::Archived]))
            ->get()
            ->groupBy(fn (ActivityAllocation $allocation): string => $allocation->user_id.'|'.$allocation->date->toDateString())
            ->map(fn ($group): float => (float) $group->sum('hours'));

        $tolerance = Setting::loadToleranceHours();

        $result = [];
        foreach ($userIds as $userId) {
            foreach ($availability['days'] as $day) {
                $capacity = $availability['hours'][$userId][$day];
                $load = $loads->get($userId.'|'.$day, 0.0);

                $result[$userId][$day] = [
                    'capacity' => $capacity,
                    'load' => round($load, 2),
                    'level' => $this->levelOf($load, $capacity, $tolerance),
                ];
            }
        }

        return $result;
    }

    private function levelOf(float $load, float $capacity, float $tolerance): ?LoadLevel
    {
        $loadCents = (int) round($load * 100);
        $capacityCents = (int) round($capacity * 100);

        if ($capacityCents === 0 && $loadCents === 0) {
            return null;
        }

        $toleranceCents = $capacityCents > 0 ? (int) round($tolerance * 100) : 0;

        return match (true) {
            $loadCents < $capacityCents - $toleranceCents => LoadLevel::Under,
            $loadCents <= $capacityCents + $toleranceCents => LoadLevel::Full,
            default => LoadLevel::Over,
        };
    }
}
