<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Validated data to start a timer.
 */
final readonly class TimerData
{
    public function __construct(
        public int $activityId,
        public ?string $description,
    ) {}
}
