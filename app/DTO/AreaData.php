<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Dati validati per creare o modificare un'area.
 */
final readonly class AreaData
{
    public function __construct(
        public string $name,
        public ?string $description,
    ) {}
}
