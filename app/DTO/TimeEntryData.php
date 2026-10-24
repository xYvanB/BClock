<?php

declare(strict_types=1);

namespace App\DTO;

use Carbon\CarbonImmutable;

/**
 * Validated data to create or edit a time entry.
 * Times are in H:i format, the duration is computed by the Action.
 */
final readonly class TimeEntryData
{
    public function __construct(
        public int $activityId,
        public CarbonImmutable $date,
        public string $startedAt,
        public string $endedAt,
        public ?string $description,
    ) {}

    /**
     * Minutes between start and end, on the same day. Computed directly on hours and minutes,
     * without going through the current clock (which would fill in seconds and date).
     */
    public function durationMinutes(): int
    {
        return $this->minutesOfDay($this->endedAt) - $this->minutesOfDay($this->startedAt);
    }

    private function minutesOfDay(string $time): int
    {
        [$hours, $minutes] = array_map(intval(...), explode(':', $time));

        return $hours * 60 + $minutes;
    }
}
