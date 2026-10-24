<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Validated data to create or edit an area.
 */
final readonly class AreaData
{
    public function __construct(
        public string $name,
        public ?string $description,
    ) {}
}
