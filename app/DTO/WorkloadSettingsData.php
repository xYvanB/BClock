<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Impostazioni del carico di lavoro: tolleranza del livello "pieno".
 */
final readonly class WorkloadSettingsData
{
    public function __construct(
        public bool $toleranceEnabled,
        public float $toleranceHours,
    ) {}
}
