<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enum\AbsenceType;

/**
 * Dati validati per creare o modificare un'assenza.
 */
final readonly class AbsenceData
{
    public function __construct(
        public AbsenceType $type,
        public string $startsOn,
        public string $endsOn,
        public ?string $note,
    ) {}
}
