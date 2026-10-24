<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Dati validati per avviare un timer.
 */
final readonly class TimerData
{
    public function __construct(
        public int $activityId,
        public ?string $description,
    ) {}
}
