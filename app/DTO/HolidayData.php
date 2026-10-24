<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Dati validati per creare o modificare un festivo.
 */
final readonly class HolidayData
{
    public function __construct(
        public string $date,
        public string $name,
    ) {}
}
