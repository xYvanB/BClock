<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enum\AbsenceType;

/**
 * Dati validati per creare o modificare un'assenza. Orari solo per i permessi.
 */
final readonly class AbsenceData
{
    public function __construct(
        public AbsenceType $type,
        public string $startsOn,
        public string $endsOn,
        public ?string $startsTime,
        public ?string $endsTime,
        public ?string $note,
    ) {}
}
