<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enum\Role;

/**
 * Activity of a template; the position is the one in the submitted list.
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
