<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enum\Role;

/**
 * Attività di un template; la posizione è quella nell'elenco inviato.
 */
final readonly class TemplateActivityData
{
    public function __construct(
        public string $name,
        public ?string $description,
        public float $plannedHours,
        public int $startDay,
        public int $endDay,
        public ?int $suggestedAreaId,
        public ?Role $suggestedRole,
    ) {}
}
