<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Workload settings: tolerance of the "full" level.
 */
final readonly class WorkloadSettingsData
{
    public function __construct(
        public bool $toleranceEnabled,
        public float $toleranceHours,
    ) {}
}
